import test from 'node:test';
import assert from 'node:assert/strict';
import { workspaceSelectionScope, batchPreviewGuard } from '../../resources/js/admin-batch-workflow.js';

const dispatchScope = () => new Promise(resolve => setTimeout(resolve, 135));
test('selection counts reject obsolete search replies and clear immediately with the selection', async () => {
    const ui = workspaceSelectionScope(), calls = [];
    ui.$wire = { workspaceSelectionScope: ids => new Promise(resolve => calls.push({ids, resolve})) };
    ui.refreshSelectionScope(['1','2'], 'Copper'); await dispatchScope();
    ui.refreshSelectionScope(['1','2'], 'Steel'); await dispatchScope();
    calls[0].resolve({total:2,inside:2,outside:0}); await Promise.resolve();
    assert.equal(ui.selectionScope,null); assert.equal(ui.selectionScopeBusy,true);
    calls[1].resolve({total:2,inside:1,outside:1}); await Promise.resolve();
    assert.deepEqual(ui.selectionScope,{total:2,inside:1,outside:1});
    ui.refreshSelectionScope([], 'Steel'); assert.equal(ui.selectionScope,null); assert.equal(ui.selectionScopeBusy,false);
    ui.destroy();
});

test('disposed summaries cannot repaint and failed counts never claim zero hidden selections', async () => {
    const ui = workspaceSelectionScope();
    ui.$wire = { workspaceSelectionScope: async () => { throw new Error('Offline'); } };
    ui.refreshSelectionScope(['1'], ''); await dispatchScope();
    assert.equal(ui.selectionScope,null); assert.equal(ui.selectionScopeFailed,true);
    ui.destroy(); ui.refreshSelectionScope(['2'], 'changed');
    assert.equal(ui.selectionScope,null);
});

test('deferred input disables the preview locally before a server update; only a fresh preview restores it', () => {
    const callbacks = new Map(), ui = batchPreviewGuard({index:0});
    ui.$wire = {batchPreview:{token:'same-token',ui_revision:'first',form_state:{replacement:'Reviewed'},selected_ids:['1']},batchPreviewStale:false,mountedActions:[{data:{replacement:'Reviewed'}}],selectedTableRecords:['1']};
    ui.$watch = (expression, callback) => callbacks.set(expression, callback);
    ui.init(); ui.captureBatchEdit({target:{matches:()=>true}});
    assert.equal(ui.batchIsOutdated,true); assert.equal(ui.$wire.batchPreviewStale,false);
    ui.$wire.batchPreview.ui_revision = 'second'; callbacks.get('$wire.batchPreview.ui_revision')('second');
    assert.equal(ui.batchIsOutdated,false);
    const changed = [...callbacks.entries()].find(([expression]) => typeof expression === 'function')[1];
    changed(); assert.equal(ui.batchIsOutdated,true);
    callbacks.get('$wire.batchPreviewStale')(true); assert.equal(ui.batchIsOutdated,true);
});


test('a Preview reply cannot re-enable Apply if newer deferred text was entered while it was in flight', () => {
    const callbacks = new Map(), ui = batchPreviewGuard({index:0});
    ui.$wire = {batchPreview:{token:'old',ui_revision:'old',form_state:{replacement:'A'},selected_ids:['1']},batchPreviewStale:false,mountedActions:[{data:{replacement:'A'}}],selectedTableRecords:['1']};
    ui.$watch = (expression, callback) => callbacks.set(expression, callback); ui.init();
    ui.$wire.mountedActions[0].data.replacement = 'B'; ui.captureBatchEdit({target:{matches:()=>true}});
    ui.$wire.batchPreview = {token:'reviewed-A',ui_revision:'new',form_state:{replacement:'A'},selected_ids:['1']};
    callbacks.get('$wire.batchPreview.ui_revision')('new'); assert.equal(ui.batchIsOutdated,true);
    ui.$wire.batchPreview = {token:'reviewed-B',ui_revision:'newer',form_state:{replacement:'B'},selected_ids:['1']};
    callbacks.get('$wire.batchPreview.ui_revision')('newer'); assert.equal(ui.batchIsOutdated,false);
});
