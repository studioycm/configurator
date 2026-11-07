import test from 'node:test';
import assert from 'node:assert/strict';
import { ruleErrorNavigation } from '../../resources/js/rule-error-navigation.js';

test('navigation expands nested containers then focuses the visible control in its own component', () => {
    const operations = [];
    const root = {};
    const group = { parentElement: root, dispatchEvent: event => operations.push(['group', event.type]) };
    const row = { parentElement: group, dispatchEvent: event => operations.push(['row', event.type]) };
    const hidden = { matches: () => true, disabled: false, hidden: false, getClientRects: () => [], focus: () => assert.fail('hidden control focused') };
    const control = { matches: () => true, disabled: false, hidden: false, getClientRects: () => [1], focus: options => operations.push(['focus', options.preventScroll]) };
    const field = { closest: () => null, id: 'actual-field', parentElement: row, matches: () => false, querySelectorAll: () => [hidden, control], scrollIntoView: options => operations.push(['scroll', options.block]) };
    root.querySelectorAll = () => [field];
    const navigation = ruleErrorNavigation('actual-field');
    navigation.$el = { closest: selector => { assert.equal(selector, '[wire\\:id]'); return root; } };
    let nextTick;
    navigation.$nextTick = callback => { nextTick = callback; };

    navigation.reveal();
    assert.deepEqual(operations, [['row', 'expand'], ['group', 'expand']]);
    nextTick();
    assert.deepEqual(operations, [['row', 'expand'], ['group', 'expand'], ['scroll', 'center'], ['focus', true]]);
});

test('a removed target does not navigate to a different component or run an action', () => {
    const navigation = ruleErrorNavigation('removed-field');
    navigation.$el = { closest: () => ({ querySelectorAll: () => [] }) };
    navigation.$nextTick = () => assert.fail('missing target scheduled focus');
    assert.doesNotThrow(() => navigation.reveal());
});

test('button groups and custom fields resolve through their rendered labels and focus a real control', () => {
    for (const kind of ['group', 'custom']) {
        let focused = false;
        const control = { matches: () => true, disabled: false, hidden: false, getClientRects: () => [1], focus: () => { focused = true; } };
        const root = {};
        const wrapper = { parentElement: root, matches: () => false, querySelectorAll: () => [control], scrollIntoView() {} };
        const anchor = { matches: () => false, id: kind === 'group' ? 'logical-field-label' : '', getAttribute: name => name === 'for' && kind === 'custom' ? 'logical-field' : null, closest: () => wrapper };
        root.querySelectorAll = () => [anchor];
        const navigation = ruleErrorNavigation('logical-field');
        navigation.$el = { closest: () => root };
        navigation.$nextTick = callback => callback();

        navigation.reveal();
        assert.equal(focused, true, kind);
    }
});

test('navigation prefers the input over wrapper tabindex and Help actions', () => {
    let focused = '';
    const control = { matches: () => true, getClientRects: () => [1], focus: () => { focused = 'input'; } };
    const help = { matches: () => true, getClientRects: () => [1], focus: () => { focused = 'help'; } };
    const root = {};
    const wrapper = { parentElement: root, matches: () => true, getClientRects: () => [1], focus: () => { focused = 'wrapper'; }, scrollIntoView() {}, querySelectorAll: selector => selector === 'input, select, textarea' ? [control] : selector === 'button, [tabindex]' ? [help] : [] };
    const label = { id: 'field-label', matches: () => false, closest: () => wrapper };
    root.querySelectorAll = () => [label];
    const navigation = ruleErrorNavigation('field');
    navigation.$el = { closest: () => root };
    navigation.$nextTick = callback => callback();

    navigation.reveal();
    assert.equal(focused, 'input');
});
