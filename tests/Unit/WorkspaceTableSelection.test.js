import { test } from 'node:test';
import assert from 'node:assert/strict';
import { workspaceTableSelection } from '../../resources/js/workspace-table-selection.js';

function fixture() {
    let clicks = 0;
    const wrapper = {};
    const checkbox = { disabled: false, matches: () => false, closest: () => null, click: () => clicks++ };
    const row = { closest: selector => selector === '[data-workspace-row-selection]' ? wrapper : null, querySelector: () => checkbox, matches: () => false, getAttribute: () => null };
    const target = { closest: selector => selector === '.fi-ta-row, .fi-ta-record' ? row : null };
    const selection = workspaceTableSelection();
    selection.$el = wrapper;
    return { selection, checkbox, row, target, wrapper, clicks: () => clicks };
}

test('an eligible noninteractive row click invokes its native checkbox exactly once', () => {
    const f = fixture();
    f.selection.toggle({ target: f.target, button: 0, detail: 1 });
    assert.equal(f.clicks(), 1);
    assert.equal(Object.hasOwn(f.selection, 'selectedIds'), false);
});

test('interactive controls, selection gestures, repeated and disabled row events stay native', () => {
    for (const patch of [{ button: 2 }, { detail: 2 }, { defaultPrevented: true }]) {
        const f = fixture();
        f.selection.toggle({ target: f.target, button: 0, detail: 1, ...patch });
        assert.equal(f.clicks(), 0);
    }
    const f = fixture();
    f.checkbox.disabled = true;
    f.selection.toggle({ target: f.target, button: 0, detail: 1 });
    assert.equal(f.clicks(), 0);
    f.checkbox.disabled = false;
    const original = f.target.closest;
    f.target.closest = selector => selector.includes('button') ? {} : original(selector);
    f.selection.toggle({ target: f.target, button: 0, detail: 1 });
    assert.equal(f.clicks(), 0);
});

test('nested unrelated selector rows cannot toggle this wrapper', () => {
    const f = fixture();
    f.row.closest = () => ({});
    f.selection.toggle({ target: f.target, button: 0, detail: 1 });
    assert.equal(f.clicks(), 0);
});

test('a nested unrelated table under the same selector wrapper is excluded', () => {
    const f = fixture();
    const mainTable = {};
    const nestedTable = {};
    f.wrapper.querySelector = () => mainTable;
    f.row.closest = selector => selector === '[data-workspace-row-selection]' ? f.wrapper : nestedTable;
    f.checkbox.closest = selector => selector === 'table' ? nestedTable : null;
    f.selection.toggle({ target: f.target, button: 0, detail: 1 });
    assert.equal(f.clicks(), 0);
});
