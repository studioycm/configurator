import test from 'node:test';
import assert from 'node:assert/strict';
import { collectConfiguratorEditors, configuratorPreviewWorkspace, navigateConfiguratorEditor } from '../../resources/js/configurator-preview-workspace.js';

test('collection asks every mounted editor to synchronize its pending values', async () => {
    const asked = [];
    const roots = ['attributes', 'rules', 'overview'].map(id => ({ closest: () => ({ getAttribute: () => id }) }));
    const workspace = { querySelectorAll: () => roots };
    const captures = await collectConfiguratorEditors(workspace, id => ({ exportPreviewChanges: async () => { asked.push(id); return { rows: [id] }; } }));
    assert.deepEqual(asked, ['attributes', 'rules', 'overview']);
    assert.deepEqual(captures.map(capture => capture.rows[0]), asked);
});

test('entering Preview automatically tests unsaved values while explicit mode switches use the chosen source', async () => {
    const preview = configuratorPreviewWorkspace(5);
    const calls = [];
    preview.collect = async () => [{ rows: [{ after: 'latest editor value' }] }];
    preview.$wire = { previewSource: 'saved', rebuildPreview: async (...args) => calls.push(args) };
    await preview.rebuild(undefined, true);
    await preview.rebuild('saved');
    assert.equal(calls[0][1], 'unsaved');
    assert.equal(calls[1][1], 'saved');
});

test('Save gathers current editor values again and never confirms a partial collection', async () => {
    const preview = configuratorPreviewWorkspace(5);
    const calls = [];
    preview.collect = async () => [{ rows: ['fresh'] }];
    preview.$wire = { reviewChanges: async captures => calls.push(captures) };
    await preview.review();
    preview.collect = async () => { throw new Error('Editor unavailable'); };
    await preview.review();
    assert.deepEqual(calls, [[{ rows: ['fresh'] }]]);
    assert.equal(preview.collectionFailed, true);
    assert.equal(preview.collecting, false);
});

test('duplicate visible-tab notifications do not rebuild or invalidate an open review', () => {
    const preview = configuratorPreviewWorkspace(5);
    let builds = 0;
    preview.rebuild = () => { builds++; };
    preview.visibility({ configuratorId: 5, active: true });
    preview.visibility({ configuratorId: 5, active: true });
    assert.equal(builds, 1);
    preview.visibility({ configuratorId: 5, active: false });
    preview.visibility({ configuratorId: 5, active: true });
    assert.equal(builds, 2);
});

test('leaving and returning during collection retries the active preview with current values', async () => {
    const preview = configuratorPreviewWorkspace(5);
    let finish;
    let collections = 0;
    const calls = [];
    preview.collect = async () => {
        if (++collections === 1) await new Promise(resolve => { finish = resolve; });
        return [{ rows: ['current'] }];
    };
    preview.$wire = { rebuildPreview: async (...args) => calls.push(args) };
    preview.active = true;
    const first = preview.rebuild(undefined, true);
    preview.visibility({ configuratorId: 5, active: false });
    preview.visibility({ configuratorId: 5, active: true });
    finish();
    await first;
    assert.equal(collections, 2);
    assert.equal(calls.length, 1);
    assert.equal(calls[0][1], 'unsaved');
});

test('returning during Save collection refreshes current values without opening a stale confirmation', async () => {
    const preview = configuratorPreviewWorkspace(5);
    let finish;
    let collections = 0;
    const reviews = [];
    const builds = [];
    preview.collect = async () => {
        if (++collections === 1) {
            await new Promise(resolve => { finish = resolve; });
            return [{ rows: ['earlier values'] }];
        }
        return [{ rows: ['latest values'] }];
    };
    preview.$wire = {
        reviewChanges: async captures => reviews.push(captures),
        rebuildPreview: async (...args) => builds.push(args),
    };
    preview.active = true;
    const review = preview.review();
    preview.visibility({ configuratorId: 5, active: false });
    preview.visibility({ configuratorId: 5, active: true });
    finish();
    await review;

    assert.deepEqual(reviews, []);
    assert.deepEqual(builds, [[[{ rows: ['latest values'] }], 'unsaved']]);
    assert.equal(preview.collecting, false);
    assert.equal(preview.pendingAutomatic, false);
});

