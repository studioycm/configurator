import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createCatalogImageSaveController, catalogImageEditor, interceptCatalogImageMessage } from '../../resources/js/catalog-image-editor.js';

// Run the installed framework's message/interceptor and pre-effects response path.
// This deliberately fails on a changed unsupported API rather than simulating promise timing.
const map=JSON.parse(readFileSync(new URL('../../vendor/livewire/livewire/dist/livewire.esm.js.map',import.meta.url),'utf8'));
const source=name=>map.sourcesContent[map.sources.indexOf('../js/request/'+name+'.js')];
const interceptorSource=source('interceptor').split('export class RequestInterceptor')[0].replace(/^import .*\n/gm,'').replace('export class MessageInterceptor','class MessageInterceptor');
const MessageInterceptor=new Function(interceptorSource+'\nreturn MessageInterceptor;')();
const Message=new Function('MessageInterceptor',source('message').replace(/^import .*\n/gm,'').replace('export default class Message','class Message')+'\nreturn Message;')(MessageInterceptor);
const requestSource=source('index');const start=requestSource.indexOf('let { snapshot: snapshotEncoded, effects } = payload');const end=requestSource.indexOf('\n                    })',start);
assert.ok(start>=0 && end>start,'Installed pre-effects response path must exist');
const processPayload=new Function('message','payload','request','Alpine','discardIfSuperseded','supersedeOlderMessages','requestAnimationFrame',requestSource.slice(start,end));

const utilsSource=map.sourcesContent[map.sources.indexOf('../js/utils.js')];
const weakStart=utilsSource.indexOf('class WeakBag'), weakEnd=utilsSource.indexOf('\nexport function dispatch',weakStart);
const WeakBag=new Function(utilsSource.slice(weakStart,weakEnd)+';return WeakBag;')();
const registrySource=source('interceptor').slice(source('interceptor').indexOf('export class InterceptorRegistry')).replace('export class','class');
const InterceptorRegistry=new Function('WeakBag','MessageInterceptor',registrySource+';return InterceptorRegistry;')(WeakBag,MessageInterceptor);
const registry=new InterceptorRegistry();
globalThis.Livewire={interceptMessage:callback=>registry.addMessageInterceptor(callback)};
let nextHost=0;

function harness(method='save') {
    const methods = Array.isArray(method) ? method : [method];
    const actionHooks=new Map(),pending=[],effects=[];let draining=[];
    const component={id:'host-'+(++nextHost),mergeNewSnapshot(encoded){effects.push('sync');wire.data=JSON.parse(encoded).data;},processEffects(){effects.push('effects');}};
    const wire={$id:component.id,data:{product_name:'Original draft',image_intent:{operation:'remove',source_token:null,draft_intent_id:'one'}},catalogMainImageCreationSession:null,catalogMainImageOriginal:'original',
        intercept(name,callback){actionHooks.set(name,callback);return()=>actionHooks.delete(name);},
        interceptMessage(name,callback){return registry.addInterceptor(component,({message,...rest})=>{if(message.getActions().some(action=>action.name===name))callback({message,...rest});});},
        prepareImageSave(){return dispatch('prepareImageSave',[]);},$call(name,...params){return dispatch(name,params);}};
    function dispatch(name,params){return new Promise((resolve,reject)=>{
        const action={name,params,fingerprint:name,cancel(){this.cancelled=true;},rejectPromise:reject,resolvePromise:resolve,invokeOnFinish(){},invokeOnSuccess(){}};
        actionHooks.get(name)?.({action});if(action.cancelled){reject({status:null});return;}
        const message=new Message(component);message.addAction(action);message.updates={};
        message.setInterceptors(registry.getMessageInterceptors(message));
        message.addInterceptor(({onSuccess})=>onSuccess(({onMorph})=>onMorph(async()=>effects.push('morph'))));
        pending.push({message,name,params});
    });}
    async function reply(index,result,data=structuredClone(wire.data)){
        const {message}=pending[index];processPayload(message,{snapshot:JSON.stringify({memo:{id:component.id},data}),effects:{returns:[result]}},{},
            {transaction(callback){const task=callback();draining.push(task);return task;}},()=>false,()=>{},callback=>callback());
        await Promise.all(draining);draining=[];await new Promise(resolve=>setImmediate(resolve));
    }
    return {wire,pending,effects,reply,component,controller:createCatalogImageSaveController({wire,methods})};
}

