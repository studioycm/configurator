import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { catalogImagesEditor, createWorkspaceImageController } from '../../resources/js/catalog-images-editor.js';

const map = JSON.parse(readFileSync(new URL('../../vendor/livewire/livewire/dist/livewire.esm.js.map', import.meta.url), 'utf8'));
const source = name => map.sourcesContent[map.sources.indexOf('../js/request/' + name + '.js')];
const interceptorSource = source('interceptor').split('export class RequestInterceptor')[0].replace(/^import .*\n/gm, '').replace('export class MessageInterceptor', 'class MessageInterceptor');
const MessageInterceptor = new Function(interceptorSource + '\nreturn MessageInterceptor;')();
const Message = new Function('MessageInterceptor', source('message').replace(/^import .*\n/gm, '').replace('export default class Message', 'class Message') + '\nreturn Message;')(MessageInterceptor);
const requestSource = source('index'), start = requestSource.indexOf('let { snapshot: snapshotEncoded, effects } = payload'), end = requestSource.indexOf('\n                    })', start);
assert.ok(start >= 0 && end > start);
const processPayload = new Function('message','payload','request','Alpine','discardIfSuperseded','supersedeOlderMessages','requestAnimationFrame',requestSource.slice(start,end));

const utilsSource=map.sourcesContent[map.sources.indexOf('../js/utils.js')];
const weakStart=utilsSource.indexOf('class WeakBag'), weakEnd=utilsSource.indexOf('\nexport function dispatch',weakStart);
const WeakBag=new Function(utilsSource.slice(weakStart,weakEnd)+';return WeakBag;')();
const registrySource=source('interceptor').slice(source('interceptor').indexOf('export class InterceptorRegistry')).replace('export class','class');
const InterceptorRegistry=new Function('WeakBag','MessageInterceptor',registrySource+';return InterceptorRegistry;')(WeakBag,MessageInterceptor);
const registry=new InterceptorRegistry();
globalThis.Livewire={interceptMessage:callback=>registry.addMessageInterceptor(callback)};
let nextHost=0;

function harness() {
    const pending = [], effects = [], locks = [], states = [];
    const hooks = {get size() {return registry.messageInterceptorCallbacks.length;}};
    const component = { id: 'host-'+(++nextHost), mergeNewSnapshot(encoded) { Object.assign(wire, JSON.parse(encoded).data); effects.push('sync'); }, processEffects() { effects.push('effects'); } };
    const wire = { $id: component.id, imageEditorSession: 'session-a', imageTarget: 'product.main_image', imageRecordId: 1, catalogMainImageOriginal: 'baseline',
        data: { image_intent: { operation: 'remove', source_token: null, draft_intent_id: 'one' }, image_upload: [] }, imagePendingTransition: { token: 'next-b' },
        interceptMessage() { assert.fail('The broken component registry must not be used.'); } };
    const data = () => ({ imageEditorSession: wire.imageEditorSession, imageTarget: wire.imageTarget, imageRecordId: wire.imageRecordId, catalogMainImageOriginal: wire.catalogMainImageOriginal, data: wire.data });
    for (const method of ['prepareWorkspaceImageSave','saveWorkspaceImage','reconcileWorkspaceImageSave','resolveWorkspaceImageTransition','catalogMainImageRevert']) {
        wire[method] = (...params) => new Promise((resolve,reject) => {
            const message = new Message(component);
            message.addAction({ name: method, params, fingerprint: method, rejectPromise: reject, resolvePromise: resolve, invokeOnSuccess() {}, invokeOnError() {}, invokeOnFinish() {} });
            message.updates = {}; message.setInterceptors(registry.getMessageInterceptors(message));
            message.addInterceptor(({ onSuccess }) => onSuccess(({ onMorph }) => onMorph(async () => effects.push('morph'))));
            pending.push({ method, params, message });
        });
    }
    async function reply(index, result, nextData = data()) {
        const outstanding = [];
        processPayload(pending[index].message, { snapshot: JSON.stringify({ memo: { id: component.id }, data: nextData }), effects: { returns: [result] } }, {},
            { transaction(fn) { const promise = fn(); outstanding.push(promise); return promise; } }, () => false, () => {}, fn => fn());
        await Promise.all(outstanding); await new Promise(resolve => setImmediate(resolve));
    }
    const controller = createWorkspaceImageController({ wire, onLock: value => locks.push(value), onState: value => states.push(value) });
    return { wire, data, controller, pending, effects, locks, states, hooks, reply };
}

