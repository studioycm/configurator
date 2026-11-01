import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createCatalogImageSaveController, catalogImageEditor } from '../../resources/js/catalog-image-editor.js';

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

function harness(method='save') {
    const actionHooks=new Map(),messageHooks=new Map(),pending=[],effects=[];let draining=[];
    const wire={data:{product_name:'Original draft',image_intent:{operation:'remove',source_token:null,draft_intent_id:'one'}},catalogMainImageCreationSession:null,catalogMainImageOriginal:'original',
        intercept(name,callback){actionHooks.set(name,callback);return()=>actionHooks.delete(name);},
        interceptMessage(name,callback){messageHooks.set(name,callback);return()=>messageHooks.delete(name);},
        prepareImageSave(){return dispatch('prepareImageSave',[]);},$call(name,...params){return dispatch(name,params);}};
    function dispatch(name,params){return new Promise((resolve,reject)=>{
        const action={name,params,fingerprint:name,cancel(){this.cancelled=true;},rejectPromise:reject,resolvePromise:resolve,invokeOnFinish(){},invokeOnSuccess(){}};
        actionHooks.get(name)?.({action});if(action.cancelled){reject({status:null});return;}
        const component={id:'host',mergeNewSnapshot(encoded){effects.push('sync');wire.data=JSON.parse(encoded).data;},processEffects(){effects.push('effects');}};
        const message=new Message(component);message.addAction(action);message.updates={};
        if(messageHooks.has(name))message.addInterceptor(messageHooks.get(name));
        message.addInterceptor(({onSuccess})=>onSuccess(({onMorph})=>onMorph(async()=>effects.push('morph'))));
        pending.push({message,name,params});
    });}
    async function reply(index,result,data=structuredClone(wire.data)){
        const {message}=pending[index];processPayload(message,{snapshot:JSON.stringify({memo:{id:'host'},data}),effects:{returns:[result]}},{},
            {transaction(callback){const task=callback();draining.push(task);return task;}},()=>false,()=>{},callback=>callback());
        await Promise.all(draining);draining=[];await new Promise(resolve=>setImmediate(resolve));
    }
    return {wire,pending,effects,reply,controller:createCatalogImageSaveController({wire,methods:[method]})};
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