test('an obsolete Save response is cancelled before installed Livewire sync effects and morph',async()=>{
    const h=harness();const task=h.controller.run('save',[false,false]);
    await h.reply(0,{outcome:'ready',receipt:'opaque'});assert.equal(h.pending[1].name,'save');assert.deepEqual(h.pending[1].params,[false,false]);
    h.effects.length=0;h.wire.data.product_name='New ordinary draft';
    await h.reply(1,undefined,{product_name:'Old saved response',image_intent:{operation:'keep'}});await task;
    assert.deepEqual(h.effects,[]);assert.equal(h.wire.data.product_name,'New ordinary draft');assert.equal(h.pending[1].message.isCancelled(),true);assert.equal(h.controller.state().unknown,true);h.controller.destroy();
});

test('a changed image draft during preparation never reaches native Save or stale effects',async()=>{
    const h=harness();const task=h.controller.run('save');h.wire.data.image_intent={operation:'set',source_token:'newer',draft_intent_id:'two'};
    await h.reply(0,{outcome:'ready',receipt:'old'},{product_name:'Original draft',image_intent:{operation:'remove'}});await task;
    assert.equal(h.pending.length,1);assert.deepEqual(h.effects,[]);assert.equal(h.wire.data.image_intent.source_token,'newer');h.controller.destroy();
});

test('a rotated create session cannot receive the prior create-another response',async()=>{
    const h=harness('create');h.wire.catalogMainImageCreationSession='first';const task=h.controller.run('create',[true]);
    await h.reply(0,{outcome:'ready',receipt:'opaque'});h.effects.length=0;h.wire.catalogMainImageCreationSession='newer';
    await h.reply(1,undefined,{product_name:'Old creation',image_intent:{operation:'keep'}});await task;
    assert.deepEqual(h.effects,[]);assert.equal(h.wire.catalogMainImageCreationSession,'newer');assert.equal(h.controller.state().unknown,true);h.controller.destroy();
});

test('the native createAnother action prepares and replays its exact empty arguments once', async () => {
    const h = harness(['create', 'createAnother']);
    await h.wire.$call('createAnother').catch(() => {});
    assert.deepEqual(h.pending.map(call => call.name), ['prepareImageSave']);
    await h.reply(0, {outcome: 'ready', receipt: 'current'});
    assert.deepEqual(h.pending.map(call => call.name), ['prepareImageSave', 'createAnother']);
    assert.deepEqual(h.pending[1].params, []);
    await h.reply(1, undefined, {product_name: '', image_intent: {operation: 'keep'}});
    assert.equal(h.pending.length, 2);
    assert.equal(h.controller.state().unknown, false);
    h.controller.destroy();
});

test('a native createAnother response cannot replace a newer creation session or enable unknown replay', async () => {
    const h = harness(['create', 'createAnother']);
    h.wire.catalogMainImageCreationSession = 'first';
    const task = h.controller.run('createAnother');
    await h.reply(0, {outcome: 'ready', receipt: 'current'});
    h.effects.length = 0;
    h.wire.catalogMainImageCreationSession = 'newer';
    await h.reply(1, undefined, {product_name: 'Old creation', image_intent: {operation: 'keep'}});
    await task;
    assert.deepEqual(h.effects, []);
    assert.equal(h.wire.catalogMainImageCreationSession, 'newer');
    assert.equal(h.controller.state().unknown, true);
    h.controller.resetUnknown();
    assert.equal(h.controller.state().unknown, true);
    const requests = h.pending.length;
    await h.controller.run('createAnother');
    assert.equal(h.pending.length, requests);
    h.controller.destroy();
});

test('ordinary keep actions retain their original native call without preparation',async()=>{
    const h=harness();h.wire.data.image_intent.operation='keep';const task=h.wire.$call('save',false,false);
    assert.deepEqual(h.pending.map(p=>p.name),['save']);await h.reply(0,undefined);await task;h.controller.destroy();
});


test('a current native Save response applies normally and preserves both original flags', async () => {
    const h = harness();
    const task = h.controller.run('save', [false, false]);
    await h.reply(0, {outcome: 'ready', receipt: 'current'});
    h.effects.length = 0;
    await h.reply(1, undefined, {product_name: 'Saved', image_intent: {operation: 'keep'}});
    await task;
    assert.deepEqual(h.effects, ['sync', 'effects', 'morph']);
    assert.deepEqual(h.pending[1].params, [false, false]);
    assert.equal(h.controller.state().unknown, false);
    h.controller.destroy();
});

test('destroying an editor cancels its in-flight response before it can reset another draft', async () => {
    const h = harness();
    const task = h.controller.run('save');
    await h.reply(0, {outcome: 'ready', receipt: 'current'});
    h.effects.length = 0;
    h.controller.destroy();
    await h.reply(1, undefined, {product_name: 'Obsolete'});
    await task;
    assert.deepEqual(h.effects, []);
    assert.equal(h.wire.data.product_name, 'Original draft');
});


