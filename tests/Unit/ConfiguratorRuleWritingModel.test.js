import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { parseRuleWriting, ruleWritingByteOffset, ruleWritingEditorOffset } from '../../resources/js/configurator-rule-writing-model.js';

const fixtures = JSON.parse(await readFile(new URL('../RuleWritingFixtures.json', import.meta.url), 'utf8'));
const semantic = nodes => nodes.map(({ start, end, raw, children, ...node }) => children ? { ...node, children: semantic(children) } : node);
function spans(source, nodes) {
    const bytes = new TextEncoder().encode(source);
    for (const node of nodes) {
        assert.equal(new TextDecoder('utf-8', { fatal: true }).decode(bytes.slice(node.start, node.end)), node.raw);
        assert.ok(Number.isInteger(node.start) && node.start >= 0 && node.end <= bytes.length && node.end >= node.start);
        if (node.children) spans(source, node.children);
    }
}
for (const fixture of fixtures) {
    test(`portable grammar: ${fixture.name}`, () => {
        const result = parseRuleWriting(fixture.source);
        assert.deepEqual(Object.keys(result), ['source', 'format_version', 'statements', 'diagnostics']);
        assert.equal(result.source, fixture.source);
        assert.equal(result.format_version, 1);
        if (fixture.valid) {
            assert.deepEqual(result.diagnostics, []);
            assert.deepEqual(result.statements.map(node => node.kind), fixture.kinds);
            if (fixture.ast) assert.deepEqual(semantic(result.statements), fixture.ast);
            if ('text' in fixture) assert.equal(result.statements[0].text, fixture.text);
            spans(fixture.source, result.statements);
            for (const span of fixture.spans ?? []) {
                const node = span.path.reduce((current, part) => current[part], result.statements);
                assert.equal(node.start, span.start);
                assert.equal(node.end, span.end);
                assert.equal(node.raw, span.raw);
            }
        } else {
            assert.deepEqual(result.statements, []);
            assert.equal(result.diagnostics.length, 1);
            for (const [key, value] of Object.entries(fixture.diagnostic ?? { code: 'syntax', severity: 'error' })) assert.equal(result.diagnostics[0][key], value);
            assert.equal(typeof result.diagnostics[0].message, 'string');
        }
    });
}

test('parse results are independent and preserve literal source', () => {
    const source = '/when @A in #one | #two\n/hide @B';
    const first = parseRuleWriting(source);
    const second = parseRuleWriting(source);
    first.statements[0].values[0] = 'changed';
    first.diagnostics.push({ code: 'injected' });
    assert.deepEqual(second.statements[0].values, ['one', 'two']);
    assert.deepEqual(parseRuleWriting(source), second);
});

test('byte and editor offsets use exact code-point boundaries', () => {
    const source = 'Aשe\u0301😀\r\nZ';
    const positions = [[0, 0], [1, 1], [2, 3], [3, 4], [4, 6], [6, 10], [7, 11], [8, 12], [9, 13]];
    for (const [editor, byte] of positions) {
        assert.equal(ruleWritingByteOffset(source, editor), byte);
        assert.equal(ruleWritingEditorOffset(source, byte), editor);
    }
    for (const offset of [-1, 0.5, 5, 10, NaN, Infinity]) assert.equal(ruleWritingByteOffset(source, offset), null);
    for (const offset of [-1, 0.5, 2, 5, 7, 8, 9, 14, NaN, Infinity]) assert.equal(ruleWritingEditorOffset(source, offset), null);
    assert.equal(ruleWritingByteOffset('', 0), 0);
    assert.equal(ruleWritingEditorOffset('', 0), 0);
});

test('unpaired raw UTF-16 remains exact local source with no byte identity claim', () => {
    for (const invalid of [String.fromCharCode(0xD800), String.fromCharCode(0xDC00), 'x' + String.fromCharCode(0xD800) + 'y']) {
        const result = parseRuleWriting(invalid);
        assert.equal(result.source, invalid);
        assert.deepEqual(result.statements, []);
        assert.deepEqual(result.diagnostics.map(({ message, ...rest }) => rest), [{ code: 'syntax', severity: 'error', start: 0, end: 0 }]);
        assert.equal(ruleWritingByteOffset(invalid, 0), null);
        assert.equal(ruleWritingEditorOffset(invalid, 0), null);
    }
});

test('source limit counts UTF-8 bytes without normalization', () => {
    const source = '//' + 'ש'.repeat(524287);
    assert.equal(new TextEncoder().encode(source).length, 1048576);
    assert.deepEqual(parseRuleWriting(source).diagnostics, []);
    const over = source + 'x';
    assert.deepEqual(parseRuleWriting(over).statements, []);
    assert.deepEqual(parseRuleWriting(over).diagnostics.map(({ message, ...rest }) => rest), [{ code: 'syntax', severity: 'error', start: 0, end: 1048577 }]);
});

test('pure APIs refuse non-string sources without coercion', () => {
    for (const invalid of [null, undefined, 7, {}, ['text']]) {
        assert.throws(() => parseRuleWriting(invalid), TypeError);
        assert.throws(() => ruleWritingByteOffset(invalid, 0), TypeError);
        assert.throws(() => ruleWritingEditorOffset(invalid, 0), TypeError);
    }
});

test('a fresh module import and parsing need no browser state or Node Buffer', async () => {
    const source = await readFile(new URL('../../resources/js/configurator-rule-writing-model.js', import.meta.url), 'utf8');
    const guarded = ['window', 'document', 'localStorage', 'sessionStorage', 'Alpine', 'Livewire', 'Buffer'];
    const originals = guarded.map(name => [name, Object.getOwnPropertyDescriptor(globalThis, name)]);
    try {
        for (const name of guarded) Object.defineProperty(globalThis, name, { configurable: true, get() { throw new Error(`Unexpected global access: ${name}`); } });
        const api = await import('data:text/javascript,' + encodeURIComponent(source));
        assert.deepEqual(Object.keys(api).sort(), ['parseRuleWriting', 'ruleWritingByteOffset', 'ruleWritingEditorOffset'].sort());
        assert.deepEqual(semantic(api.parseRuleWriting('/hide @A').statements), [{ kind: 'hide', attribute: 'A', values: [], text: null }]);
        assert.equal(api.ruleWritingByteOffset('ש😀', 3), 6);
        assert.equal(api.ruleWritingEditorOffset('ש😀', 6), 3);
    } finally {
        for (const [name, descriptor] of originals) {
            if (descriptor) Object.defineProperty(globalThis, name, descriptor);
            else delete globalThis[name];
        }
    }
});
