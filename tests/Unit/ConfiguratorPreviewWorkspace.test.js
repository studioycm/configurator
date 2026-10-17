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