function navigationWorkspace(editor = 'rules', mounted = true) {
    const calls = [];
    const workspace = new EventTarget();
    const panel = { classList: { contains: () => true }, focus: () => calls.push('focus') };
    const element = new EventTarget();
    element.closest = selector => selector === '[wire\\:id]' ? { getAttribute: () => 'editor-id' } : panel;
    element.querySelector = () => panel;
    workspace.querySelector = selector => selector.includes('editor-tab=') ? { getAttribute: () => 'target-tab' } : (mounted ? element : null);
    workspace.addEventListener('configurator-select-editor-tab', () => calls.push('tab'));
    const find = () => ({ openPreviewSource: async (...args) => { calls.push(args); return true; } });
    return { workspace, element, calls, find, mount: () => { mounted = true; workspace.dispatchEvent(new CustomEvent('configurator-preview-editor-ready', { detail: { editor } })); } };
}

test('conflict links open the exact Rule or Value through the existing mounted editor', async () => {
    for (const target of [{ type: 'rule', id: 'new:pending-rule' }, { type: 'value', attribute_id: '25', id: '76' }]) {
        const fixture = navigationWorkspace();
        await navigateConfiguratorEditor(fixture.workspace, target, fixture.find, async () => {});
        assert.deepEqual(fixture.calls, ['tab', target.type === 'rule' ? [target.id] : [25, 76], 'focus']);
    }
});

test('conflict links wait for lazy Rule and Value editors before selecting their sources', async () => {
    for (const target of [{ type: 'rule', id: '13' }, { type: 'value', attribute_id: '25', id: '76' }]) {
        const fixture = navigationWorkspace(target.type === 'rule' ? 'rules' : 'attributes', false);
        const navigation = navigateConfiguratorEditor(fixture.workspace, target, fixture.find, async () => {});
        assert.deepEqual(fixture.calls, ['tab']);
        fixture.mount();
        await navigation;
        assert.deepEqual(fixture.calls, ['tab', target.type === 'rule' ? ['13'] : [25, 76], 'focus']);
    }
});

test('manual navigation cancels a pending lazy conflict link without selecting or focusing an editor', async () => {
    const fixture = navigationWorkspace('rules', false);
    const navigation = navigateConfiguratorEditor(fixture.workspace, { type: 'rule', id: '13' }, fixture.find, async () => {});
    fixture.workspace.dispatchEvent(new CustomEvent('configurator-preview-visibility', { detail: { tab: 'overview-tab' } }));
    await navigation;
    fixture.mount();
    assert.deepEqual(fixture.calls, ['tab']);
});

test('conflict navigation flushes pending editor values before leaving Preview and ignores double clicks', async () => {
    const preview = configuratorPreviewWorkspace(5);
    const calls = [];
    let finish;
    preview.collect = async () => { calls.push('collect'); await new Promise(resolve => { finish = resolve; }); return []; };
    preview.navigate = async target => calls.push(target);
    const target = { type: 'rule', id: '13' };
    const first = preview.openSource(target);
    await preview.openSource(target);
    assert.deepEqual(calls, ['collect']);
    finish();
    await first;
    assert.deepEqual(calls, ['collect', target]);
    assert.equal(preview.collecting, false);
});

test('leaving Preview during the flush cancels a conflict jump and retains editor collection errors', async () => {
    const preview = configuratorPreviewWorkspace(5);
    let finish;
    const calls = [];
    preview.active = true;
    preview.collect = async () => new Promise(resolve => { finish = resolve; });
    preview.navigate = async target => calls.push(target);
    const first = preview.openSource({ type: 'rule', id: '13' });
    preview.visibility({ configuratorId: 5, active: false });
    finish([]);
    await first;
    assert.deepEqual(calls, []);
    preview.collect = async () => { throw new Error('Editor unavailable'); };
    await preview.openSource({ type: 'rule', id: '13' });
    assert.equal(preview.collectionFailed, true);
    assert.equal(preview.collecting, false);
});