test('a missing native Save response remains review required with no automatic replay', async () => {
    const h = harness();
    const task = h.controller.run('save');
    await h.reply(0, {outcome: 'ready', receipt: 'current'});
    h.pending[1].message.rejectActionPromises(new Error('Synthetic lost response'));
    await task;
    assert.equal(h.controller.state().unknown, true);
    h.wire.reconcileImageSave = async () => ({outcome: 'review_required', message: 'Review the whole saved record.'});
    await h.controller.reconcile();
    assert.equal(h.controller.state().unknown, true);
    assert.equal(h.controller.state().message, 'Review the whole saved record.');
    assert.equal(h.pending.length, 2);
    h.controller.destroy();
});


test('an old source scan reply cannot replace a newly chosen source or image proposal', async () => {
    const h = harness();
    h.wire.catalogMainImageSourceProgress = {source_id: 'older', scan_version: 1};
    const task = h.wire.$call('catalogMainImageAdvanceSource');
    h.wire.catalogMainImageSourceProgress = {source_id: 'newer', scan_version: 1};
    const rejected = assert.rejects(task);
    await h.reply(0, {stage: 'ready'}, {product_name: 'Older response', image_intent: {operation: 'remove'}});
    await rejected;
    assert.deepEqual(h.effects, []);
    assert.equal(h.wire.catalogMainImageSourceProgress.source_id, 'newer');
    h.controller.destroy();
});

test('an obsolete native image upload reply is rejected before overwriting a newer image draft', async () => {
    const h = harness();
    const task = h.wire.$call('_finishUpload', 'data.image_upload.fixture', ['reference'], false);
    h.wire.data.image_intent = {operation: 'set', source_token: 'newer', draft_intent_id: 'two'};
    const rejected = assert.rejects(task);
    await h.reply(0, undefined, {image_intent: {operation: 'set', source_token: 'older'}});
    await rejected;
    assert.deepEqual(h.effects, []);
    assert.equal(h.wire.data.image_intent.source_token, 'newer');
    h.controller.destroy();
});

test('image response guards do not intercept an unrelated native upload field', async () => {
    const h = harness();
    const task = h.wire.$call('_finishUpload', 'data.other_upload.fixture', ['reference'], false);
    h.wire.data.product_name = 'Changed';
    await h.reply(0, undefined);
    await task;
    assert.deepEqual(h.effects, ['sync', 'effects', 'morph']);
    h.controller.destroy();
});


test('the upload resume control continues a staged scan through short requests', async () => {
    const calls = [];
    const widget = catalogImageEditor({});
    widget.$wire = {
        async catalogMainImageStageUpload() {calls.push('resume exact upload'); return {stage: 'scanning'};},
        async catalogMainImageSourceStatus() {calls.push('status'); return {stage: 'scanning'};},
        async catalogMainImageAdvanceSource() {calls.push('bounded step'); return {stage: 'ready'};},
    };
    await widget.stageUpload();
    assert.deepEqual(calls, ['resume exact upload', 'status', 'bounded step']);
    assert.equal(widget.scanning, false);
});

test('a recovered settled upload can seal then scan without restarting its source', async () => {
    const calls = [];
    const widget = catalogImageEditor({});
    widget.$wire = {
        async catalogMainImageSourceStatus() {return {source_id: 'same', stage: 'uploading', file_count: 1, unsettled_uploads: 0};},
        async catalogMainImageAdvanceSource() {calls.push('same bounded source'); return {source_id: 'same', stage: calls.length === 1 ? 'scanning' : 'ready'};},
    };
    await widget.scan();
    assert.deepEqual(calls, ['same bounded source', 'same bounded source']);
});

function sourceLifecycle(overrides = {}) {
    const listeners = new Map(), root = { addEventListener(name, fn) { listeners.set(name, fn); }, removeEventListener(name) { listeners.delete(name); }, getAttribute() {}, contains() { return true; } };
    const wire = { data: { image_intent: { operation: 'keep' } }, $el: root, $id: 'image',
        intercept() { return () => {}; }, interceptMessage() { return () => {}; }, ...overrides };
    const ui = catalogImageEditor({ methods: [] }); ui.$wire = wire;
    ui.$el = { closest(selector) { return selector === 'form' ? root : null; }, setAttribute() {}, removeAttribute() {}, contains() { return false; } };
    ui.init();
    return { ui, wire };
}

