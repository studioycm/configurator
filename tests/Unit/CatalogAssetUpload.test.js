import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createCatalogAssetUpload } from '../../resources/js/catalog-asset-upload.js';
import { createAssetAssignmentController, catalogAssetAssignment } from '../../resources/js/catalog-asset-assignment.js';

const image = path => ({ name: path.split('/').at(-1), webkitRelativePath: path, size: 100, type: 'image/png' });
function uploadHarness(files = [image('a/valve.png'), image('b/valve.png')]) {
    const calls = [], copied = new Set();
    let count = 0;
    const progress = id => ({ source_id: 'source', revision: count, stage: 'uploading', file_count: count,
        ...(id ? { current_entry: { entry_id: id, committed: copied.has(id) } } : {}) });
    const adapters = {
        issue: async metadata => { calls.push(['issue', metadata]); return { source_id: 'source', intake_token: 'stable-token', entry_ids: files.filter(f => f.type === 'image/png').map((_, i) => `entry-${i}`) }; },
        begin: async token => { calls.push(['begin', token]); return progress(); },
        entry: async index => { calls.push(['entry', index]); return { entry_id: `entry-${index}`, token: `cap-${index}` }; },
        transfer: async (cap, file) => { calls.push(['transfer', cap.entry_id, file]); if (!copied.has(cap.entry_id)) count++; copied.add(cap.entry_id); return progress(cap.entry_id); },
        status: async id => { calls.push(['status', id]); return progress(id); },
        finish: async revision => { calls.push(['finish', revision]); return { ...progress(), stage: 'scanning', uploads_sealed: true }; },
    };
    const upload = createCatalogAssetUpload(files, adapters);
    return { upload, calls, adapters, copied };
}

test('each foreground advance sends at most one native File and preserves duplicate basename paths', async () => {
    const h = uploadHarness();
    await h.upload.advance();
    assert.deepEqual(h.calls.map(c => c[0]), ['issue', 'begin', 'entry', 'transfer']);
    assert.deepEqual(h.calls[0][1].map(f => f.relative_path), ['a/valve.png', 'b/valve.png']);
    await h.upload.advance();
    await h.upload.advance();
    assert.deepEqual(h.calls.filter(c => c[0] === 'transfer').map(c => c[1]), ['entry-0', 'entry-1']);
    assert.equal(h.upload.state().done, true);
    assert.deepEqual(h.calls.at(-1), ['finish', 2]);
    assert.equal(JSON.stringify(h.upload.state()).includes('cap-'), false);
});

test('lost Begin preserves the issued identity and explicit Continue reuses it after status', async () => {
    const h = uploadHarness();
    const begin = h.adapters.begin;
    let first = true;
    h.adapters.begin = async token => { if (first) { first = false; h.calls.push(['begin-lost', token]); throw new Error('lost'); } return begin(token); };
    await assert.rejects(h.upload.advance(), /lost/);
    await assert.rejects(h.upload.advance(), /Refresh/);
    await h.upload.refresh();
    await h.upload.advance();
    assert.equal(h.calls.filter(c => c[0] === 'issue').length, 1);
    assert.deepEqual(h.calls.filter(c => c[0].startsWith('begin')).map(c => c[1]), ['stable-token', 'stable-token']);
});

test('lost committed transfer pauses and read-only status advances without recopying that File', async () => {
    const h = uploadHarness();
    const transfer = h.adapters.transfer;
    let first = true;
    h.adapters.transfer = async (...args) => { const result = await transfer(...args); if (first) { first = false; throw new Error('lost acknowledgement'); } return result; };
    await assert.rejects(h.upload.advance(), /lost acknowledgement/);
    await assert.rejects(h.upload.advance(), /Refresh/);
    await h.upload.refresh();
    assert.equal(h.calls.filter(c => c[0] === 'transfer').length, 1);
    await h.upload.advance();
    assert.deepEqual(h.calls.filter(c => c[0] === 'transfer').map(c => c[1]), ['entry-0', 'entry-1']);
});