test('workspace Save uses two separate native messages with the same editor and pending destination', async () => {
    const h = harness(), task = h.controller.save('next-b');
    assert.deepEqual(h.pending.map(p => p.method), ['prepareWorkspaceImageSave']);
    await h.reply(0, { outcome: 'ready', receipt: 'opaque' });
    assert.deepEqual(h.pending.map(p => p.method), ['prepareWorkspaceImageSave','saveWorkspaceImage']);
    assert.deepEqual(h.pending[0].params, h.pending[1].params);
    await h.reply(1, { outcome: 'committed', message: 'Image saved.' }, { ...h.data(), imageRecordId: 2, imageEditorSession: 'session-b' });
    await task; assert.equal(h.wire.imageRecordId, 2); assert.equal(h.controller.state().unknown, false);
    assert.deepEqual(h.locks, [true,false]); h.controller.destroy();
});

test('changed draft before preparation response cancels before sync and never sends final Save', async () => {
    const h = harness(), task = h.controller.save(); h.wire.data.image_intent.draft_intent_id = 'newer';
    await h.reply(0, { outcome: 'ready', receipt: 'old' }); await task;
    assert.deepEqual(h.effects, []); assert.equal(h.pending.length, 1); assert.equal(h.controller.state().unknown, false); h.controller.destroy();
});

test('stale final response is cancelled before effects and image-only recovery does not replay Save', async () => {
    const h = harness(), task = h.controller.save('next-b'); await h.reply(0, { outcome: 'ready', receipt: 'opaque' });
    h.effects.length = 0; h.wire.data.image_intent.draft_intent_id = 'changed';
    await h.reply(1, { outcome: 'committed' }); await task;
    assert.deepEqual(h.effects, []); assert.equal(h.controller.state().unknown, true);
    const recovery = h.controller.reconcile(); assert.equal(h.pending[2].method, 'reconcileWorkspaceImageSave');
    await h.reply(2, { outcome: 'goal_matches', message: 'Image matches your choice.' }, { ...h.data(), imageRecordId: 2 });
    await recovery; assert.equal(h.controller.state().unknown, false); assert.equal(h.wire.imageRecordId, 2);
    assert.equal(h.pending.filter(p => p.method === 'saveWorkspaceImage').length, 1); h.controller.destroy();
});

test('untyped successful HTTP response remains unknown and neither Save nor teardown can replay it', async () => {
    const h = harness(), task = h.controller.save(); await h.reply(0, { outcome: 'ready', receipt: 'opaque' });
    await h.reply(1, undefined); await task; assert.equal(h.controller.state().unknown, true);
    await h.controller.save(); assert.equal(h.pending.length, 2);
    h.controller.destroy(); assert.equal(h.hooks.size, 0);
});

test('baseline recovery permits only a later explicit Save and a conflict remains unknown', async () => {
    for (const outcome of ['baseline_matches','conflict']) {
        const h = harness(), task = h.controller.save(); await h.reply(0, { outcome: 'ready', receipt: 'opaque' }); await h.reply(1, undefined); await task;
        const recovery = h.controller.reconcile(); await h.reply(2, { outcome, message: outcome }); await recovery;
        assert.equal(h.pending.length, 3); assert.equal(h.controller.state().unknown, outcome === 'conflict'); h.controller.destroy();
    }
});

test('destroy after preparation was sent suppresses every later effect and final request', async () => {
    const h = harness(), task = h.controller.save(); h.controller.destroy(); await h.reply(0, { outcome: 'ready', receipt: 'opaque' }); await task;
    assert.deepEqual(h.effects, []); assert.equal(h.pending.length, 1);
});


test('a replaced source invalidates a workspace response before sync even if the old image intent remains', async () => {
    const h = harness(), task = h.controller.save();
    h.wire.catalogMainImageSourceProgress = { source_id: 'new-source', scan_version: 2 };
    await h.reply(0, { outcome: 'ready', receipt: 'old' }); await task;
    assert.deepEqual(h.effects, []); assert.equal(h.pending.length, 1); h.controller.destroy();
});

