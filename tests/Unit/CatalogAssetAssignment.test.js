import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createAssetAssignmentController, catalogAssetAssignment } from '../../resources/js/catalog-asset-assignment.js';

const map=JSON.parse(readFileSync(new URL('../../vendor/livewire/livewire/dist/livewire.esm.js.map',import.meta.url),'utf8'));
const source=name=>map.sourcesContent[map.sources.indexOf('../js/request/'+name+'.js')];
const interceptor=source('interceptor');
const MessageInterceptor=new Function(interceptor.split('export class RequestInterceptor')[0].replace(/^import .*\n/gm,'').replace('export class MessageInterceptor','class MessageInterceptor')+';return MessageInterceptor;')();
const Message=new Function('MessageInterceptor',source('message').replace(/^import .*\n/gm,'').replace('export default class Message','class Message')+';return Message;')(MessageInterceptor);
const utils=map.sourcesContent[map.sources.indexOf('../js/utils.js')];
const WeakBag=new Function(utils.slice(utils.indexOf('class WeakBag'),utils.indexOf('\nexport function dispatch',utils.indexOf('class WeakBag')))+';return WeakBag;')();
const Registry=new Function('WeakBag','MessageInterceptor',interceptor.slice(interceptor.indexOf('export class InterceptorRegistry')).replace('export class','class')+';return InterceptorRegistry;')(WeakBag,MessageInterceptor);
const request=source('index'),start=request.indexOf('let { snapshot: snapshotEncoded, effects } = payload'),end=request.indexOf('\n                    })',start);
const processPayload=new Function('message','payload','request','Alpine','discardIfSuperseded','supersedeOlderMessages','requestAnimationFrame',request.slice(start,end));
const registry=new Registry(); globalThis.Livewire={interceptMessage:fn=>registry.addMessageInterceptor(fn)}; let hosts=0;
function harness() {
 const pending=[],effects=[];const wire={$id:'review-'+(++hosts),launchSession:'launch',batchId:'batch',previewVersion:1,sourceId:'source',sourceVersion:1,recipeData:{mode:'fill_missing'},candidateData:{},reviewProgress:{stage:'building',revision:0},sourceProgress:{stage:'scanning',revision:0}};
 const data=()=>Object.fromEntries(Object.entries(wire).filter(([key,value])=>!key.startsWith('$') && typeof value!=='function'));
 const component={id:wire.$id,mergeNewSnapshot(encoded){Object.assign(wire,JSON.parse(encoded).data);effects.push('sync');},processEffects(){effects.push('effects');}};
 wire.$call=(method,...params)=>new Promise((resolve,reject)=>{const message=new Message(component);message.addAction({name:method,params,fingerprint:method,rejectPromise:reject,resolvePromise:resolve,invokeOnSuccess(){},invokeOnError(){},invokeOnFinish(){}});message.updates={};message.setInterceptors(registry.getMessageInterceptors(message));pending.push({method,params,message,reject});});
 async function reply(index,result,nextData=data()) { const outstanding=[];processPayload(pending[index].message,{snapshot:JSON.stringify({memo:{id:wire.$id},data:nextData}),effects:{returns:[result]}},{},{transaction(fn){const p=fn();outstanding.push(p);return p;}},()=>false,()=>{},fn=>fn());await Promise.all(outstanding);await new Promise(r=>setImmediate(r)); }
 const controller=createAssetAssignmentController({wire});return {wire,data,pending,effects,controller,reply};
}
test('bounded review requests use committed revision and Stop prevents the next request',async()=>{const h=harness(),run=h.controller.build();assert.equal(h.pending[0].method,'buildReview');h.controller.stop();await h.reply(0,{stage:'building',revision:1},{...h.data(),reviewProgress:{stage:'building',revision:1}});await run;assert.equal(h.pending.length,1);assert.equal(h.wire.reviewProgress.revision,1);h.controller.destroy();});
test('lost committed chunk pauses and read-only refresh never resumes work',async()=>{const h=harness(),run=h.controller.build();h.pending[0].reject(new Error('lost'));await run;assert.equal(h.controller.state().unknown,true);await h.controller.build();assert.equal(h.pending.length,1);const read=h.controller.refresh();assert.equal(h.pending[1].method,'refreshReviewStatus');await h.reply(1,{review:{stage:'building',revision:1},source:{}},{...h.data(),reviewProgress:{stage:'building',revision:1}});await read;assert.equal(h.pending.length,2);assert.equal(h.controller.state().unknown,false);h.controller.destroy();});
test('new recipe rejects stale response before native snapshot and effects',async()=>{const h=harness(),run=h.controller.command('previewReview');h.wire.recipeData.mode='replace_existing';await h.reply(0,{stage:'building',revision:1},{...h.data(),previewVersion:2});await run;assert.deepEqual(h.effects,[]);assert.equal(h.controller.state().unknown,true);h.controller.destroy();});
test('teardown fences an in-flight response and disposes the public registry exactly once',async()=>{const h=harness(),before=registry.messageInterceptorCallbacks.length,run=h.controller.build();h.controller.destroy();h.controller.destroy();await h.reply(0,{stage:'building',revision:1});await run;assert.deepEqual(h.effects,[]);assert.equal(h.pending.length,1);assert.equal(registry.messageInterceptorCallbacks.length,before-1);});
test('foreground scan uses expected source revision and stops at Busy',async()=>{const h=harness(),run=h.controller.scan();assert.equal(h.pending[0].method,'advanceReviewSource');assert.equal(h.pending[0].params[1],0);await h.reply(0,{stage:'scanning',outcome:'busy',revision:0});await run;assert.equal(h.pending.length,1);h.controller.destroy();});
test('native lazy wire is captured once before the control detaches',async()=>{const h=harness();h.controller.destroy();let connected=true;const ui=catalogAssetAssignment({user:1});Object.defineProperty(ui,'$wire',{get(){if(!connected)throw new Error('detached');return h.wire;}});ui.init();const run=ui.build();connected=false;await h.reply(0,{stage:'review',revision:1},{...h.data(),reviewProgress:{stage:'review',revision:1}});await run;assert.equal(ui.unknown,false);ui.destroy();});