test('destroyed native image field cannot start scanning when its pending stage returns', async () => {
    let resolve;
    const calls = [];
    const h = sourceLifecycle({ catalogMainImageStageUpload() { return new Promise(done => { resolve = done; }); }, async catalogMainImageSourceStatus() { calls.push('status'); return { stage: 'ready' }; }, async catalogMainImageAdvanceSource() { calls.push('advance'); } });
    const task = h.ui.stageUpload(); h.ui.destroy(); resolve({ stage: 'scanning' }); await task;
    assert.deepEqual(calls, []);
});

test('native scan after teardown sends no source status request', async () => {
    const calls = [], h = sourceLifecycle({ async catalogMainImageSourceStatus() { calls.push('status'); return { stage: 'ready' }; } });
    h.ui.destroy(); await h.ui.scan(); assert.deepEqual(calls, []);
});

test('destroying a native scan while status is pending prevents an advance', async () => {
    let resolve;
    const calls = [], h = sourceLifecycle({ catalogMainImageSourceStatus() { calls.push('status'); return new Promise(done => { resolve = done; }); }, async catalogMainImageAdvanceSource() { calls.push('advance'); } });
    const task = h.ui.scan(); h.ui.destroy(); resolve({ stage: 'scanning', outcome: 'ready' }); await task;
    assert.deepEqual(calls, ['status']);
});

test('native stage and scan retain the installed memoized wire proxy across the first element morph', async () => {
    const wireSource = map.sourcesContent[map.sources.indexOf('../js/$wire.js')];
    const start = wireSource.indexOf("Alpine.magic('wire'"), end = wireSource.indexOf("\nwireProperty('__instance'", start);
    let magic, connected = true; const calls = [];
    const h = sourceLifecycle({ async catalogMainImageStageUpload() { calls.push('stage'); connected = false; return { stage: 'scanning' }; },
        async catalogMainImageSourceStatus() { calls.push('status'); return { stage: 'scanning', outcome: 'ready' }; },
        async catalogMainImageAdvanceSource() { calls.push('advance'); return { stage: 'ready', outcome: 'ready' }; } });
    new Function('Alpine', 'findComponentByEl', 'generateEntangleFunction', 'generateWatchFunction', wireSource.slice(start, end))(
        { magic(name, factory) { magic = factory; } }, () => { if (!connected) throw new Error('Component not found'); return { $wire: h.wire }; }, () => {}, () => {});
    Object.defineProperty(h.ui, '$wire', { get() { return magic({}, { cleanup() {} }); } });
    await h.ui.stageUpload(); assert.deepEqual(calls, ['stage', 'status', 'advance']); h.ui.destroy();
});


test('installed registry teardown releases A then B guards and a reopened A retains its current source response', async () => {
    const h=harness([]);
    const stale=h.wire.$call('catalogMainImageChooseSource','source:old');
    const staleRejected=assert.rejects(stale);
    assert.doesNotThrow(()=>h.controller.destroy());
    assert.equal(registry.messageInterceptorCallbacksByComponent.get(h.component).length,0);
    const b=createCatalogImageSaveController({wire:h.wire,methods:[]});
    assert.doesNotThrow(()=>b.destroy());
    const a=createCatalogImageSaveController({wire:h.wire,methods:[]});
    const fresh=h.wire.$call('catalogMainImageChooseSource','source:current');
    await h.reply(1,{stage:'ready'});await fresh;
    assert.deepEqual(h.effects,['sync','effects','morph']);
    h.effects.length=0;
    await h.reply(0,{stage:'ready'});await staleRejected;
    assert.deepEqual(h.effects,[]);
    a.destroy();a.destroy();
    assert.equal(registry.messageInterceptorCallbacks.length,0);
    assert.equal(registry.messageInterceptorCallbacksByComponent.get(h.component).length,0);
});


test('the supported registry wrapper filters host and method and removes only its own callback once', () => {
    let calls=0;
    const remove=interceptCatalogImageMessage({$id:'own-page'},'catalogMainImageChooseSource',({cancel})=>{calls++;cancel();});
    const message=(id,name)=>{
        const result=new Message({id});
        result.addAction({name,params:[],fingerprint:name,rejectPromise(){},resolvePromise(){},invokeOnFinish(){},invokeOnSuccess(){}});
        result.setInterceptors(registry.getMessageInterceptors(result));
        return result;
    };
    assert.equal(message('other-page','catalogMainImageChooseSource').isCancelled(),false);
    assert.equal(message('own-page','unrelatedAction').isCancelled(),false);
    assert.equal(calls,0);
    assert.equal(message('own-page','catalogMainImageChooseSource').isCancelled(),true);
    assert.equal(calls,1);
    const unrelated=registry.addMessageInterceptor(()=>{});
    remove();remove();assert.equal(registry.messageInterceptorCallbacks.length,1);
    unrelated();assert.equal(registry.messageInterceptorCallbacks.length,0);
});