test('429 keeps the same index and a later foreground advance obtains a fresh entry capability', async () => {
    const h = uploadHarness();
    const transfer = h.adapters.transfer;
    let first = true;
    h.adapters.transfer = async (...args) => { if (first) { first = false; throw Object.assign(new Error('rate limited'), { status: 429, retryAfterMs: 1500 }); } return transfer(...args); };
    await assert.rejects(h.upload.advance(), error => error.status === 429);
    await h.upload.advance();
    assert.deepEqual(h.calls.filter(c => c[0] === 'entry').map(c => c[1]), [0, 0]);
    assert.equal(h.upload.state().completed, 1);
});

test('unsupported files are visibly skipped before selection freezes and an oversize image never begins', async () => {
    const h = uploadHarness([image('valve.png'), { name: 'notes.txt', size: 20, type: 'text/plain' }]);
    assert.deepEqual(h.upload.state().skipped, ['notes.txt']);
    await h.upload.advance();
    assert.equal(h.calls[0][1].length, 1);
    const tooLarge = image('large.png'); tooLarge.size = 3145729;
    assert.throws(() => createCatalogAssetUpload([tooLarge], h.adapters), /3 MiB/);
});

test('an unresolved bound transfer cannot be silently replaced or resumed', async () => {
    const h = uploadHarness();
    h.adapters.transfer = async () => ({ source_id: 'source', recovery: 'unresolved' });
    await h.upload.advance();
    await assert.rejects(h.upload.advance(), /fresh selection/);
    assert.equal(h.calls.filter(c => c[0] === 'entry').length, 1);
});

function controllerUploadHarness(transferUpload) {
    globalThis.Livewire = { interceptMessage: () => () => {} };
    const calls = [], copied = new Set();
    const wire = { $id: 'upload-host', launchSession: 'launch', launchToken: 'launch-token', batchId: null, previewVersion: 0,
        sourceId: null, sourceVersion: 0, recipeData: {}, candidateData: {}, selectedTableRecords: [], applyProgress: {}, intakeProgress: {} };
    wire.$call = async (method, ...args) => {
        calls.push([method, ...args]);
        if (method === 'issueReviewUpload') return { source_id: 'source', intake_token: 'begin-token', entry_ids: args[0].map((_, i) => `entry-${i}`) };
        if (method === 'beginReviewUpload') {
            Object.assign(wire, { sourceId: 'source', sourceVersion: 1 });
            wire.intakeProgress = { source_id: 'source', stage: 'uploading', revision: 0, file_count: 0, byte_count: 0, declared_files: 2, declared_bytes: 200 };
            return wire.intakeProgress;
        }
        if (method === 'chooseReviewSource') { Object.assign(wire, { sourceId: 'source-B', intakeProgress: {} }); return { source_id: 'source-B', stage: 'ready' }; }
        if (method === 'refreshReviewStatus') return { source: { source_id: wire.sourceId }, review: {}, apply: {} };
        if (method === 'issueReviewUploadEntry') return { entry_id: `entry-${args[0]}`, url: '/upload' };
        if (method === 'refreshReviewUpload') return { source_id: 'source', stage: 'uploading', revision: copied.size,
            current_entry: { entry_id: args[0], committed: copied.has(args[0]) } };
        if (method === 'finishReviewUpload') return { source_id: 'source', stage: 'scanning', revision: copied.size + 1, uploads_sealed: true };
        throw new Error(`Unexpected method ${method}`);
    };
    const controller = createAssetAssignmentController({ wire, transferUpload: (cap, file) => transferUpload(cap, file, copied) });
    return { wire, calls, copied, controller };
}