test('an unavailable source reports a recoverable navigation error without losing the active editors', async () => {
    const preview = configuratorPreviewWorkspace(5);
    let notices = 0;
    preview.collect = async () => [];
    preview.navigate = async () => { throw new Error('Target removed'); };
    preview.$wire = { notifyNavigationFailure: async () => { notices++; } };
    await preview.openSource({ type: 'rule', id: '13' });
    assert.equal(notices, 1);
    assert.equal(preview.navigationFailed, true);
    assert.equal(preview.collecting, false);
});

test('returning to Preview while an editor is opening rebuilds current captures after navigation settles', async () => {
    const preview = configuratorPreviewWorkspace(5);
    let finish;
    const builds = [];
    preview.active = true;
    preview.collect = async () => [{ rows: ['latest'] }];
    preview.navigate = async () => {
        preview.visibility({ configuratorId: 5, active: false });
        await new Promise(resolve => { finish = resolve; });
    };
    preview.$wire = { rebuildPreview: async (...args) => builds.push(args) };
    const first = preview.openSource({ type: 'rule', id: '13' });
    await Promise.resolve();
    preview.visibility({ configuratorId: 5, active: true });
    finish();
    await first;
    assert.deepEqual(builds, [[[{ rows: ['latest'] }], 'unsaved']]);
});

test('direct Save collects once and uses its committed receipt before refreshing inventory', async () => {
    const preview = configuratorPreviewWorkspace(5);
    const calls = [];
    preview.collect = async () => { calls.push('collect'); return [{ rows: ['latest'] }]; };
    preview.editorIds = () => [];
    preview.$wire = {
        saveChanges: async captures => { calls.push(['write', captures]); return { status: 'committed', receipt: { operation_id: 'op', editor_ids: [], captures, newRules: {} } }; },
        finishPreviewSave: async id => calls.push(['finish', id]),
        refreshEditorInventory: async captures => calls.push(['inventory', captures]),
    };
    await preview.save();
    assert.deepEqual(calls, ['collect', ['write', [{ rows: ['latest'] }]], ['finish', 'op'], 'collect', ['inventory', [{ rows: ['latest'] }]]]);
    assert.equal(preview.phase, 'idle');
});

test('missing reconciliation blocks a repeated Save and retries only editor refresh', async () => {
    const preview = configuratorPreviewWorkspace(5);
    let writes = 0;
    const reconciliations = [];
    preview.collect = async () => [{ rows: ['later editor text'] }];
    preview.editorIds = () => ['rules'];
    preview.waitForAcknowledgments = async () => { throw new Error('Editor response missing'); };
    preview.findEditor = id => ({ acceptPreviewSave: async (...args) => reconciliations.push([id, ...args]) });
    preview.$wire = {
        saveChanges: async () => { writes++; return { status: 'committed', receipt: { operation_id: 'op', editor_ids: ['rules'], captures: [{ rows: ['committed text'] }], newRules: { 'new:1': { id: '7' } } } }; },
        finishPreviewSave: async () => {}, refreshEditorInventory: async () => {},
    };
    await preview.save();
    assert.equal(preview.phase, 'reconcile_failed');
    await preview.save();
    assert.equal(writes, 1);
    await preview.retryReconciliation();
    assert.equal(writes, 1);
    assert.deepEqual(reconciliations, [['rules', 5, [{ rows: ['committed text'] }], { 'new:1': { id: '7' } }, 'op']]);
    assert.equal(preview.phase, 'idle');
});

test('Save waits for an admitted Product change and blocks competing Product admission during collection', async () => {
    const preview = configuratorPreviewWorkspace(5);
    const calls = [];
    let finish;
    preview.collect = async () => { calls.push('collect'); return []; };
    preview.editorIds = () => [];
    preview.$wire = { saveChanges: async () => { calls.push('write'); return { status: 'rejected' }; } };
    const product = preview.request(async () => { calls.push('product'); await new Promise(resolve => { finish = resolve; }); });
    await Promise.resolve();
    const save = preview.save();
    assert.deepEqual(calls, ['product']);
    finish();
    await product;
    await save;
    assert.deepEqual(calls, ['product', 'collect', 'write']);

    preview.collecting = true;
    await preview.request(async () => calls.push('forbidden product'));
    assert.equal(calls.includes('forbidden product'), false);
});