test('sequential scan captures committed progress and source version for each next short request',async()=>{
 const h=harness(),run=h.controller.scan();await h.reply(0,{stage:'scanning',revision:1},{...h.data(),sourceProgress:{stage:'scanning',revision:1}});
 assert.equal(h.pending.length,2);assert.equal(h.pending[1].params[1],1);assert.equal(h.pending[1].params[0].source_version,1);
 await h.reply(1,{stage:'ready',revision:2},{...h.data(),sourceProgress:{stage:'ready',revision:2}});await run;assert.equal(h.pending.length,2);h.controller.destroy();
});
test('a foreign native component cannot receive or cancel this review response',async()=>{
 const a=harness(),b=harness(),run=b.controller.build();a.wire.recipeData.mode='other';a.controller.destroy();
 await b.reply(0,{stage:'review',revision:1},{...b.data(),reviewProgress:{stage:'review',revision:1}});await run;
 assert.deepEqual(a.effects,[]);assert.deepEqual(b.effects,['sync','effects']);b.controller.destroy();
});
for(const status of [403,422,500])test(`uncertain HTTP ${status} does not replay a mutation`,async()=>{
 const h=harness(),run=h.controller.command('resolveReviewCandidate',{source_id:'source'});h.pending[0].reject(Object.assign(new Error('unconfirmed'),{status}));await run;
 assert.equal(h.controller.state().unknown,true);await h.controller.command('resolveReviewCandidate',{source_id:'source'});assert.equal(h.pending.length,1);h.controller.destroy();
});

test('failed read-only refresh preserves unknown state and cannot enable a mutation replay',async()=>{
 const h=harness(),run=h.controller.build();h.pending[0].reject(new Error('lost commit response'));await run;
 const read=h.controller.refresh();h.pending[1].reject(new Error('status offline'));await read;
 assert.equal(h.controller.state().unknown,true);await h.controller.build();assert.equal(h.pending.length,2);h.controller.destroy();
});

test('the retained parent reacts to first review Back and its Return binding uses the same review without a build',async()=>{
 const alpineSource=map.sourcesContent[map.sources.indexOf('../node_modules/alpinejs/dist/module.cjs.js')];
 const module={exports:{}};class Element{};class MutationObserver{observe(){}disconnect(){}takeRecords(){return[];}}
 const Alpine=new Function('module','exports','window','document','MutationObserver',alpineSource+'\nreturn module.exports.default;')(module,module.exports,{Element},{createElement:()=>({})},MutationObserver);
 const parentBlade=readFileSync(new URL('../../resources/views/filament/pages/catalog-images.blade.php',import.meta.url),'utf8');
 const expression=parentBlade.match(/<span x-data="\{ lastReview: '' \}" x-effect="([^"]+)"/)[1].replaceAll('{{ auth()->id() }}','17');
 const closeBinding=parentBlade.match(/x-on:catalog-image-review-closed="([^"]+)"/)[1];
 const returnBinding=parentBlade.match(/x-on:click="(command\('returnToImageReview'[^\"]+)"/)[1];
 const values=new Map(),storage={getItem:key=>values.get(key)??null,setItem:(key,value)=>values.set(key,value)};
 const previousStorage=globalThis.localStorage;globalThis.localStorage=storage;
 const parent=Alpine.reactive({imageTarget:'product.main_image',imageReviewLaunch:null}),state=Alpine.reactive({lastReview:''});
 const evaluate=new Function('$wire','localStorage','state','with(state){'+expression+'}');
 const effect=Alpine.effect(()=>evaluate(parent,storage,state));
 const h=harness();h.controller.destroy();h.wire.batchId=null;h.wire.previewVersion=0;h.wire.targetKey='product.main_image';h.wire.operation='folder_matches';
 const ui=catalogAssetAssignment({user:17});ui.$wire=h.wire;
 try{
  parent.imageReviewLaunch={session:'launch'};await Promise.resolve();assert.equal(state.lastReview,'');ui.init();
  const preview=ui.preview();await h.reply(0,{stage:'review',revision:1},{...h.data(),batchId:'launch',previewVersion:1,reviewProgress:{stage:'review',revision:1}});await preview;
  assert.equal(storage.getItem('catalog-image-review:17:product.main_image'),'launch');assert.equal(state.lastReview,'');
  const back=ui.command('backToRecords');assert.equal(h.pending[1].method,'backToRecords');await h.reply(1,undefined);await back;
  new Function('command','$event',closeBinding)((method,session,host)=>{
   assert.deepEqual([method,session,host],['closeImageReview','launch','parent']);parent.imageReviewLaunch=null;
  },{detail:{session:'launch',host:'parent'}});
  await Promise.resolve();assert.equal(state.lastReview,'launch');
  const returned=[];new Function('command','lastReview',returnBinding)((...args)=>returned.push(args),state.lastReview);
  assert.deepEqual(returned,[['returnToImageReview','launch']]);assert.equal(parent.imageTarget,'product.main_image');
  assert.deepEqual(h.pending.map(p=>p.method),['previewReview','backToRecords']);assert.equal(h.wire.reviewProgress.revision,1);
 }finally{Alpine.release(effect);ui.destroy();globalThis.localStorage=previousStorage;}
});
