import { test } from 'node:test';
import assert from 'node:assert/strict';
import { editorDrafts, configuratorDrafts } from '../../resources/js/editor-drafts.js';

function controller() {
    const state = editorDrafts();
    state.$nextTick = callback => callback();
    state.$refs = {};
    state.$el = { querySelector: () => ({ dataset: { editorRecord: 'Attribute Value BN' } }) };
    return state;
}

test('keeping a draft cancels the pending transition without invoking it', () => {
    const state = controller();
    let prevented = false;
    let clicked = false;
    state.requestDiscard({ preventDefault() { prevented = true; }, stopImmediatePropagation() {} }, { click() { clicked = true; } });
    assert.equal(prevented, true);
    assert.equal(state.confirmDiscard, true);
    assert.equal(state.discardRecord, 'Attribute Value BN');
    state.keepEditing();
    assert.equal(state.confirmDiscard, false);
    assert.equal(clicked, false);
    state.discardChanges();
    assert.equal(clicked, false);
});

test('a discard request reveals the prompt and focuses the safe choice', () => {
    const state = controller();
    const calls = [];
    state.$refs.discardAlert = {
        scrollIntoView(options) { calls.push(['scroll', options]); },
        querySelector(selector) {
            assert.equal(selector, 'button');
            return { focus(options) { calls.push(['focus', options]); } };
        },
    };
    state.requestDiscard({ preventDefault() {}, stopImmediatePropagation() {} }, null);
    assert.deepEqual(calls, [['scroll', { block: 'nearest' }], ['focus', { preventScroll: true }]]);
});

test('discarding replays only the requested transition with its guard bypassed once', () => {
    const state = controller();
    let count = 0;
    const target = { click() { assert.equal(state.allowTransition, true); count++; } };
    state.requestDiscard({ preventDefault() {}, stopImmediatePropagation() {} }, target);
    state.discardChanges();
    assert.equal(count, 1);
    assert.equal(state.allowTransition, false);
    assert.equal(state.pendingTransition, null);
});

test('drawer close events are delayed until the user chooses to discard', () => {
    const state = controller();
    const dispatched = [];
    state.$dispatch = (name, detail) => dispatched.push([name, detail]);
    state.requestDiscard({ preventDefault() {}, stopImmediatePropagation() {} }, null, 'drawer-7');
    assert.deepEqual(dispatched, []);
    state.discardChanges();
    assert.deepEqual(dispatched, [['close-modal', { id: 'drawer-7' }]]);
});

test('a retained Attribute draft stays dirty after another Attribute is selected', () => {
    const state = configuratorDrafts('attributes');
    state.$wire = {
        selectedAttributeId: 1, editorData: { label_override: 'Draft' },
        previewOriginals: { 'attribute:1': { label_override: null }, 'attribute:2': { label_override: null } },
        editorDrafts: {},
    };
    assert.equal(state.hasDraft(), true);
    state.$wire.editorDrafts[1] = state.$wire.editorData;
    state.$wire.selectedAttributeId = 2;
    state.$wire.editorData = { label_override: null };
    assert.equal(state.hasDraft(), false);
    assert.equal(state.hasPendingDrafts(), true);
    state.$wire.selectedAttributeId = 1;
    state.$wire.editorData = state.$wire.editorDrafts[1];
    assert.equal(state.hasDraft(), true);
    state.$wire.editorData = { label_override: null };
    assert.equal(state.hasDraft(), false);
});

test('retained Rule drafts compare with the saved original for existing and new rules', () => {
    const state = configuratorDrafts('rules');
    state.$wire = {
        selectedRuleId: 7, editorKind: 'Mapping', editorData: { label: 'Draft' },
        previewOriginals: { 'rule:7': { label: 'Saved' }, 'new:Mapping': { label: '' } },
    };
    assert.equal(state.hasDraft(), true);
    state.$wire.previewOriginals['rule:7'] = { label: 'Draft' };
    assert.equal(state.hasDraft(), false);
    state.$wire.selectedRuleId = null;
    state.$wire.editorData = { label: 'New draft' };
    assert.equal(state.hasDraft(), true);
    state.$wire.editorKind = null;
    assert.equal(state.hasDraft(), false);
});

test('a local Value footer distinguishes its own draft from other retained Value drafts', () => {
    const state = configuratorDrafts('attributes');
    state.$wire = {
        selectedOptionId: 11, optionEditorData: { hint: 'Draft hint' },
        previewOriginals: { 'value:11': { hint: null }, 'value:12': { hint: null } },
        optionEditorDrafts: { 1: { 11: { hint: 'Draft hint' } } },
    };
    assert.equal(state.hasCurrentOptionDraft(), true);
    state.$wire.selectedOptionId = 12;
    state.$wire.optionEditorData = { hint: null };
    assert.equal(state.hasCurrentOptionDraft(), false);
    assert.equal(state.hasOptionDraft(), true);
    state.$wire.selectedOptionId = 11;
    state.$wire.optionEditorData = state.$wire.optionEditorDrafts[1][11];
    assert.equal(state.hasCurrentOptionDraft(), true);
});