test('the workspace captures the native lazy wire once and retains it after the initiating form detaches', async () => {
    const h = harness(); h.controller.destroy();
    const wireSource = map.sourcesContent[map.sources.indexOf('../js/$wire.js')];
    const start = wireSource.indexOf("Alpine.magic('wire'"), end = wireSource.indexOf("\nwireProperty('__instance'", start);
    let magic, connected = true;
    new Function('Alpine', 'findComponentByEl', 'generateEntangleFunction', 'generateWatchFunction', wireSource.slice(start, end))(
        { magic(name, factory) { magic = factory; } }, () => { if (!connected) throw new Error('Component not found'); return { $wire: h.wire }; }, () => {}, () => {});
    const workspace = { inert: false, setAttribute() {}, removeAttribute() {} };
    const form = new EventTarget(); form.closest = () => workspace; form.querySelector = () => null;
    const ui = catalogImagesEditor(); ui.$el = form;
    Object.defineProperty(ui, '$wire', { get() { return magic({}, { cleanup() {} }); } });
    ui.init(); const task = ui.save('next-b'); connected = false;
    await h.reply(0, { outcome: 'ready', receipt: 'opaque' });
    assert.equal(h.pending[1].method, 'saveWorkspaceImage');
    await h.reply(1, { outcome: 'committed', message: 'Image saved.' }); await task;
    assert.equal(ui.unknown, false); assert.equal(workspace.inert, false); ui.destroy(); assert.equal(h.hooks.size, 0);
});


test('a final HTTP rejection cannot prove rollback and blocks another Save until read-only recovery', async () => {
    for (const status of [401, 403, 404, 419, 422]) {
        const h = harness(), task = h.controller.save('next-b');
        await h.reply(0, { outcome: 'ready', receipt: 'opaque' });
        h.pending[1].message.invokeOnError({ response: { status }, body: '{}', preventDefault() {} });
        await task;
        assert.equal(h.controller.state().unknown, true, `final ${status} must remain uncertain`);
        assert.match(h.controller.state().message, /Check the saved image/);
        await h.controller.save('next-b'); assert.equal(h.pending.length, 2);
        const recovery = h.controller.reconcile();
        assert.equal(h.pending[2].method, 'reconcileWorkspaceImageSave');
        await h.reply(2, { outcome: 'goal_matches', message: 'Image matches your choice.' }, { ...h.data(), imageRecordId: 2 });
        await recovery;
        assert.equal(h.controller.state().unknown, false); assert.equal(h.wire.imageRecordId, 2);
        assert.equal(h.pending.filter(p => p.method === 'saveWorkspaceImage').length, 1);
        h.controller.destroy();
    }
});

test('a preparation HTTP rejection retains the draft without sending a final Save or offering commit recovery', async () => {
    for (const status of [401, 403, 404, 419, 422]) {
        const h = harness(), draft = structuredClone(h.wire.data), task = h.controller.save();
        h.pending[0].message.invokeOnError({ response: { status }, body: '{}', preventDefault() {} });
        await task;
        assert.equal(h.controller.state().unknown, false);
        assert.match(h.controller.state().message, /Preparation did not complete/);
        assert.deepEqual(h.wire.data, draft); assert.equal(h.pending.length, 1);
        await h.controller.reconcile(); assert.equal(h.pending.length, 1); h.controller.destroy();
    }
});


test('workspace editor disposal releases the installed registry before reopening the same Page owner', async () => {
    const h=harness(), old=h.controller.save();
    h.controller.destroy();
    assert.equal(registry.messageInterceptorCallbacks.length,0);
    const b=createWorkspaceImageController({wire:h.wire}); b.destroy();
    h.wire.imageEditorSession='reopened-a';
    const a=createWorkspaceImageController({wire:h.wire}), current=a.save();
    await h.reply(1,{outcome:'ready',receipt:'new'});
    await h.reply(2,{outcome:'committed',message:'Image saved.'});await current;
    assert.equal(a.state().unknown,false);
    h.effects.length=0;
    await h.reply(0,{outcome:'ready',receipt:'old'});await old;
    assert.deepEqual(h.effects,[]);assert.equal(h.pending.length,3);
    a.destroy();a.destroy();assert.equal(registry.messageInterceptorCallbacks.length,0);
});
