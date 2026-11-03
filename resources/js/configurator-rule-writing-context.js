import {parseRuleWriting, ruleWritingByteOffset, ruleWritingEditorOffset} from './configurator-rule-writing-model.js';
import {projectWritingSource} from './configurator-rule-writing-editor.js';

export const inspectRuleWritingSyntax = source => parseRuleWriting.inspectSyntax(source);
const commands = [
    ['when', 'Condition', 'Start conditions', '/when '], ['and', 'Condition', 'Join with And', '/and '], ['or', 'Condition', 'Join with Or', '/or '],
    ['pair', 'Mapping', 'Pair Attributes', '/pair '], ['case', 'Mapping', 'Add mapping case', '/case '], ['other', 'Mapping', 'Other target values', '/other '],
    ...['allow', 'exclude', 'hide', 'disable', 'set-label', 'set-display-value', 'set-hint'].map(key => [key, key.startsWith('set-') ? 'Text' : 'Effects', key.replaceAll('-', ' '), `/${key} `]),
];
const tone = category => ({Condition: 'source', Group: 'group', Mapping: 'allowed', Effects: 'excluded', Text: 'text'}[category] ?? 'source');
const quotedKey = key => /^[A-Za-z_][A-Za-z0-9_-]*$/.test(key) ? key : JSON.stringify(key);