test('the existing foreground keeps one transfer in flight and Stop prevents all following requests', async () => {
    let release;
    const h = controllerUploadHarness((cap, file, copied) => new Promise(resolve => { release = () => {
        copied.add(cap.entry_id); resolve({ source_id: 'source', revision: copied.size, current_entry: { entry_id: cap.entry_id, committed: true } });
    }; }));
    h.controller.selectUploadFiles([image('a.png'), image('b.png')]);
    const run = h.controller.uploadFiles();
    await new Promise(resolve => setImmediate(resolve));
    await h.controller.uploadFiles();
    assert.equal(h.calls.filter(c => c[0] === 'issueReviewUploadEntry').length, 1);
    h.controller.stop(); release(); await run;
    assert.equal(h.calls.filter(c => c[0] === 'issueReviewUploadEntry').length, 1);
    assert.equal(h.controller.state().upload.completed, 1);
    assert.equal(h.calls.some(c => c[0] === 'finishReviewUpload'), false);
    h.controller.destroy();
});

test('destroying a host discards an in-flight upload response and schedules nothing else', async () => {
    let release;
    const h = controllerUploadHarness(cap => new Promise(resolve => { release = () => resolve({ source_id: 'source', current_entry: { entry_id: cap.entry_id, committed: true } }); }));
    h.controller.selectUploadFiles([image('a.png'), image('b.png')]);
    const run = h.controller.uploadFiles(); await new Promise(resolve => setImmediate(resolve));
    h.controller.destroy(); release(); await run;
    assert.equal(h.calls.filter(c => c[0] === 'issueReviewUploadEntry').length, 1);
    assert.equal(h.controller.state().upload.completed, 0);
});

test('Scan does not loop on an upload source that has not been finished', async () => {
    const h = controllerUploadHarness(() => { throw new Error('No file should be sent'); });
    h.wire.sourceProgress = { stage: 'uploading', revision: 0 };

    await h.controller.scan();

    assert.deepEqual(h.calls, []);
    assert.equal(h.controller.state().unknown, false);
    h.controller.destroy();
});

test('Stop cancels a single Retry-After wait without sending the next transfer', async () => {
    let transfers = 0;
    const h = controllerUploadHarness(async () => { transfers++; throw Object.assign(new Error('rate limited'), { status: 429, retryAfterMs: 60000 }); });
    h.controller.selectUploadFiles([image('a.png')]);
    const run = h.controller.uploadFiles(); await new Promise(resolve => setImmediate(resolve));
    assert.equal(h.controller.state().running, true);

    h.controller.stop(); await run;

    assert.equal(transfers, 1);
    assert.equal(h.controller.state().unknown, false);
    h.controller.destroy();
});

test('a confirmed source switch discards the old browser upload association without transferring its remaining Files', async () => {
    let release;
    const h = controllerUploadHarness((cap, file, copied) => new Promise(resolve => { release = () => {
        copied.add(cap.entry_id); resolve({ source_id: 'source', revision: copied.size, file_count: copied.size, byte_count: 100,
            declared_files: 2, declared_bytes: 200, current_entry: { entry_id: cap.entry_id, committed: true } });
    }; }));
    h.controller.selectUploadFiles([image('a.png'), image('b.png')]);
    const run = h.controller.uploadFiles(); await new Promise(resolve => setImmediate(resolve));
    h.controller.stop(); release(); await run;

    await h.controller.command('chooseReviewSource');

    assert.equal(h.controller.state().upload, null);
    await h.controller.refreshUpload();
    const calls = h.calls.length;
    await h.controller.continueUpload();
    assert.equal(h.calls.length, calls);
    assert.equal(h.calls.at(-1)[0], 'refreshReviewStatus');
    assert.equal(h.copied.size, 1);
    assert.equal(h.wire.sourceId, 'source-B');
    assert.equal(h.controller.state().unknown, false);
    h.controller.destroy();
});

