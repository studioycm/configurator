import test from 'node:test';
import assert from 'node:assert/strict';
import { collectConfiguratorEditors, configuratorPreviewWorkspace } from '../../resources/js/configurator-preview-workspace.js';

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
