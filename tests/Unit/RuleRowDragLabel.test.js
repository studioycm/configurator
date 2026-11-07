import test from 'node:test';
import assert from 'node:assert/strict';
import { ruleRowDragLabel } from '../../resources/js/rule-row-drag-label.js';

test('drag names follow heading morphs and native attribute resets and disconnect on removal', () => {
    let heading = 'Nested condition 2 · Incomplete condition';
    const attributes = new Map();
    const mutations = [];
    let notify, disconnected = false;
    const header = { querySelector: () => ({ textContent: heading }) };
    const component = ruleRowDragLabel({ template: 'Move :name', fallback: 'Rule row' }, { Observer: class {
        constructor(callback) { notify = callback; }
        observe(element, options) { assert.equal(element, header); assert.equal(options.subtree, true); }
        disconnect() { disconnected = true; }
    } });
    component.$nextTick = callback => callback();
    component.$el = { closest: () => header, getAttribute: name => attributes.get(name), setAttribute: (name, value) => { attributes.set(name, value); mutations.push(name); } };

    component.init();
    assert.equal(attributes.get('aria-label'), 'Move Nested condition 2 · Incomplete condition');
    heading = 'Nested condition 2 · Product property · Pressure · Contains';
    notify();
    assert.equal(attributes.get('aria-label'), `Move ${heading}`);
    assert.equal(attributes.get('title'), `Move ${heading}`);
    attributes.set('aria-label', 'Move Rule row');
    notify();
    assert.equal(attributes.get('aria-label'), `Move ${heading}`);
    const count = mutations.length;
    notify();
    assert.equal(mutations.length, count);
    heading = 'Nested condition 1 · Product property · Pressure · Contains';
    notify();
    assert.equal(attributes.get('title'), `Move ${heading}`);
    component.destroy();
    assert.equal(disconnected, true);
});

test('an absent header does not install an observer', () => {
    const component = ruleRowDragLabel({ template: 'Move :name', fallback: 'Rule row' }, { Observer: class { constructor() { assert.fail('observer without a header'); } } });
    component.$el = { closest: () => null };
    component.$nextTick = callback => callback();
    component.init();
    assert.doesNotThrow(() => component.destroy());
});
