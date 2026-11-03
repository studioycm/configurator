import {test} from 'node:test';
import assert from 'node:assert/strict';
import {parseRuleWriting} from '../../resources/js/configurator-rule-writing-model.js';
import {ruleWritingContext} from '../../resources/js/configurator-rule-writing-context.js';
const inspectRuleWritingSyntax = parseRuleWriting.inspectSyntax;
const references = {fingerprint: 'refs-1', attributes: [
    {key: 'body', label: 'Body material', values: [{semantic_id: 'steel', label: 'Steel'}, {semantic_id: 'iron', label: 'Iron'}]},
    {key: 'bolts', label: 'Bolt set', values: [{semantic_id: 'steel', label: 'Steel'}, {semantic_id: 'ss316', label: 'Stainless steel'}]},
], product_fields: [{key: 'Connection Size.מידה', label: 'Connection Size.מידה'}]};
const context = (source, extra = {}) => ruleWritingContext({source, kind: 'Mapping', references, editorSelection: [source.replaceAll('\r\n', '\n').length, source.replaceAll('\r\n', '\n').length], ...extra});

test('partial syntax keeps complete conditions and grouped children while strict parsing still rejects', () => {
    const source = '/when all {\n  @body = #iron\n  any {\n    @bolts = #steel\n    @body in #steel |';
    assert.deepEqual(parseRuleWriting(source).statements, []);
    const partial = inspectRuleWritingSyntax(source);
    assert.equal(partial.statements[0].reference, 'body');
    assert.equal(partial.statements[1].kind, 'group');
    assert.equal(partial.statements[1].children[0].reference, 'bolts');
    assert(partial.diagnostics.length > 0);
});
test('after-pipe choices offer only the remaining Values from this Attribute and list', () => {
    const source = '/when @body in #steel |';
    const c = context(source);
    assert.equal(c.phase, 'values'); assert.equal(c.attributeKey, 'body'); assert.equal(c.toolbar.values, true);
    assert.deepEqual(c.choices.map(x => x.insertText), ['#iron']);
    assert(c.nextGroups.some(group => group.actions.some(action => action.open === 'values')));
});
test('same Value remains usable in other conditions, cases and target lists', () => {
    const c = context('/when @body = #steel\n/pair @body -> @bolts\n/case #steel -> ');
    assert.equal(c.attributeKey, 'bolts'); assert.deepEqual(c.choices.map(x => x.insertText), ['#steel', '#ss316']);
});
test('middle-token replacement keeps the active Value available and scopes duplicate filtering to its list', () => {
    const source = '/when @body in #steel | #iron';
    const offset = source.indexOf('#steel') + 3;
    const c = context(source, {editorSelection: [offset, offset]});
    assert(c.choices.some(x => x.insertText === '#steel'));
    assert(!c.choices.some(x => x.insertText === '#iron'));
    assert.equal(source.slice(c.choices[0].from, c.choices[0].to), '#steel');
});
test('comments, JSON strings and multiline text suppress structural suggestions', () => {
    for (const source of ['// /when @body', '/set-hint @body "some @body', '/set-hint @body [[line\n/when @body']) {
        const c = context(source, {kind: 'Advanced'}); assert.equal(c.protectedText, true); assert.deepEqual(c.choices, []);
    }
});
test('quoted Product keys preserve exact spaces dots Hebrew and value text is not a model lookup', () => {
    const c = context('/when $', {picker: 'products'});
    assert.equal(c.choices[0].insertText, '$"Connection Size.מידה"');
    const value = context('/when $"Connection Size.מידה" = ');
    assert.equal(value.phase, 'text'); assert.equal(value.toolbar.values, false);
});
test('partial lexer stops at an invalid earlier statement and never invents later outcomes', () => {
    const source = '/hide @body\n?\n/allow @bolts #steel';
    const syntax = inspectRuleWritingSyntax(source);
    assert.equal(syntax.statements.length, 1); assert.equal(syntax.statements[0].kind, 'hide');
    assert(!syntax.statements.some(x => x.kind === 'allow'));
});
test('CRLF source spans and editor selections stay distinct around emoji and Hebrew text', () => {
    const source = '// 😀 שלום\r\n/when @body in #steel |';
    const c = context(source);
    assert.equal(c.choices[0].from, source.replaceAll('\r\n', '\n').length);
    assert(c.syntax.spans.some(span => span.type === 'comment'));
});
test('unknown and ambiguous references are issues rather than arbitrary Value choices', () => {
    assert.equal(context('/when @missing in ').choices.length, 0);
    const c = context('/when @body in ', {references: {...references, attributes: [...references.attributes, references.attributes[0]]}});
    assert(c.issues.some(x => x.code === 'reference')); assert.equal(c.choices.length, 0);
});

test('unfinished Attribute and quoted Product conditions offer operators before any operator exists',()=>{
    for(const source of ['/when @body ', '/when $"Connection Size.מידה" ']) {
        const c=context(source);assert.equal(c.phase,'operator');assert(c.choices.some(x=>x.insertText===' in '));assert(c.choices.every(x=>x.from===source.length&&x.to===source.length));
    }
});
test('replacing an existing operator uses its whole token rather than inserting at the caret',()=>{
    const source='/when @body in #steel',caret=source.indexOf(' in ')+2;
    const c=context(source,{editorSelection:[caret,caret]});assert.equal(c.phase,'operator');
    const choice=c.choices.find(x=>x.insertText==='not-in');assert(choice);assert.equal(source.slice(choice.from,choice.to),'in');
});

for(const newline of ['\n','\r\n','\r']) test(`line context preserves ${JSON.stringify(newline)} and Unicode offsets`,()=>{
    const source=['// 😀 שלום','/when @body = #steel','/and @bolts in #steel |'].join(newline);
    const normalized=source.replace(/\r\n|\r/g,'\n');const c=context(source,{editorSelection:[normalized.length,normalized.length]});
    assert.equal(c.attributeKey,'bolts');assert.deepEqual(c.choices.map(x=>x.insertText),['#ss316']);assert.equal(c.choices[0].from,normalized.length);assert.equal(c.activeByte,new TextEncoder().encode(source).length);
});
test('completed quoted and bracket braces cannot open or close structural groups',()=>{
    for(const source of ['/set-hint @body "{"','/set-hint @body [[{]]']) {const c=context(source,{kind:'Advanced'});assert.equal(c.groupDepth,0);assert.equal(c.toolbar.closeGroup,false);}
    for(const value of ['"}"','[[}]]']) {const c=context(`/when all {\n $Connection = ${value}\n `);assert.equal(c.groupDepth,1);assert.equal(c.toolbar.closeGroup,true);}
    assert.equal(context('/when all {\n @body = #iron\n}\n').groupDepth,0);
});

test('initial and grouped command menus only offer structures the current grammar can continue',()=>{
    const initial=context('',{picker:'commands'});assert.deepEqual(initial.choices.map(x=>x.insertText),['/when ','/pair ']);
    const grouped=context('/when all {\n ',{picker:'commands'});assert(grouped.choices.every(x=>!x.insertText.startsWith('/pair')&&!x.insertText.startsWith('/when')));
    const paired=context('/pair @body -> @bolts\n',{picker:'commands'});assert(paired.choices.some(x=>x.insertText==='/case '));assert(!paired.choices.some(x=>x.insertText==='/pair '));
});