test('a confirmed HTTP copy followed by Stop displays its current files and bytes without an extra Refresh', async () => {
    let release;
    const h = controllerUploadHarness(cap => new Promise(resolve => { release = () => resolve({ source_id: 'source', revision: 1,
        file_count: 1, byte_count: 100, declared_files: 2, declared_bytes: 200, current_entry: { entry_id: cap.entry_id, committed: true } }); }));
    h.controller.selectUploadFiles([image('a.png'), image('b.png')]);
    const run = h.controller.uploadFiles(); await new Promise(resolve => setImmediate(resolve));
    h.controller.stop(); release(); await run;

    assert.equal(h.wire.intakeProgress.file_count, 0);
    assert.deepEqual([h.controller.uploadProgress().file_count, h.controller.uploadProgress().byte_count], [1, 100]);
    assert.equal(h.calls.some(call => call[0] === 'refreshReviewUpload'), false);
    h.controller.destroy();
});

test('a generic folder source cannot enter batch recovery or poison the existing foreground', async () => {
    const h = controllerUploadHarness(() => { throw new Error('No transfer is authorized'); });
    h.wire.sourceId = 'folder-source';
    h.wire.sourceProgress = { stage: 'ready' };

    await h.controller.continueUpload();

    assert.deepEqual(h.calls, []);
    assert.equal(h.controller.state().unknown, false);
    await h.controller.refreshUpload();
    assert.equal(h.calls[0][0], 'refreshReviewStatus');
    assert.equal(h.controller.state().unknown, false);
    assert.equal(h.wire.sourceId, 'folder-source');
    h.controller.destroy();
});

test('the installed Alpine display effect updates copied files and bytes after HTTP success and Stop', async () => {
    const map = JSON.parse(readFileSync(new URL('../../vendor/livewire/livewire/dist/livewire.esm.js.map', import.meta.url), 'utf8'));
    const source = map.sourcesContent[map.sources.indexOf('../node_modules/alpinejs/dist/module.cjs.js')];
    const module = { exports: {} }; class Element {} class MutationObserver { observe() {} disconnect() {} takeRecords() { return []; } }
    const Alpine = new Function('module', 'exports', 'window', 'document', 'MutationObserver', source + '\nreturn module.exports.default;')(module, module.exports, { Element }, { createElement: () => ({}) }, MutationObserver);
    const h = controllerUploadHarness(() => { throw new Error('The UI uses its actual HTTP adapter'); });
    const previousFetch = globalThis.fetch;
    let release, rendered = '';
    globalThis.fetch = () => new Promise(resolve => { release = () => resolve({ ok: true, json: async () => ({ source_id: 'source', revision: 1,
        file_count: 1, byte_count: 100, declared_files: 2, declared_bytes: 200, current_entry: { entry_id: 'entry-0', committed: true } }) }); });
    const ui = Alpine.reactive({ ...catalogAssetAssignment({ user: 1, csrf: 'test-csrf' }), $wire: Alpine.reactive(h.wire), $nextTick: callback => callback() });
    ui.init();
    let effect;
    try {
        ui.selectUploadFiles([new File([new Uint8Array(100)], 'a.png', { type: 'image/png' }), new File([new Uint8Array(100)], 'b.png', { type: 'image/png' })]);
        const run = ui.uploadFiles(); await new Promise(resolve => setImmediate(resolve));
        effect = Alpine.effect(() => {
            const progress = ui.uploadProgress();
            rendered = progress ? `${progress.file_count}/${progress.declared_files} files; ${progress.byte_count}/${progress.declared_bytes} bytes` : '';
        });
        assert.equal(rendered, '0/2 files; 0/200 bytes');

        ui.stop(); release(); await run; await Promise.resolve();

        assert.equal(ui.upload.completed, 1);
        assert.equal(rendered, '1/2 files; 100/200 bytes');
        assert.deepEqual(h.calls.map(call => call[0]), ['issueReviewUpload', 'beginReviewUpload', 'issueReviewUploadEntry']);
    } finally {
        if (effect) Alpine.release(effect);
        ui.destroy(); h.controller.destroy(); globalThis.fetch = previousFetch;
    }
});
