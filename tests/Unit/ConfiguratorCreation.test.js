import { test } from 'node:test';
import assert from 'node:assert/strict';
import { configuratorCreationIsDirty } from '../../resources/js/configurator-creation.js';

test('generated refs and blank canonical rows leave an empty Configurator workspace clean', () => {
    assert.equal(configuratorCreationIsDirty({ attributes: { a: { ref: 'new:a', label: ' ', key: '', values: [], _master_composer: { b: { ref: 'new:b', label: '', semantic_id: '', tags: [] } } } } }), false);
    assert.equal(configuratorCreationIsDirty({ _active_owner: 'inclusion:1', new_values: [], _master_composer: { a: { ref: 'new:a', label: '', semantic_id: '' } } }, 'values'), false);
});

test('retained review stashes and unstaged selections are meaningful dirty state', () => {
    assert.equal(configuratorCreationIsDirty({ _review_stash: { 'id:7': { review: { values: [{ local_fields: { hint: 'Keep' } }] } } } }), true);
    assert.equal(configuratorCreationIsDirty({ _selected_value_ids: ['7'] }, 'values'), true);
    assert.equal(configuratorCreationIsDirty({ existing_attribute_ids: ['7'] }), true);
    assert.equal(configuratorCreationIsDirty({ new_values: { row: { ref: 'new:1', value_ref: 'new:master', code: '' } } }, 'values'), true);
});