export function ruleWritingContext({kind, source, editorSelection, references, picker = null}) {
    const syntax = inspectRuleWritingSyntax(source), projection = projectWritingSource(source);
    const selection = editorSelection ?? [projection.text.length, projection.text.length];
    const from = selection[0], to = selection[1], rawFrom = projection.rawOffset(from), rawTo = projection.rawOffset(to);
    const activeByte = rawFrom === null ? null : ruleWritingByteOffset(source, rawFrom);
    const toEditor = byte => {const raw = ruleWritingEditorOffset(source, byte); return raw === null ? null : projection.editorOffset(raw);};
    const spans = syntax.spans ?? [], tokens = syntax.tokens ?? [], attributes = references?.attributes ?? [];
    const lineStart = from > 0 ? projection.text.lastIndexOf('\n', from - 1) + 1 : 0;
    const lineEnd = projection.text.indexOf('\n', from);
    const startByte = ruleWritingByteOffset(source, projection.rawOffset(lineStart)) ?? 0;
    const endByte = ruleWritingByteOffset(source, projection.rawOffset(lineEnd < 0 ? projection.text.length : lineEnd)) ?? 0;
    const lineTokens = tokens.filter(t => t.start >= startByte && t.start < endByte);
    const before = lineTokens.filter(t => t.start < activeByte);
    const active = spans.find(t => activeByte >= t.start && activeByte <= t.end && !['comment', 'newline'].includes(t.type));
    const protectedSpan = spans.find(t => ['comment', 'string', 'block'].includes(t.type) && activeByte > t.start && (activeByte < t.end || !t.complete && activeByte === t.end || t.type === 'comment' && activeByte === t.end));
    const protectedText = Boolean(protectedSpan);
    const issues = syntax.diagnostics.map(d => ({...d, code: 'syntax', severity: /Complete|Close|Add|Choose|needs|incomplete|Provide|Select|before/i.test(d.message) ? 'incomplete' : 'invalid'}));
    const issueReference = message => issues.push({code: 'reference', severity: 'unresolved', message, start: startByte, end: endByte});
    const first = lineTokens[0], command = first?.value, bareCondition = first?.type === 'attribute' || first?.type === 'product';
    const reference = lineTokens.find(t => ['attribute', 'product'].includes(t.type));
    const operator = lineTokens.find(t => ['=', '!=', 'in', 'not-in', 'contains'].includes(t.value));
    const pair = syntax.statements.filter(t => t.kind === 'pair' && t.start < (activeByte ?? 0)).at(-1);
    const arrow = lineTokens.find(t => t.value === '->');
    const side = command === '/case' && arrow && activeByte > arrow.start ? 'target' : 'source';
    let attributeKey = command === '/case' ? pair?.[side] : reference?.type === 'attribute' ? reference.value : null;
    const matches = attributes.filter(a => a.key === attributeKey);
    const attribute = matches.length === 1 && !matches[0].ambiguous ? matches[0] : null;
    if (attributeKey && !attribute) issueReference(`Attribute @${attributeKey} is ${matches.length ? 'ambiguous' : 'not in these references'}. Refresh references or use the regular form.`);
    const valueList = lineTokens.filter(t => command !== '/case' || (side === 'target' ? t.start > (arrow?.start ?? Infinity) : !arrow || t.start < arrow.start));
    const selectedValues = new Set(valueList.filter(t => t.type === 'value' && (!active || t.start !== active.start)).map(t => t.value));
    const last = before.at(-1), beforeCaret = projection.text.slice(0, from), tail = beforeCaret.slice(beforeCaret.lastIndexOf('\n') + 1);
    let phase = 'command';
    if (command === '/other') phase = 'behavior';
    else if (command === '/pair') phase = !reference ? 'attribute' : arrow ? 'target-attribute' : 'pair-arrow';
    else if (command === '/case') phase = 'values';
    else if (operator) phase = reference?.type === 'product' ? 'text' : 'values';
    else if (reference) phase = ['when', 'and', 'or'].includes(command?.slice(1)) || bareCondition ? 'operator' : 'values';
    else if (['/when', '/and', '/or', '/allow', '/exclude', '/hide', '/disable', '/set-label', '/set-display-value', '/set-hint'].includes(command)) phase = 'attribute';
    if (active?.type === 'attribute' && phase !== 'operator') phase = command === '/pair' && arrow && active.start > arrow.start ? 'target-attribute' : 'attribute';
    if (active?.type === 'value') phase = 'values';
    const groupStack = [];
    for (const token of tokens.filter(t => t.start < activeByte)) { if (token.type === 'word' && token.value === '{') groupStack.push(token); else if (token.type === 'word' && token.value === '}') groupStack.pop(); }
    const activeToken = lineTokens.find(t => t.start === active?.start);
    if (activeToken && ['=', '!=', 'in', 'not-in', 'contains'].includes(activeToken.value)) phase = 'operator';
    if (groupStack.length && !lineTokens.length) phase = 'attribute';
    const partialMarker = tail.match(/(?:^|\s)([@#$])([^\s]*)$/);
    const marker = active?.type === 'attribute' ? '@' : active?.type === 'value' ? '#' : active?.type === 'product' ? '$' : partialMarker?.[1];
    const editFrom = active && ['attribute', 'value', 'product'].includes(active.type) ? toEditor(active.start) : partialMarker ? from - partialMarker[0].trimStart().length : from;
    const editTo = active && ['attribute', 'value', 'product'].includes(active.type) && active.complete ? toEditor(active.end) : to;
    const baseAction = (label, insertText, category, range = [editFrom, editTo]) => ({label, insertText, category, tone: tone(category), from: range[0], to: range[1], explanation: `${label}. Inserts one explicit source edit.`});
    const open = (label, value, category) => ({label, open: value, category, tone: tone(category), explanation: `${label} from the current Configurator references.`});
    const mode = picker ?? (marker === '@' ? 'attributes' : marker === '$' ? 'products' : marker === '#' || phase === 'values' ? 'values' : phase === 'attribute' || phase === 'target-attribute' ? 'attributes' : phase === 'operator' ? 'operators' : phase === 'behavior' ? 'behaviors' : 'commands');
    let choices = [];
    const priorNodes=syntax.statements.flatMap(node=>node.kind==='group'?node.children:[node]).filter(node=>node.end <= (activeByte??0));
    const hasConditions=priorNodes.some(node=>node.kind==='condition'),hasOutcomes=priorNodes.some(node=>node.kind!=='condition');
    const hasPair=priorNodes.some(node=>node.kind==='pair'),hasCase=priorNodes.some(node=>node.kind==='case');
    if (mode === 'attributes') choices = attributes.map(a => baseAction(`${a.label} · ${a.key}${a.ambiguous ? ' (ambiguous)' : ''}`, `@${a.key}`, 'Condition'));
    if (mode === 'products') choices = (references?.product_fields ?? []).map(field => baseAction(field.label, `$${quotedKey(field.key)}`, 'Condition'));
    if (mode === 'values' && attribute) choices = attribute.values.filter(v => v.semantic_id && !v.ambiguous && !selectedValues.has(v.semantic_id)).map(v => baseAction(`${v.label} · ${v.semantic_id}`, `#${v.semantic_id}`, command === '/case' ? 'Mapping' : 'Condition'));
    if (mode === 'operators') choices = ['=', '!=', 'in', 'not-in', ...(reference?.type === 'product' ? ['contains'] : [])].map(token => baseAction(token, operator && activeToken === operator ? token : ` ${token} `, 'Condition', operator && activeToken === operator ? [toEditor(operator.start), toEditor(operator.end)] : [from, to]));
    if (mode === 'behaviors') choices = ['hide', 'disable'].map(token => baseAction(`Others ${token}`, token, 'Mapping', [from, to]));
    if (mode === 'commands') {
        let range = [from, to], search = '';
        const candidate = lineTokens.find(t => t.type === 'word' && t.value?.startsWith('/') && activeByte >= t.start && activeByte <= t.end);
        if (candidate) {range = [toEditor(candidate.start), toEditor(candidate.end)]; search = candidate.value.slice(1);}
        else if (tail.trim() === '/') range = [from - 1, to];
        choices = commands.filter(([key, category]) => (kind === 'Mapping' ? !['Effects', 'Text'].includes(category) : category !== 'Mapping') && (!search || key.startsWith(search)) && (key==='when'?!hasConditions&&!hasOutcomes:key==='and'?hasConditions&&!hasOutcomes&&Boolean(syntax.context?.flat)&&syntax.context.flat!=='Any':key==='or'?hasConditions&&!hasOutcomes&&Boolean(syntax.context?.flat)&&syntax.context.flat!=='And':key==='pair'?!hasPair&&!hasOutcomes:key==='case'?hasPair:key==='other'?hasCase:true)).map(([,category,label,text]) => { const meta = references?.presentation?.effects?.find(effect => effect.token === text.trim()); return {...baseAction(meta?.label ?? label,text,category,range),tone:meta?.tone ?? tone(category),icon:meta?.icon ?? 'command'}; });
    }
    if (mode === 'commands' && groupStack.length) {
        choices = [baseAction('Close condition group', '}', 'Group', [from,to]), ...(groupStack.length === 1 ? [baseAction('All conditions', 'all {\n  \n}', 'Group', [from,to]),baseAction('Any condition', 'any {\n  \n}', 'Group', [from,to])] : [])];
    }
    if (!picker && marker && mode !== 'commands') {
        const prefix = tail.slice(tail.lastIndexOf(marker) + 1).toLowerCase();
        if (prefix && !prefix.includes('"')) choices = choices.filter(c => c.insertText.slice(1).toLowerCase().startsWith(prefix));
    }
    if (protectedText || activeByte === null || rawTo === null) choices = [];
    const nextGroups = [];
    if (!protectedText) {
        if (phase === 'values') {
            const category=command === '/case' ? 'Mapping' : 'Condition';
            const actions=!attribute?[open('Refresh references','refresh','Condition')]:last?.type==='value'?[baseAction('Add another Value',' | ',category,[from,to])]:[open('Choose another Value','values',category)];
            if(attribute&&command==='/case'&&!arrow&&last?.type==='value')actions.push(baseAction('Continue to target Values',' -> ','Mapping',[from,to]));
            nextGroups.push({category,actions});
            if(command?.startsWith('/set-')&&attribute)nextGroups.push({category:'Text',actions:[open('Write the effect text','text','Text')]});
        }
        else if (phase === 'text') nextGroups.push({category: 'Text', actions: last?.type==='string'&&['in','not-in'].includes(operator?.value)?[baseAction('Add another text value',' | ','Text',[from,to])]:[open('Write quoted text or a text block', 'text', 'Text')]});
        else if (phase === 'operator') nextGroups.push({category: 'Condition', actions: [open('Choose a comparison', 'operators', 'Condition')]});
        else if (phase === 'pair-arrow') nextGroups.push({category: 'Mapping', actions: [baseAction('Continue to target Attribute', ' -> ', 'Mapping', [from,to])]});
        else if (phase === 'attribute') {
            nextGroups.push({category: 'Condition', actions: [open('Attribute condition', 'attributes', 'Condition'), ...((groupStack.length || ['/when','/and','/or'].includes(command)) ? [open('Product field condition', 'products', 'Condition')] : [])]});
            if (command === '/when') nextGroups.push({category: 'Group', actions: [baseAction('All conditions', 'all {\n  \n}', 'Group', [from,to]),baseAction('Any conditions', 'all {\n  any {\n    \n  }\n}', 'Group', [from,to])]});
        } else nextGroups.push({category: kind === 'Mapping' ? 'Mapping' : 'Effects', actions: [open('Choose a full command', 'commands', kind === 'Mapping' ? 'Mapping' : 'Effects')]});
    }
    if (attribute) for (const token of valueList.filter(t => t.type === 'value')) {
        if (token === active && !token.complete) continue;
        const options = attribute.values.filter(v => v.semantic_id === token.value);
        if (options.length !== 1 || options[0].ambiguous) issueReference(`Value #${token.value} is ${options.length ? 'ambiguous' : 'unresolved'} for @${attributeKey}.`);
    }
    const allowsList = command === '/case' || ['/allow','/exclude','/hide','/disable'].includes(command) || command?.startsWith('/set-') || ['in','not-in'].includes(operator?.value);
    const toolbar = {values: !protectedText && phase === 'values' && Boolean(attribute), attributes: !protectedText && (['attribute','target-attribute'].includes(phase) || active?.type==='attribute'), products: !protectedText && (phase==='attribute' && (groupStack.length>0 || ['/when','/and','/or'].includes(command)) || active?.type==='product'), commands: !protectedText, addValue: !protectedText && Boolean(attribute||reference?.type==='product') && allowsList && ['value','string'].includes(last?.type), arrow: !protectedText && (phase === 'pair-arrow' || command === '/case' && !arrow && last?.type === 'value'), closeGroup: !protectedText && groupStack.length > 0, text: !protectedText && Boolean(reference) && (command?.startsWith('/set-') || reference.type==='product'&&Boolean(operator))};
    return {syntax, command, canStartConditions:!hasConditions&&!hasOutcomes, atLineEnd: from === (lineEnd < 0 ? projection.text.length : lineEnd), groupDepth: groupStack.length, phase, attributeKey, side, protectedText, activeByte, from, to, choices, issues, nextGroups, toolbar, line: projection.text.slice(0, from).split('\n').length, learning: {title: phase === 'values' ? 'A list contains distinct Values' : 'Write one complete instruction', text: phase === 'values' ? 'The pipe adds another Value to this list. It does not join two conditions.' : 'Choices only insert after you explicitly choose one. Logic is local interpretation; saved records are separate.'}};
}
