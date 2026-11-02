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
 const pending=[],effects=[];const wire={$id:'review-'+(++hosts),launchSession:'launch',batchId:'batch',previewVersion:1,sourceId:'source',sourceVersion:1,recipeData:{mode:'fill_missing'},candidateData:{},selectedTableRecords:['1','2'],applyProgress:{accepted:false},reviewProgress:{stage:'building',revision:0},sourceProgress:{stage:'scanning',revision:0}};
 const data=()=>Object.fromEntries(Object.entries(wire).filter(([key,value])=>!key.startsWith('$') && typeof value!=='function'));
 const component={id:wire.$id,mergeNewSnapshot(encoded){Object.assign(wire,JSON.parse(encoded).data);effects.push('sync');},processEffects(){effects.push('effects');}};
 wire.$call=(method,...params)=>new Promise((resolve,reject)=>{const message=new Message(component);message.addAction({name:method,params,fingerprint:method,rejectPromise:reject,resolvePromise:resolve,invokeOnSuccess(){},invokeOnError(){},invokeOnFinish(){}});message.updates={};message.setInterceptors(registry.getMessageInterceptors(message));pending.push({method,params,message,reject});});
 async function reply(index,result,nextData=data()) { const outstanding=[];processPayload(pending[index].message,{snapshot:JSON.stringify({memo:{id:wire.$id},data:nextData}),effects:{returns:[result]}},{},{transaction(fn){const p=fn();outstanding.push(p);return p;}},()=>false,()=>{},fn=>fn());await Promise.all(outstanding);await new Promise(r=>setImmediate(r)); }
 const controller=createAssetAssignmentController({wire});return {wire,data,pending,effects,controller,reply};
}

const ready = (extra={}) => ({acceptance_id:'intent',active_acceptance_id:'intent',pending_intent:null,next_step_id:'step1',...extra});
async function accept(h,run) {
 assert.equal(h.pending[0].method,'prepareImageApply');assert.deepEqual(h.pending[0].params[0],['1','2']);
 await h.reply(0,{pending_intent:{id:'intent'}},{...h.data(),applyProgress:{pending_intent:{id:'intent'}}});
 assert.equal(h.pending[1].method,'applyImages');assert.equal(h.pending[1].params[0],'intent');
 await h.reply(1,ready(),{...h.data(),selectedTableRecords:[],applyProgress:ready()});
 return run;
}
test('Apply accepts exactly one subset then processes only already issued stable steps',async()=>{
 const h=harness(),run=h.controller.apply();await accept(h);
 assert.equal(h.pending[2].method,'processNextImageAssignment');assert.equal(h.pending[2].params[0],'step1');
 await h.reply(2,{outcome:'applied',progress:ready({next_step_id:'step2'})},{...h.data(),applyProgress:ready({next_step_id:'step2'})});
 assert.equal(h.pending[3].params[0],'step2');
 await h.reply(3,{outcome:'applied',progress:ready({next_step_id:null,active_acceptance_id:null})},{...h.data(),applyProgress:ready({next_step_id:null,active_acceptance_id:null})});await run;
 assert.equal(h.pending.length,4);assert.equal(h.controller.state().unknown,false);h.controller.destroy();
});
for(const stage of ['prepare','accept','item'])test(`lost ${stage} response pauses and read-only recovery never replays it`,async()=>{
 const h=harness(),run=h.controller.apply();let index=0;
 if(stage!=='prepare') {await h.reply(0,{pending_intent:{id:'intent'}},{...h.data(),applyProgress:{pending_intent:{id:'intent'}}});index=1;}
 if(stage==='item') {await h.reply(1,ready(),{...h.data(),selectedTableRecords:[],applyProgress:ready()});index=2;}
 h.pending[index].reject(new Error('lost'));await run;assert.equal(h.controller.state().unknown,true);
 const read=h.controller.refresh();await h.reply(index+1,{review:{},source:{},apply:ready()},{...h.data(),applyProgress:ready()});await read;
 assert.equal(h.pending.length,index+2);await h.controller.apply();assert.equal(h.pending.length,index+2);h.controller.destroy();
});
test('Stop during preparation leaves an explicit prepared intent without accepting it',async()=>{
 const h=harness(),run=h.controller.apply();h.controller.stop();await h.reply(0,{pending_intent:{id:'intent'}},{...h.data(),applyProgress:{pending_intent:{id:'intent'}}});await run;assert.equal(h.pending.length,1);h.controller.destroy();
});
test('Stop during an item keeps that commit and schedules no following step',async()=>{
 const h=harness(),run=h.controller.apply();await accept(h);h.controller.stop();await h.reply(2,{outcome:'applied',progress:ready({next_step_id:'step2'})},{...h.data(),applyProgress:ready({next_step_id:'step2'})});await run;assert.equal(h.pending.length,3);h.controller.destroy();
});
test('changing checks before prepare response prevents native sync and acceptance',async()=>{
 const h=harness(),run=h.controller.apply();h.wire.selectedTableRecords=['2'];await h.reply(0,{pending_intent:{id:'intent'}},{...h.data(),applyProgress:{pending_intent:{id:'intent'}}});await run;assert.deepEqual(h.effects,[]);assert.equal(h.pending.length,1);assert.equal(h.controller.state().unknown,true);h.controller.destroy();
});
test('explicit Continue uses the recovered issued step without preparing a new subset',async()=>{
 const h=harness();h.wire.applyProgress=ready({next_step_id:null,recovery_required:true});const run=h.controller.continueApply();assert.equal(h.pending[0].method,'continueImageApply');
 await h.reply(0,ready({next_step_id:'replacement'}),{...h.data(),applyProgress:ready({next_step_id:'replacement'})});assert.equal(h.pending[1].params[0],'replacement');await h.reply(1,{outcome:'busy',progress:ready()});await run;assert.equal(h.pending.length,2);h.controller.destroy();
});

test('a completed step response cannot start a different acceptance visible after reconciliation',async()=>{
 const h=harness(),run=h.controller.apply();await accept(h);
 await h.reply(2,{outcome:'applied',progress:ready({next_step_id:null,active_acceptance_id:'new'})},{...h.data(),applyProgress:{...ready(),acceptance_id:'new',active_acceptance_id:'new',next_step_id:'new-step'}});assert.equal(h.pending.length,3);await run;h.controller.destroy();
});