function receiptWorkspace(editorIds = ['rules']) {
    const preview = configuratorPreviewWorkspace(5);
    const events = new EventTarget();
    const calls = [];
    preview.eventTarget = events;
    preview.$nextTick = () => {};
    preview.collect = async () => { calls.push('collect'); return [{ rows: ['current'] }]; };
    preview.editorIds = () => editorIds;
    preview.$wire = {
        $id: 'preview-id',
        saveChanges: async captures => { calls.push('write'); return { status: 'committed', receipt: { operation_id: 'operation', editor_ids: editorIds, captures, newRules: { 'new:rule': { id: '9' } } } }; },
        finishPreviewSave: async () => calls.push('finish'),
        refreshEditorInventory: async () => calls.push('inventory'),
    };
    preview.init();
    const acknowledge = detail => events.dispatchEvent(new CustomEvent('configurator-preview-editor-reconciled', { detail: { configuratorId: 5, operationId: 'operation', componentId: 'rules', ...detail } }));
    return { preview, calls, events, acknowledge };
}

async function settlePromises() {
    for (let index = 0; index < 8; index++) await Promise.resolve();
}

test('Save retains the barrier until every scoped editor acknowledgment and ignores double clicks and tab departure after submission', async () => {
    const { preview, calls, acknowledge } = receiptWorkspace(['rules', 'attributes']);
    preview.active = true;
    const saving = preview.save();
    await settlePromises();
    assert.equal(preview.phase, 'reconciling');
    await preview.save();
    preview.visibility({ configuratorId: 5, active: false });
    preview.visibility({ configuratorId: 5, active: true });
    acknowledge({ configuratorId: 99 });
    acknowledge({ operationId: 'older-operation' });
    acknowledge({ componentId: 'unrelated' });
    acknowledge({});
    await settlePromises();
    assert.deepEqual(calls, ['collect', 'write']);
    acknowledge({ componentId: 'attributes' });
    await saving;
    assert.equal(calls.filter(call => call === 'write').length, 1);
    assert.ok(calls.indexOf('finish') < calls.indexOf('inventory'));
    assert.equal(preview.phase, 'idle');
    preview.destroy();
});

test('a lost initial acknowledgment retries only pending mounted editors and recollects later text once', async () => {
    const { preview, calls, acknowledge } = receiptWorkspace(['rules', 'attributes']);
    preview.acknowledgmentTimeoutMs = 1;
    preview.findEditor = id => ({ acceptPreviewSave: async (configuratorId, captures, map, operationId) => {
        calls.push(['retry', id, configuratorId, captures, map, operationId]);
    } });
    const saving = preview.save();
    await settlePromises();
    acknowledge({ componentId: 'attributes' });
    await saving;
    assert.equal(preview.phase, 'reconcile_failed');
    assert.deepEqual(calls, ['collect', 'write']);
    await preview.save();
    await preview.retryReconciliation();
    assert.equal(calls.filter(call => call === 'write').length, 1);
    assert.deepEqual(calls.filter(Array.isArray).map(call => call.slice(0, 3)), [['retry', 'rules', 5]]);
    assert.equal(calls.filter(call => call === 'collect').length, 2);
    assert.equal(preview.phase, 'idle');
    preview.destroy();
});

test('an editor removed after collection leaves reconciliation blocked without a second write', async () => {
    const { preview, calls } = receiptWorkspace();
    preview.waitForAcknowledgments = async () => { throw new Error('Missing editor'); };
    preview.findEditor = () => null;
    await preview.save();
    await preview.retryReconciliation();
    await preview.save();
    assert.equal(preview.phase, 'reconcile_failed');
    assert.deepEqual(calls, ['collect', 'write']);
    preview.destroy();
});

test('a rejected export or leaving Preview before submission never invokes the writer', async () => {
    const { preview, calls } = receiptWorkspace([]);
    preview.collect = async () => { throw new Error('Export unavailable'); };
    await preview.save();
    assert.equal(preview.collectionFailed, true);
    assert.equal(calls.includes('write'), false);
    let finish;
    preview.active = true;
    preview.collect = async () => new Promise(resolve => { finish = resolve; });
    const saving = preview.save();
    preview.visibility({ configuratorId: 5, active: false });
    finish([]);
    await saving;
    assert.equal(calls.includes('write'), false);
    preview.destroy();
});

