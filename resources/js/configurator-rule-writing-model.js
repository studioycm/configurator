class WritingSyntaxError extends Error {}

function assertSource(source) {
    if (typeof source !== 'string') throw new TypeError('Writing source must be a string.');
}

function unicodePosition(source, offset, editorToByte) {
    let editor = 0;
    let bytes = 0;
    let position = offset === 0 ? 0 : null;
    while (editor < source.length) {
        const point = source.codePointAt(editor);
        if (point >= 0xD800 && point <= 0xDFFF) return null;
        editor += point > 0xFFFF ? 2 : 1;
        bytes += point < 0x80 ? 1 : point < 0x800 ? 2 : point < 0x10000 ? 3 : 4;
        if ((editorToByte ? editor : bytes) === offset) position = editorToByte ? bytes : editor;
    }
    return { bytes, position };
}

export function ruleWritingByteOffset(source, utf16Offset) {
    assertSource(source);
    if (!Number.isInteger(utf16Offset) || utf16Offset < 0 || utf16Offset > source.length) return null;
    return unicodePosition(source, utf16Offset, true)?.position ?? null;
}

export function ruleWritingEditorOffset(source, byteOffset) {
    assertSource(source);
    if (!Number.isInteger(byteOffset) || byteOffset < 0) return null;
    return unicodePosition(source, byteOffset, false)?.position ?? null;
}

function letter(byte) {
    return (byte >= 65 && byte <= 90) || (byte >= 97 && byte <= 122);
}

function referenceStart(byte) {
    return letter(byte) || (byte >= 48 && byte <= 57) || byte === 95;
}

function tokenize(bytes, decode) {
    const tokens = [];
    let i = 0;
    while (i < bytes.length) {
        const start = i;
        const character = bytes[i];
        if (character === 13 || character === 10) {
            i += character === 13 && bytes[i + 1] === 10 ? 2 : 1;
            tokens.push({ type: 'newline', value: '\n', start, end: i });
            continue;
        }
        if (character === 32 || character === 9) {
            i++;
            continue;
        }
        if (character === 47 && bytes[i + 1] === 47) {
            while (i < bytes.length && bytes[i] !== 13 && bytes[i] !== 10) i++;
            continue;
        }
        const prefix = [64, 35, 36].includes(character) ? character : null;
        if (prefix !== null) i++;
        let value;
        let type;
        if (bytes[i] === 34) {
            const quote = i++;
            let closed = false;
            while (i < bytes.length) {
                if (bytes[i] === 92) {
                    i += 2;
                    continue;
                }
                if (bytes[i++] === 34) {
                    closed = true;
                    break;
                }
            }
            if (!closed) throw new WritingSyntaxError('Close the quoted text.');
            try {
                value = JSON.parse(decode(quote, i));
            } catch {
                throw new WritingSyntaxError('Invalid quoted JSON text.');
            }
            if (unicodePosition(value) === null) throw new WritingSyntaxError('Invalid quoted JSON text.');
            if (prefix !== null && prefix !== 36) throw new WritingSyntaxError('Only Product keys use quoted references.');
            type = prefix === 36 ? 'product' : 'string';
        } else if (prefix === null && bytes[i] === 91 && bytes[i + 1] === 91) {
            i += 2;
            let segment = i;
            const parts = [];
            let closed = false;
            while (i < bytes.length) {
                if (bytes[i] === 92 && bytes[i + 1] === 93 && bytes[i + 2] === 93) {
                    parts.push(decode(segment, i), ']]');
                    i += 3;
                    segment = i;
                } else if (bytes[i] === 92 && bytes[i + 1] === 92) {
                    parts.push(decode(segment, i), '\\');
                    i += 2;
                    segment = i;
                } else if (bytes[i] === 93 && bytes[i + 1] === 93) {
                    parts.push(decode(segment, i));
                    i += 2;
                    closed = true;
                    break;
                } else {
                    i++;
                }
            }
            if (!closed) throw new WritingSyntaxError('Close the text block with ]].');
            value = parts.join('');
            type = 'string';
        } else if (prefix !== null) {
            if (!referenceStart(bytes[i])) throw new WritingSyntaxError('Complete the reference.');
            const reference = i++;
            while (referenceStart(bytes[i]) || bytes[i] === 46 || bytes[i] === 45) i++;
            value = decode(reference, i);
            type = prefix === 64 ? 'attribute' : prefix === 35 ? 'value' : 'product';
        } else if ((character === 45 && bytes[i + 1] === 62) || (character === 33 && bytes[i + 1] === 61)) {
            i += 2;
            value = decode(start, i);
            type = 'word';
        } else if ([61, 124, 123, 125].includes(character)) {
            value = decode(i, ++i);
            type = 'word';
        } else if (letter(character) || (character === 47 && letter(bytes[i + 1]))) {
            if (character === 47) i++;
            i++;
            while (letter(bytes[i]) || bytes[i] === 45) i++;
            value = decode(start, i);
            type = 'word';
        } else {
            throw new WritingSyntaxError('Unknown character in source.');
        }
        tokens.push({ type, value, start, end: i });
    }
    return tokens;
}

