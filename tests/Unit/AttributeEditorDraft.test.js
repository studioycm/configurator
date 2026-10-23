import test from 'node:test';
import assert from 'node:assert/strict';
import { attributeEditorDraft } from '../../resources/js/attribute-editor-draft.js';

const clean = () => ({ label: 'Exact Label ', key: 'exact_key', pending_options: [], master_values: [], _master_composer: [], _selected_value_ids: [], _selected_pending: [] });
const blank = () => ({ ref: 'new:generated', label: '', semantic_id: null, tags: [], description: ' ', _suggested_semantic_id: true, _identifier_semantic_id_intent: 'intent', _identifier_semantic_id_candidate: null });

test('opening, switching disclosure and generated blank composers leave the comparison clean without mutating raw state', () => {
    const before = clean();
    const opened = { ...before, _workspace_open: true, _active_owner: 'attribute:1', _workspace_owner: 'new:owner', _master_composer: { 'native-uuid': blank() } };
    const raw = structuredClone(opened);
    assert.deepEqual(attributeEditorDraft(opened), attributeEditorDraft(before));
    assert.deepEqual(opened, raw);
});

for (const [name, change] of [
    ['label exact whitespace', state => { state.label = 'Exact Label'; }],
    ['key clear', state => { state.key = ''; }],
    ['existing selection', state => { state._selected_value_ids = [19]; }],
    ['pending selection', state => { state._selected_pending = ['new:pending']; }],
    ['unfinished composer with a manual clear', state => { state._master_composer = { uuid: { ...blank(), label: 'Retained', semantic_id: '' } }; }],
    ['zero tag', state => { state._master_composer = { uuid: { ...blank(), tags: ['0'] } }; }],
    ['description only', state => { state._master_composer = { uuid: { ...blank(), description: 'unfinished' } }; }],
    ['pending master pool', state => { state.master_values = { uuid: { ...blank(), label: 'Retained' } }; }],
    ['pending code clear', state => { state.pending_options = { uuid: { ref: 'new:association', value_id: 19, code: '' } }; }],
]) {
    test(`${name} remains meaningful`, () => {
        const state = clean();
        change(state);
        assert.notDeepEqual(attributeEditorDraft(state), attributeEditorDraft(clean()));
    });
}

test('exact native keys, refs, pending references and Codes survive while derived names and suggestion helpers do not', () => {
    const state = { ...clean(), pending_options: { 'association-uuid': { ref: 'new:association', value_ref: 'new:master', code: ' A', name: 'Derived' } }, master_values: { 'master-uuid': { ...blank(), ref: 'new:master', label: 'Name', semantic_id: 'manual' } } };
    const projected = attributeEditorDraft(state);
    assert.deepEqual(projected.pending_options, [['association-uuid', { ref: 'new:association', value_id: null, value_ref: 'new:master', code: ' A' }]]);
    assert.deepEqual(projected.master_values, [['master-uuid', { ref: 'new:master', label: 'Name', semantic_id: 'manual', tags: [], description: ' ' }]]);
    state.pending_options['association-uuid'].name = 'Refreshed display';
    state.master_values['master-uuid']._identifier_semantic_id_intent = 'new intent';
    assert.deepEqual(attributeEditorDraft(state), projected);
    state.master_values.replacement = state.master_values['master-uuid'];
    delete state.master_values['master-uuid'];
    assert.notDeepEqual(attributeEditorDraft(state), projected);
});

test('clearing meaningful work back to an empty composer restores the comparison baseline', () => {
    const state = clean();
    state._master_composer = { uuid: { ...blank(), label: 'Draft' } };
    assert.notDeepEqual(attributeEditorDraft(state), attributeEditorDraft(clean()));
    state._master_composer.uuid.label = '';
    assert.deepEqual(attributeEditorDraft(state), attributeEditorDraft(clean()));
});

for (const [name, value, meaningful] of [['nonbreaking space', '\u00a0', true], ['EM SPACE', '\u2003', true], ['NUL', '\0', false], ['PHP trim characters', ' \t\n\r\0\v', false]]) {
    test(`composer and pool population match PHP for ${name} without rewriting raw text`, () => {
        const state = clean();
        state._master_composer = { uuid: { ...blank(), description: value } };
        state.master_values = { 'pool-uuid': { ...blank(), tags: [value] } };
        const before = structuredClone(state);
        const projected = attributeEditorDraft(state);
        assert.equal(projected._master_composer.length, meaningful ? 1 : 0);
        assert.equal(projected.master_values.length, meaningful ? 1 : 0);
        if (meaningful) {
            assert.equal(projected._master_composer[0][1].description, value);
            assert.deepEqual(projected.master_values[0][1].tags, [value]);
        }
        assert.deepEqual(state, before);
    });
}