test('ambiguous submitted transport failure holds outcome_unknown and never retries the writer automatically', async () => {
    const { preview, calls } = receiptWorkspace([]);
    preview.$wire.saveChanges = async () => { calls.push('write'); throw new Error('Response lost'); };
    await preview.save();
    await preview.save();
    preview.visibility({ configuratorId: 5, active: true });
    await settlePromises();
    assert.equal(preview.phase, 'outcome_unknown');
    assert.deepEqual(calls, ['collect', 'write']);
    preview.destroy();
});

test('collection blocks competing source navigation inspection and Value requests', async () => {
    const { preview, calls } = receiptWorkspace([]);
    let finish;
    preview.collect = async () => new Promise(resolve => { finish = resolve; });
    preview.$wire.reviewChanges = async () => calls.push('review');
    preview.$wire.inspectState = async () => calls.push('inspect');
    preview.$wire.clearInspection = async () => calls.push('clear');
    preview.$wire.selectOption = async () => calls.push('value');
    preview.$wire.mountAction = async () => calls.push('notices');
    const review = preview.review();
    await preview.rebuild('saved');
    await preview.request(async () => calls.push('product'));
    await preview.inspect('1');
    await preview.clearInspection();
    await preview.selectOption('1', '2');
    await preview.notices();
    await preview.openSource({ type: 'rule', id: '1' });
    assert.deepEqual(calls, []);
    finish([]);
    await review;
    assert.deepEqual(calls, ['review']);
    preview.destroy();
});

test('native modal close defers its server action through collection and unregisters on destroy', async () => {
    const preview = configuratorPreviewWorkspace(5);
    const calls = [];
    let interceptor;
    let finish;
    preview.$nextTick = () => {};
    preview.eventTarget = new EventTarget();
    preview.$wire = { $intercept: (name, callback) => { assert.equal(name, 'unmountAction'); interceptor = callback; return () => calls.push('cleanup'); }, reviewChanges: async () => calls.push('review') };
    preview.collect = async () => new Promise(resolve => { finish = resolve; });
    preview.init();
    const review = preview.review();
    let finishClose;
    const action = { defer: () => calls.push('defer'), fire: () => calls.push('close'), promise: new Promise(resolve => { finishClose = resolve; }) };
    interceptor({ action });
    assert.deepEqual(calls, ['defer']);
    finish([]);
    await settlePromises();
    assert.deepEqual(calls, ['defer', 'review', 'close']);
    assert.equal(preview.phase, 'request');
    await preview.request(async () => calls.push('competing request'));
    assert.equal(calls.includes('competing request'), false);
    finishClose();
    await review;
    assert.equal(preview.phase, 'idle');
    assert.deepEqual(preview.deferredUnmounts, []);
    preview.destroy();
    assert.equal(calls.at(-1), 'cleanup');
});

test('mounted editor identities use their actual wire:id owner and deduplicate participating components', () => {
    const preview = configuratorPreviewWorkspace(5);
    const editor = { closest: selector => selector === '[wire\\:id]' ? { getAttribute: () => 'rules-id' } : null };
    preview.$el = { closest: () => ({ querySelectorAll: () => [editor, editor] }) };
    assert.deepEqual(preview.editorIds(), ['rules-id']);
});

test('teleported Save requests match the active owning component and remove their listener on destruction', () => {
    const { preview, events } = receiptWorkspace([]);
    let saves = 0;
    preview.active = true;
    preview.save = () => { saves++; };
    const dispatch = detail => events.dispatchEvent(new CustomEvent('configurator-preview-save-request', { detail }));
    dispatch({ configuratorId: 99, componentId: 'preview-id' });
    dispatch({ configuratorId: 5, componentId: 'another-preview' });
    dispatch({ configuratorId: 5, componentId: 'preview-id' });
    assert.equal(saves, 1);
    preview.destroy();
    dispatch({ configuratorId: 5, componentId: 'preview-id' });
    assert.equal(saves, 1);
});