function values(tokens, type) {
    if (tokens.length === 0) throw new WritingSyntaxError('Add the Value after the separator.');
    const output = [];
    for (const [index, token] of tokens.entries()) {
        if (index % 2 === 0) {
            if (token.type !== type) throw new WritingSyntaxError('Choose the expected Value or quoted text.');
            output.push(token.value);
        } else if (token.value !== '|') {
            throw new WritingSyntaxError('Separate Values with a pipe.');
        }
    }
    if (tokens.length % 2 === 0) throw new WritingSyntaxError('Add the Value after the separator.');
    if (new Set(output).size !== output.length) throw new WritingSyntaxError('Use distinct Values in this list.');
    return output;
}

function condition(tokens) {
    const reference = tokens[0];
    if (!['attribute', 'product'].includes(reference?.type)) throw new WritingSyntaxError('Choose an Attribute or Product field.');
    const operators = new Map([['=', 'Equals'], ['!=', 'NotEquals'], ['in', 'In'], ['not-in', 'NotIn'], ['contains', 'Contains']]);
    const operator = operators.get(tokens[1]?.value);
    if (!operator || (operator === 'Contains' && reference.type !== 'product')) throw new WritingSyntaxError('Choose a supported comparison.');
    const operands = values(tokens.slice(2), reference.type === 'attribute' ? 'value' : 'string');
    if (!['In', 'NotIn'].includes(operator) && operands.length !== 1) throw new WritingSyntaxError('Use in or not-in for multiple Values.');
    return { kind: 'condition', source_kind: reference.type === 'attribute' ? 'SelectionOption' : 'ProductProperty', reference: reference.value, operator, values: operands };
}

