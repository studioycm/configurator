import test from 'node:test';
import assert from 'node:assert/strict';
import tagsInput from '../../vendor/filament/forms/resources/js/components/tags-input.js';

function field(state = []) {
    const component = tagsInput({ state: [...state], splitKeys: [] });
    component.$refs = {};
    return component;
}

const authoringVectors = [
    ['space', ' 0 ', '0'],
    ['NBSP', '\u00a0001\u00a0', '001'],
    ['BOM', '\ufeffé Multi Word\ufeff', 'é Multi Word'],
    ['zero-width space', '\u200b0\u200b', '\u200b0\u200b'],
    ['ZWNJ', '\u200c0\u200c', '\u200c0\u200c'],
    ['ZWJ', '\u200d0\u200d', '\u200d0\u200d'],
    ['soft hyphen', '\u00ad0\u00ad', '\u00ad0\u00ad'],
];

for (const [name, input, expected] of authoringVectors) {
    test(`Group authoring ${name} matches the PHP persistence contract`, () => {
        const component = field();
        component.newTag = input;
        component.createTag();
        assert.deepEqual(component.state, [expected]);
        assert.equal(component.newTag, '');
    });
}

test('loaded Group labels retain exact bytes while new entries use Enter and exact duplicate handling', () => {
    const retained = [' 0 ', '001', 'Case', 'case', 'מים Multi Word'];
    const component = field(retained);
    let prevented = false;
    component.newTag = '  Water Supply  ';
    component.input['x-on:keydown'].call(component, {
        key: 'Enter', preventDefault() { prevented = true; }, stopPropagation() {},
    });
    assert.equal(prevented, true);
    assert.deepEqual(component.state, [...retained, 'Water Supply']);
    component.newTag = ' Water Supply ';
    component.createTag();
    assert.deepEqual(component.state, [...retained, 'Water Supply']);
    component.deleteTag(' 0 ');
    component.newTag = ' 0 ';
    const commitOnBlur = new Function('createTag', component.input['x-on:blur']);
    commitOnBlur(component.createTag.bind(component));
    assert.deepEqual(component.state, ['001', 'Case', 'case', 'מים Multi Word', 'Water Supply', '0']);
});

test('non-ECMAScript invisible labels stay distinct from the literal Group value zero', () => {
    const component = field(['0']);
    for (const [, input] of authoringVectors.slice(3)) {
        component.newTag = input;
        component.createTag();
    }
    assert.deepEqual(component.state, ['0', '\u200b0\u200b', '\u200c0\u200c', '\u200d0\u200d', '\u00ad0\u00ad']);
});