export function parseRuleWriting(source) {
    assertSource(source);
    const result = { source, format_version: 1, statements: [], diagnostics: [] };
    const info = unicodePosition(source);
    const failure = (message, start, end) => {
        result.statements = [];
        result.diagnostics.push({ code: 'syntax', severity: 'error', start, end, message });
        return result;
    };
    if (info === null) return failure('Source contains an incomplete Unicode character.', 0, 0);
    if (info.bytes > 1048576) return failure('Source must be UTF-8 within the authoring size limit.', 0, info.bytes);
    const bytes = new TextEncoder().encode(source);
    const decoder = new TextDecoder('utf-8', { fatal: true, ignoreBOM: true });
    const decode = (start, end) => decoder.decode(bytes.subarray(start, end));
    let offset = 0;
    try {
        const lines = [];
        let line = [];
        for (const token of tokenize(bytes, decode)) {
            if (token.type === 'newline') {
                if (line.length) {
                    lines.push(line);
                    line = [];
                }
            } else {
                line.push(token);
            }
        }
        if (line.length) lines.push(line);
        let root = false;
        let group = null;
        let flat = null;
        let sawOutcome = false;
        for (let tokens of lines) {
            offset = tokens[0].start;
            const words = tokens.map(token => token.value);
            const command = words[0];
            const end = tokens.at(-1).end;
            let node = { start: offset, end, raw: decode(offset, end) };
            if (words.length === 3 && command === '/when' && words[1] === 'all' && words[2] === '{') {
                if (root || result.statements.length || sawOutcome) throw new WritingSyntaxError('Use one outer All condition block before outcomes.');
                root = true;
                continue;
            }
            if (['all', 'any', '/all', '/any'].includes(command) && words[1] === '{' && words.length === 2) {
                if (!root || group !== null) throw new WritingSyntaxError('Only one child group level is supported.');
                const operator = command.replace(/^\//, '');
                group = { ...node, kind: 'group', operator: operator[0].toUpperCase() + operator.slice(1), children: [] };
                continue;
            }
            if (words.length === 1 && command === '}') {
                if (group !== null) {
                    if (group.children.length === 0) throw new WritingSyntaxError('A group needs conditions.');
                    group.end = node.end;
                    group.raw = decode(group.start, group.end);
                    result.statements.push(group);
                    group = null;
                } else if (root) {
                    root = false;
                } else {
                    throw new WritingSyntaxError('No group is open.');
                }
                continue;
            }
            if (root || ['/when', '/and', '/or'].includes(command)) {
                if (sawOutcome) throw new WritingSyntaxError('Conditions precede outcomes.');
                if (!root) {
                    if (command === '/when') {
                        if (flat !== null) throw new WritingSyntaxError('Start conditions once.');
                        flat = 'All';
                    } else if (flat === null) {
                        throw new WritingSyntaxError('Start conditions with /when.');
                    }
                    if (command === '/or') {
                        if (flat === 'And') throw new WritingSyntaxError('Use explicit groups for mixed joins.');
                        flat = 'Any';
                    }
                    if (command === '/and') {
                        if (flat === 'Any') throw new WritingSyntaxError('Use explicit groups for mixed joins.');
                        flat = 'And';
                    }
                    tokens = tokens.slice(1);
                }
                const predicate = { ...node, ...condition(tokens) };
                if (group !== null) group.children.push(predicate);
                else result.statements.push(predicate);
                continue;
            }
            sawOutcome = true;
            if (command === '/pair') {
                if (tokens.length !== 4 || tokens[1].type !== 'attribute' || words[2] !== '->' || tokens[3].type !== 'attribute') throw new WritingSyntaxError('Complete /pair @source -> @target.');
                node = { ...node, kind: 'pair', source: words[1], target: words[3] };
            } else if (command === '/case') {
                const arrow = words.indexOf('->');
                if (arrow < 0) throw new WritingSyntaxError('A case needs source -> target Values.');
                node = { ...node, kind: 'case', source_values: values(tokens.slice(1, arrow), 'value'), target_values: values(tokens.slice(arrow + 1), 'value') };
            } else if (command === '/other') {
                if (tokens.length !== 2 || !['hide', 'disable'].includes(words[1])) throw new WritingSyntaxError('Use /other hide or disable after a case.');
                node = { ...node, kind: 'other', behavior: words[1][0].toUpperCase() + words[1].slice(1) };
            } else if (['/allow', '/exclude', '/hide', '/disable', '/set-label', '/set-display-value', '/set-hint'].includes(command)) {
                if (tokens[1]?.type !== 'attribute') throw new WritingSyntaxError('Choose a target Attribute.');
                const rest = tokens.slice(2);
                let text = null;
                if (command.startsWith('/set-')) {
                    const last = rest.pop();
                    if (last?.type !== 'string') throw new WritingSyntaxError('Provide quoted text or a text block.');
                    text = last.value;
                }
                const selected = rest.length === 0 ? [] : values(rest, 'value');
                if (selected.length === 0 && ['/allow', '/exclude', '/disable'].includes(command)) throw new WritingSyntaxError('Select target Values.');
                node = { ...node, kind: command.slice(1), attribute: words[1], values: selected, text };
            } else {
                throw new WritingSyntaxError('Unknown or incomplete full command.');
            }
            result.statements.push(node);
        }
        if (root || group !== null) throw new WritingSyntaxError('Close the condition group.');
        if (flat === 'Any') {
            const conditions = result.statements.filter(node => node.kind === 'condition');
            if (conditions.length) {
                const first = conditions[0];
                const last = conditions.at(-1);
                result.statements = [{ kind: 'group', operator: 'Any', children: conditions, start: first.start, end: last.end, raw: decode(first.start, last.end) }, ...result.statements.filter(node => node.kind !== 'condition')];
            }
        }
    } catch (error) {
        if (!(error instanceof WritingSyntaxError)) throw error;
        return failure(error.message, offset, info.bytes);
    }
    return result;
}
