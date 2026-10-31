import { test } from 'node:test';
import assert from 'node:assert/strict';

const deferred = () => { let resolve, reject; const promise = new Promise((a,b) => { resolve=a; reject=b; }); return {promise,resolve,reject}; };
async function moduleUnderTest() { return import('../../resources/js/catalog-navigation.js'); }
const card = id => ({id,name:'Model '+id,url:'/dashboard/catalog/groups/'+id,description:null,legacyId:null,productCount:1,mainImage:null,tags:[],presentation:{group_card_content:[],group_image_placement:'left',group_image_scale:'medium'}});
const query = (overrides={}) => ({branchId:null,search:'',tagValueIds:[],mode:'tree',page:1,...overrides});
const reply = (q=query(), overrides={}) => ({requestId:'initial',queryIdentity:'server-only-opaque-hash',query:q,displayVersion:'defaults:v1',treeVersion:'a'.repeat(64),branch:q.branchId===null?null:{id:q.branchId,name:'Branch',parentId:null},ancestors:[],treeRows:[{id:1,name:'Branch',parentId:null,hasChildren:true,modelCount:2}],rootTreePagination:{page:1,nextPage:null,hasMore:false},tags:[{id:1,label:'Pressure',values:[{id:3,label:'001',count:2,selected:false},{id:4,label:'001',count:1,selected:false}]}],cards:[card(2)],total:1,page:1,hasMore:false,presentation:{sidebar_mode:'tree',branch_tag_retention:'keep_available',criteria_container:'responsive'},presentationRecovery:null,...overrides});
async function harness() {
    const {catalogNavigation} = await moduleUnderTest();
    const browser = new EventTarget(); browser.location={href:'https://catalog.test/dashboard/catalog?d[x]=001&external=keep'};
    browser.history={state:{outside:true},pushes:[],replaces:[],pushState(s,t,u){this.state=s;browser.location.href=String(u);this.pushes.push(String(u));},replaceState(s,t,u){this.state=s;browser.location.href=String(u);this.replaces.push(String(u));}};
    let clock=0,timerId=0;const timers=new Map();
    browser.setTimeout=(callback,delay)=>{timers.set(++timerId,{callback,at:clock+delay});return timerId;};
    browser.clearTimeout=id=>timers.delete(id);
    browser.advance=milliseconds=>{clock+=milliseconds;const tasks=[];for(const [id,timer] of [...timers])if(timer.at<=clock){timers.delete(id);tasks.push(timer.callback());}return tasks;};
    browser.innerWidth=1280; browser.scrollY=0; browser.scrollTo=()=>{};
    const requests=[], trees=[];
    const component=catalogNavigation(browser);component.$el={querySelector:()=>({textContent:JSON.stringify(reply())}),dataset:{catalogIndexUrl:'/dashboard/catalog',labels:JSON.stringify({failed:'Failed. Retry.',removed:'Unavailable tags removed',invalid:'Invalid catalog state',treeChanged:'Tree changed. Retry.'})}};
    component.$wire={loadNavigation(q){const d=deferred();requests.push({q,...d});return d.promise;},loadTreeChildren(...args){const d=deferred();trees.push({args,...d});return d.promise;}};
    component.$nextTick=f=>f();component.init();
    return {component,browser,requests,trees};
}
function answer(request,extra={}) { request.resolve(reply(query(request.q),{requestId:request.q.requestId,...extra})); }

test('latest response wins with Unicode and server-normalized identifiers without reproducing PHP hashes', async () => {
    const {component:c,requests,browser}=await harness();
    const first=c.navigate(query({search:'older'})); const second=c.navigate(query({search:'שסתום /',tagValueIds:[4,3]}));
    answer(requests[1],{query:query({search:'שסתום /',tagValueIds:[3,4]}),cards:[card(9)],total:1});await second;
    answer(requests[0],{cards:[card(8)]});await first;
    assert.equal(c.projection.cards[0].id,9);assert.equal(c.criteria.search,'שסתום /');assert.deepEqual(c.criteria.tagValueIds,[3,4]);
    assert.match(browser.location.href,/external=keep/);assert.match(browser.location.href,/d%5Bx%5D=001|d\[x\]=001/);assert.equal(browser.history.state.outside,true);c.destroy();
});

test('branch retention removes unavailable IDs once while zero result combinations remain selected',async()=>{
    const {component:c,requests}=await harness();c.criteria=query({tagValueIds:[3,4]});
    const task=c.chooseBranch(1);answer(requests[0],{tags:[{id:1,label:'Pressure',values:[{id:3,label:'001',count:1}]}],total:0,cards:[]});
    await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,2);assert.deepEqual(requests[1].q.tagValueIds,[3]);
    answer(requests[1],{cards:[],total:0,tags:[{id:1,label:'Pressure',values:[{id:3,label:'001',count:1}]}]});await task;
    assert.deepEqual(c.criteria.tagValueIds,[3]);assert.equal(c.projection.total,0);assert.ok(c.notice);c.destroy();
});

test('disclosure pages are independent, duplicates guarded and plus never changes criteria/history',async()=>{
    const {component:c,trees,browser}=await harness();const before=JSON.stringify(c.criteria);
    const open=c.toggleTree(c.projection.treeRows[0]);assert.equal(trees.length,1);
    trees[0].resolve({requestId:trees[0].args[3],parentId:1,page:1,treeVersion:'a'.repeat(64),status:'ready',rows:[{id:2,parentId:1,name:'Model',hasChildren:false,modelCount:1}],nextPage:2,hasMore:true});await open;
    assert.deepEqual(c.visibleTreeRows.map(x=>x.id),[1,2]);assert.equal(JSON.stringify(c.criteria),before);assert.equal(browser.history.pushes.length,0);
    const more=c.moreTree(1);const duplicate=c.moreTree(1);assert.equal(trees.length,2);
    trees[1].resolve({requestId:trees[1].args[3],parentId:1,page:2,treeVersion:'a'.repeat(64),status:'ready',rows:[{id:3,parentId:1,name:'Other',hasChildren:false,modelCount:1}],nextPage:null,hasMore:false});await Promise.all([more,duplicate]);assert.deepEqual(c.visibleTreeRows.map(x=>x.id),[1,2,3]);c.destroy();
});

test('failed or malformed responses retain settled cards and teardown ignores outstanding replies',async()=>{
    const {component:c,requests}=await harness();const failed=c.navigate(query({search:'missing'}));requests[0].reject(new Error('offline'));await failed;
    assert.equal(c.projection.cards[0].id,2);assert.equal(c.loading,false);assert.ok(c.error);
    const malformed=c.navigate(query());requests[1].resolve({requestId:requests[1].q.requestId,cards:[]});await malformed;assert.equal(c.projection.cards[0].id,2);
    const pending=c.navigate(query({search:'late'}));c.destroy();answer(requests[2],{cards:[card(99)]});await pending;assert.equal(c.projection.cards[0].id,2);
});

test('history restoration keeps tags even under clear-on-new-branch and adds no history entry',async()=>{
    const {component:c,requests,browser}=await harness();c.projection.presentation.branch_tag_retention='clear';
    browser.location.href='https://catalog.test/dashboard/catalog?g[version]=1&g[branch]=1&g[tags][]=3&g[mode]=tags';
    const task=c.restore();assert.deepEqual(requests[0].q.tagValueIds,[3]);answer(requests[0]);await task;
    assert.deepEqual(c.criteria.tagValueIds,[3]);assert.equal(c.criteria.mode,'tags');assert.equal(browser.history.pushes.length,0);c.destroy();
});

test('two parents expand independently and late mode replies cannot append children',async()=>{
    const {component:c,trees,requests}=await harness();c.projection.treeRows.push({id:10,name:'Second branch',parentId:null,hasChildren:true,modelCount:1});c.seedTree();
    const one=c.toggleTree(c.projection.treeRows[0]),two=c.toggleTree(c.projection.treeRows[1]);
    for(const index of [1,0])trees[index].resolve({requestId:trees[index].args[3],parentId:trees[index].args[0],page:1,treeVersion:'a'.repeat(64),status:'ready',rows:[{id:index===0?2:11,parentId:trees[index].args[0],name:'Child',hasChildren:false,modelCount:1}],nextPage:null,hasMore:false});
    await Promise.all([one,two]);assert.deepEqual(c.visibleTreeRows.map(x=>x.id),[1,2,10,11]);
    delete c.treePages[1];c.expanded[1]=false;const late=c.toggleTree(c.projection.treeRows[0]);const mode=c.chooseMode('tags');answer(requests[0]);await mode;
    trees[2].resolve({requestId:trees[2].args[3],parentId:1,page:1,treeVersion:'a'.repeat(64),status:'ready',rows:[{id:99,parentId:1}],nextPage:null,hasMore:false});await late;
    assert.equal(c.visibleTreeRows.some(x=>x.id===99),false);c.destroy();
});

test('tree mismatch refreshes once without appending and persistent mismatch requires an explicit retry',async()=>{
    const {component:c,trees,requests}=await harness();c.criteria=query({search:'valve',tagValueIds:[3]});
    const open=c.toggleTree(c.projection.treeRows[0]);trees[0].resolve({requestId:trees[0].args[3],parentId:1,page:1,treeVersion:'b'.repeat(64),status:'refresh_required',rows:[]});
    await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,1);assert.equal(requests[0].q.search,'valve');assert.deepEqual(requests[0].q.tagValueIds,[3]);answer(requests[0],{treeVersion:'b'.repeat(64)});await open;
    assert.deepEqual(c.visibleTreeRows.map(x=>x.id),[1]);const again=c.toggleTree(c.projection.treeRows[0]);trees[1].resolve({requestId:trees[1].args[3],parentId:1,page:1,treeVersion:'c'.repeat(64),status:'refresh_required',rows:[]});await again;
    assert.ok(c.treeErrors[1]);assert.equal(requests.length,1);
    const retry=c.retryTree(1);trees[2].resolve({requestId:trees[2].args[3],parentId:1,page:1,treeVersion:'c'.repeat(64),status:'refresh_required',rows:[]});
    await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,2);answer(requests[1],{treeVersion:'c'.repeat(64)});await retry;c.destroy();
});

test('history restores recorded scroll and focus without touching unrelated state or pushing',async()=>{
    const {component:c,requests,browser}=await harness();let focused=false,scroll;
    browser.document={activeElement:{dataset:{catalogFocus:'model-2'}}};browser.scrollY=420;
    c.root.querySelectorAll=()=>[{dataset:{catalogFocus:'model-2'},focus(){focused=true;}}];browser.scrollTo=options=>{scroll=options.top;};
    c.persistPosition();assert.equal(browser.history.state.outside,true);
    const task=c.restore();answer(requests[0]);await task;assert.equal(focused,true);assert.equal(scroll,420);assert.equal(browser.history.pushes.length,0);c.destroy();
});

test('URL parsing rejects obsolete versions duplicate IDs and malformed canonical page values',async()=>{
    const {navigationQueryFromUrl,normalizeNavigationQuery}=await moduleUnderTest();
    for(const suffix of ['g[version]=9','g[version]=1&g[page]=01','g[version]=1&g[tags][]=3&g[tags][]=3','g[version]=1&g[unknown]=x'])assert.throws(()=>navigationQueryFromUrl('https://catalog.test/?'+suffix));
    assert.equal(navigationQueryFromUrl('https://catalog.test/?d[x]=001','tags').mode,'tags');assert.throws(()=>normalizeNavigationQuery(query({page:true})));
});

test('rapid tag choices accumulate pending intent while only the newest reply can publish',async()=>{
    const {component:c,requests}=await harness();const first=c.toggleTag(3);const second=c.toggleTag(4);
    assert.deepEqual(requests[1].q.tagValueIds,[3,4]);answer(requests[1]);await second;answer(requests[0]);await first;
    assert.deepEqual(c.criteria.tagValueIds,[3,4]);c.destroy();
});

test('repeated initialization and disposed history events do not issue duplicate requests',async()=>{
    const {component:c,requests,browser}=await harness();c.init();browser.dispatchEvent(new Event('popstate'));assert.equal(requests.length,1);answer(requests[0]);await new Promise(resolve=>setImmediate(resolve));c.destroy();browser.dispatchEvent(new Event('popstate'));assert.equal(requests.length,1);
});

test('a no-op navigation does not add a history entry',async()=>{
    const {component:c,requests,browser}=await harness();const task=c.navigate(query());answer(requests[0]);await task;assert.equal(browser.history.pushes.length,0);c.destroy();
});

test('malformed card shape and inconsistent page totals cannot replace the settled view',async()=>{
    const {component:c,requests}=await harness();const task=c.navigate(query({search:'bad'}));answer(requests[0],{cards:[{id:9}],total:1});await task;assert.equal(c.projection.cards[0].id,2);assert.ok(c.error);c.destroy();
});


test('K3-STATE-SEARCH-001 an earlier reply cannot overwrite a newer Unicode draft before debounce',async()=>{
    const {component:c,requests,browser}=await harness();const old=c.navigate(query({search:'older'}));
    c.searchText='שסתום / חדש';c.searchLater();answer(requests[0],{cards:[card(8)]});await old;
    assert.equal(c.searchText,'שסתום / חדש');const [task]=browser.advance(250);
    assert.equal(requests[1].q.search,'שסתום / חדש');answer(requests[1],{cards:[card(9)]});await task;
    assert.equal(c.searchText,'שסתום / חדש');assert.equal(c.projection.cards[0].id,9);assert.equal(new URL(browser.location.href).searchParams.get('g[q]'),'שסתום / חדש');c.destroy();
});

for(const action of ['branch','tag','mode'])for(const order of ['reply-before-timer','reply-after-timer'])test(`K3-STATE-SEARCH-001 ${action} consumes draft once (${order})`,async()=>{
    const {component:c,requests,browser}=await harness();c.searchText='valve / שסתום';c.searchLater();
    const task=action==='branch'?c.chooseBranch(1):action==='tag'?c.toggleTag(3):c.chooseMode('tags');
    assert.equal(requests[0].q.search,'valve / שסתום');
    if(order==='reply-after-timer')assert.deepEqual(browser.advance(250),[]);
    answer(requests[0],{cards:[card(9)]});await task;
    if(order==='reply-before-timer')assert.deepEqual(browser.advance(250),[]);
    assert.equal(requests.length,1);assert.equal(c.searchText,'valve / שסתום');assert.equal(c.projection.cards[0].id,9);assert.equal(new URL(browser.location.href).searchParams.get('g[q]'),'valve / שסתום');c.destroy();
});

test('K3-STATE-SEARCH-001 Enter cancels debounce and a later typed draft still wins',async()=>{
    const {component:c,requests,browser}=await harness();c.searchText='first';c.searchLater();const enter=c.searchNow();assert.deepEqual(browser.advance(250),[]);
    c.searchText='second / חדש';c.searchLater();answer(requests[0]);await enter;assert.equal(c.searchText,'second / חדש');
    const [task]=browser.advance(250);answer(requests[1],{cards:[card(9)]});await task;assert.equal(c.criteria.search,'second / חדש');assert.equal(new URL(browser.location.href).searchParams.get('g[q]'),'second / חדש');c.destroy();
});

for(const branch of [null,1])for(const pending of [false,true])test(`K3-STATE-BRANCH-001 reselection is a no-op (${branch}, pending=${pending})`,async()=>{
    const {component:c,requests,browser}=await harness();c.projection.presentation.branch_tag_retention='clear';const retained=query({branchId:branch,search:'retain / שסתום',tagValueIds:[3],page:3});let task;
    if(pending)task=c.navigate(retained);else c.criteria=retained;
    c.searchText=retained.search;const before=JSON.stringify({criteria:c.criteria,pending:c.pendingQuery,input:c.searchText,loading:c.loading});const count=requests.length;
    const reselection=c.chooseBranch(branch===null?null:String(branch));assert.equal(requests.length,count);await reselection;assert.equal(JSON.stringify({criteria:c.criteria,pending:c.pendingQuery,input:c.searchText,loading:c.loading}),before);assert.equal(browser.history.pushes.length,0);
    if(task){answer(requests[0],{total:49,page:3,cards:[card(49)]});await task;assert.equal(c.criteria.page,3);assert.deepEqual(c.criteria.tagValueIds,[3]);}c.destroy();
});

for(const policy of ['clear','keep_available'])test(`K3-STATE-BRANCH-001 only actual transitions apply ${policy} entry behavior`,async()=>{
    const {component:c,requests,browser}=await harness();c.criteria=query({branchId:1,tagValueIds:[3],page:3});c.projection.presentation.branch_tag_retention=policy;c.searchLater('new / שסתום');
    const task=c.chooseBranch('2');assert.equal(requests[0].q.branchId,2);assert.equal(requests[0].q.page,1);assert.equal(requests[0].q.search,'new / שסתום');assert.deepEqual(requests[0].q.tagValueIds,policy==='clear'?[]:[3]);assert.deepEqual(browser.advance(250),[]);answer(requests[0]);await task;assert.equal(c.criteria.branchId,2);assert.equal(new URL(browser.location.href).searchParams.get('g[q]'),'new / שסתום');c.destroy();
});

const rawGroupCases=[
    ['indexed','g[version]=1&g[tags][0]=31&g[tags][1]=47',true],
    ['append','g[version]=1&g[tags][]=31&g[tags][]=47',true],
    ['reversed contiguous','g[version]=1&g[tags][1]=47&g[tags][0]=31',true],
    ['scalar','g=bad',false],['bare scalar','g',false],['scalar then map','g=bad&g[version]=1',false],
    ['repeated scalar','g=bad&g=worse',false],['repeated version','g[version]=1&g[version]=1',false],
    ['repeated search','g[version]=1&g[q]=one&g[q]=two',false],
    ['duplicate index','g[version]=1&g[tags][0]=31&g[tags][0]=47',false],
    ['sparse index','g[version]=1&g[tags][0]=31&g[tags][2]=47',false],
    ['leading-zero index','g[version]=1&g[tags][00]=31&g[tags][1]=47',false],
    ['mixed append and index','g[version]=1&g[tags][0]=31&g[tags][]=47',false],
    ['duplicate value','g[version]=1&g[tags][]=31&g[tags][]=31',false],
    ['scalar tags','g[version]=1&g[tags]=31',false],['unknown','g[version]=1&g[future]=bad',false],
];
for(const [name,raw,valid] of rawGroupCases)test(`K3-URL-CODEC-001 raw history ${name}`,async()=>{
    const {navigationQueryFromUrl}=await moduleUnderTest();const url='https://catalog.test/?'+raw;
    if(valid)assert.deepEqual(navigationQueryFromUrl(url).tagValueIds,[31,47]);else assert.throws(()=>navigationQueryFromUrl(url));
});

test('K3-URL-CODEC-001 invalid restoration cannot widen results and canonical cleanup removes scalar g too',async()=>{
    const {component:c,requests,browser}=await harness();c.criteria=query({branchId:1,tagValueIds:[3]});browser.location.href='https://catalog.test/?g=bad&external=keep&d[x]=001';
    const restoration=c.restore();assert.equal(requests.length,0);await restoration;assert.equal(c.projection.cards[0].id,2);assert.ok(c.error);assert.equal(c.criteria.branchId,1);assert.deepEqual(c.criteria.tagValueIds,[3]);
    const task=c.chooseBranch(2);answer(requests[0]);await task;const url=new URL(browser.location.href);assert.equal(url.searchParams.has('g'),false);assert.equal(url.searchParams.get('external'),'keep');assert.equal(url.searchParams.get('d[x]'),'001');c.destroy();
});

test('K3-UI-FOCUS-001 close waits for reactive inert removal before returning focus',async()=>{
    const {component:c}=await harness();const jobs=[];let inert=true,focused=0;c.criteriaOpen=true;c.narrow=true;
    c.$nextTick=callback=>jobs.push(callback);c.$refs={criteriaTrigger:{isConnected:true,disabled:false,getClientRects:()=>[{}],closest:()=>inert?{}:null,focus(){assert.equal(inert,false);focused++;}}};
    c.closeCriteria();assert.equal(c.criteriaOpen,false);assert.equal(focused,0);assert.equal(jobs.length,1);
    inert=false;jobs.shift()();assert.equal(focused,1);c.destroy();
});

for(const state of ['detached','disabled','hidden','still-inert','reopened','destroyed'])test(`K3-UI-FOCUS-001 unusable opener is skipped (${state})`,async()=>{
    const {component:c}=await harness();const jobs=[];let focused=0;c.criteriaOpen=true;c.$nextTick=callback=>jobs.push(callback);
    c.$refs={criteriaTrigger:{isConnected:state!=='detached',disabled:state==='disabled',getClientRects:()=>state==='hidden'?[]:[{}],closest:()=>state==='still-inert'?{}:null,focus(){focused++;}}};
    c.closeCriteria();if(state==='reopened')c.criteriaOpen=true;if(state==='destroyed')c.destroy();jobs.forEach(callback=>callback());assert.equal(focused,0);c.destroy();
});

test('K3-RETENTION-FEEDBACK-001 removed same-label choices carry category and exact ID with fallback',async()=>{
    const {component:c,requests}=await harness();c.criteria=query({tagValueIds:[3,4,7]});const task=c.chooseBranch(1);
    const tags=[{id:1,label:'Pressure',values:[{id:3,label:'001',count:1}]}];answer(requests[0],{tags,cards:[],total:0});await new Promise(resolve=>setImmediate(resolve));
    assert.deepEqual(requests[1].q.tagValueIds,[3]);assert.equal(c.notice,'');answer(requests[1],{tags,cards:[],total:0});await task;
    assert.match(c.notice,/Pressure.*001.*#4/);assert.match(c.notice,/#7/);assert.doesNotMatch(c.notice,/#3/);assert.deepEqual(c.criteria.tagValueIds,[3]);assert.equal(c.projection.total,0);c.destroy();
});

test('K3-RETENTION-FEEDBACK-001 stale removal response cannot publish its notice',async()=>{
    const {component:c,requests}=await harness();c.criteria=query({tagValueIds:[3,4]});const old=c.chooseBranch(1);const tags=[{id:1,label:'Pressure',values:[{id:3,label:'001',count:1}]}];answer(requests[0],{tags});await new Promise(resolve=>setImmediate(resolve));
    const newer=c.chooseBranch(2);answer(requests[2]);await newer;answer(requests[1],{tags});await old;
    assert.equal(c.criteria.branchId,2);assert.equal(c.notice,'');c.destroy();
});

test('K3-RETENTION-FEEDBACK-001 no removal produces no reconciliation or notice',async()=>{
    const {component:c,requests}=await harness();c.criteria=query({tagValueIds:[3,4]});const task=c.chooseBranch(1);answer(requests[0]);await task;assert.equal(requests.length,1);assert.equal(c.notice,'');assert.deepEqual(c.criteria.tagValueIds,[3,4]);c.destroy();
});


test('K3-STATE-SEARCH-001 timer-submitted draft survives an older reply arriving afterward',async()=>{
    const {component:c,requests,browser}=await harness();const old=c.navigate(query({search:'old'}));c.searchLater('new / חדש');const [fresh]=browser.advance(250);
    answer(requests[0],{cards:[card(8)]});await old;assert.equal(c.searchText,'new / חדש');assert.equal(c.loading,true);
    answer(requests[1],{cards:[card(9)]});await fresh;assert.equal(c.projection.cards[0].id,9);assert.equal(new URL(browser.location.href).searchParams.get('g[q]'),'new / חדש');c.destroy();
});

test('K3-STATE-BRANCH-001 same root preserves draft debounce and never applies clear-entry behavior',async()=>{
    const {component:c,requests,browser}=await harness();c.criteria=query({tagValueIds:[3],page:3});c.projection.presentation.branch_tag_retention='clear';c.searchLater('new / חדש');
    await c.chooseBranch(null);assert.equal(requests.length,0);assert.equal(c.criteria.page,3);assert.deepEqual(c.criteria.tagValueIds,[3]);assert.equal(browser.history.pushes.length,0);
    const [task]=browser.advance(250);assert.equal(requests[0].q.search,'new / חדש');assert.deepEqual(requests[0].q.tagValueIds,[3]);answer(requests[0]);await task;c.destroy();
});

const mismatch = request => request.resolve({requestId:request.args[3],parentId:request.args[0],page:request.args[1],treeVersion:'b'.repeat(64),status:'refresh_required',rows:[]});
for(const order of ['graph-first','search-first'])test(`K3-TREE-HISTORY-001 graph recovery preserves a pending Unicode debounce (${order})`,async()=>{
    const {component:c,requests,trees,browser}=await harness();const tree=c.toggleTree(c.projection.treeRows[0]);c.searchLater('valve / שסתום');mismatch(trees[0]);await new Promise(resolve=>setImmediate(resolve));
    assert.equal(requests[0].q.search,'');assert.equal(c.searchText,'valve / שסתום');
    if(order==='graph-first'){answer(requests[0],{treeVersion:'b'.repeat(64)});await tree;}
    const tasks=browser.advance(250);assert.equal(tasks.length,1);assert.equal(requests[1].q.search,'valve / שסתום');answer(requests[1],{treeVersion:'b'.repeat(64),cards:[card(9)]});await tasks[0];
    if(order==='search-first'){answer(requests[0],{treeVersion:'a'.repeat(64),cards:[card(8)]});await tree;}
    assert.equal(c.searchText,'valve / שסתום');assert.equal(c.criteria.search,'valve / שסתום');assert.equal(c.projection.cards[0].id,9);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,0);assert.equal(browser.history.replaces.length,2);assert.deepEqual(browser.advance(1000),[]);assert.equal(requests.length,2);c.destroy();
});
function navigationQueryFromUrlForTest(browser) { const p=new URL(browser.location.href).searchParams;return query({branchId:p.has('g[branch]')?Number(p.get('g[branch]')):null,search:p.get('g[q]') || '',tagValueIds:p.getAll('g[tags][]').map(Number).sort((a,b)=>a-b),mode:p.get('g[mode]') || 'tree',page:Number(p.get('g[page]') || 1)}); }

for(const action of ['branch','tag'])for(const order of ['original-first','recovery-first'])test(`K3-TREE-HISTORY-001 ${action} push survives recovery (${order})`,async()=>{
    const {component:c,requests,trees,browser}=await harness();const tree=c.toggleTree(c.projection.treeRows[0]);const user=action==='branch'?c.chooseBranch(1):c.toggleTag(3);mismatch(trees[0]);await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,2);const {requestId:originalId,...originalQuery}=requests[0].q;const {requestId:recoveryId,...recoveryQuery}=requests[1].q;assert.deepEqual(recoveryQuery,originalQuery);
    if(order==='original-first'){answer(requests[0],{cards:[card(8)]});await user;assert.equal(browser.history.pushes.length,0);}
    answer(requests[1],{treeVersion:'b'.repeat(64),cards:[card(9)]});await tree;
    if(order==='recovery-first'){answer(requests[0],{cards:[card(8)]});await user;}
    assert.equal(c.projection.cards[0].id,9);assert.equal(browser.history.pushes.length,1);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.deepEqual(browser.advance(1000),[]);assert.equal(requests.length,2);c.destroy();
});

test('K3-TREE-HISTORY-001 a newer draft during branch recovery commits the surviving branch push once',async()=>{
    const {component:c,requests,trees,browser}=await harness();const tree=c.toggleTree(c.projection.treeRows[0]);const branch=c.chooseBranch(1);mismatch(trees[0]);await new Promise(resolve=>setImmediate(resolve));c.searchLater('latest / חדש');const [search]=browser.advance(250);
    assert.equal(requests[2].q.branchId,1);assert.equal(requests[2].q.search,'latest / חדש');answer(requests[2],{treeVersion:'b'.repeat(64),cards:[card(9)]});await search;answer(requests[0],{cards:[card(8)]});answer(requests[1],{cards:[card(8)]});await Promise.all([tree,branch]);
    assert.equal(c.searchText,'latest / חדש');assert.equal(c.projection.cards[0].id,9);assert.equal(browser.history.pushes.length,1);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.deepEqual(browser.advance(1000),[]);c.destroy();
});

test('K3-TREE-HISTORY-001 pure graph recovery adds no history and changes no query',async()=>{
    const {component:c,requests,trees,browser}=await harness();c.criteria=query({search:'settled',tagValueIds:[3]});c.searchText='settled';browser.location.href='https://catalog.test/dashboard/catalog?g[version]=1&g[q]=settled&g[tags][]=3&g[mode]=tree';const before=browser.location.href;
    const tree=c.toggleTree(c.projection.treeRows[0]);mismatch(trees[0]);await new Promise(resolve=>setImmediate(resolve));answer(requests[0],{treeVersion:'b'.repeat(64),cards:[card(9)]});await tree;
    assert.deepEqual(c.criteria,query({search:'settled',tagValueIds:[3]}));assert.equal(browser.location.href,before);assert.equal(browser.history.pushes.length,0);assert.equal(browser.history.replaces.length,0);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);c.destroy();
});

test('K3-TREE-HISTORY-001 recovery retains pending removal metadata and a single canonical branch entry',async()=>{
    const {component:c,requests,trees,browser}=await harness();c.criteria=query({tagValueIds:[3,4]});const tree=c.toggleTree(c.projection.treeRows[0]);const branch=c.chooseBranch(1);const tags=[{id:1,label:'Pressure',values:[{id:3,label:'001',count:1}]}];answer(requests[0],{tags,cards:[],total:0});await new Promise(resolve=>setImmediate(resolve));mismatch(trees[0]);await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,3);
    answer(requests[2],{treeVersion:'b'.repeat(64),tags,cards:[],total:0});await tree;answer(requests[1],{tags,cards:[],total:0});await branch;
    assert.deepEqual(c.criteria.tagValueIds,[3]);assert.match(c.notice,/Pressure.*001.*#4/);assert.equal(browser.history.pushes.length,1);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);c.destroy();
});

test('K3-TREE-HISTORY-001 explicit refresh commits a consumed draft instead of leaving an old URL',async()=>{
    const {component:c,requests,browser}=await harness();c.searchLater('refresh / חדש');const task=c.refresh();assert.equal(requests[0].q.search,'refresh / חדש');assert.deepEqual(browser.advance(250),[]);answer(requests[0],{cards:[card(9)]});await task;
    assert.equal(c.projection.cards[0].id,9);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,0);c.destroy();
});

test('K3-TREE-HISTORY-001 explicit refresh preserves the pending branch push',async()=>{
    const {component:c,requests,browser}=await harness();const branch=c.chooseBranch(1);const refresh=c.refresh();answer(requests[1],{cards:[card(9)]});await refresh;answer(requests[0],{cards:[card(8)]});await branch;
    assert.equal(c.projection.cards[0].id,9);assert.equal(browser.history.pushes.length,1);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);c.destroy();
});

test('K3-TREE-HISTORY-001 a history restoration invalidates older tree recovery callbacks',async()=>{
    const {component:c,requests,trees,browser}=await harness();const tree=c.toggleTree(c.projection.treeRows[0]);const branch=c.chooseBranch(1);browser.location.href='https://catalog.test/dashboard/catalog?g=bad';await c.restore();assert.ok(c.error);
    mismatch(trees[0]);await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,1);answer(requests[0]);await Promise.all([tree,branch]);assert.ok(c.error);assert.equal(c.criteria.branchId,null);assert.equal(browser.history.pushes.length,0);c.destroy();
});

function pendingRetentionFixture(c,browser) {
    c.criteria=query({tagValueIds:[3,7]});c.projection.query=c.criteria;
    c.projection.tags=[{id:1,label:'Pressure',values:[{id:3,label:'001',count:2},{id:4,label:'001',count:1}]},{id:2,label:'Color',values:[{id:7,label:'Blue',count:1},{id:8,label:'Red',count:1}]}];
    browser.location.href='https://catalog.test/dashboard/catalog?g[version]=1&g[tags][]=3&g[tags][]=7&g[mode]=tree&external=keep&d[x]=001';
}
function destinationReply(request,count=1) {
    // Values7/8 are globally valid Color values, but absent from this branch.
    // CategoryAND gives0 until the transition removes them; Pressure3 then matches.
    const total=request.q.tagValueIds.some(id=>id===7 || id===8)?0:count;
    const offset=(request.q.page-1)*24;const cards=Array.from({length:Math.min(24,Math.max(0,total-offset))},(_,index)=>({...card(total===1?9:offset+index+1),name:'valve / חדש'}));
    answer(request,{treeVersion:'b'.repeat(64),tags:[{id:1,label:'Pressure',values:[{id:3,label:'001',count}]}],cards,total,page:request.q.page,hasMore:request.q.page<Math.ceil(total/24)});
}

test('K3-PENDING-RETENTION-001 search during branch graph recovery removes the unavailable other-category value',async()=>{
    const {component:c,requests,trees,browser}=await harness();pendingRetentionFixture(c,browser);const tree=c.toggleTree(c.projection.treeRows[0]);const branch=c.chooseBranch(1);mismatch(trees[0]);await new Promise(resolve=>setImmediate(resolve));c.searchLater('חדש');const [search]=browser.advance(250);
    assert.deepEqual(requests[2].q.tagValueIds,[3,7]);destinationReply(requests[2]);await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,4);assert.deepEqual(requests[3].q.tagValueIds,[3]);assert.equal(requests[3].q.search,'חדש');destinationReply(requests[3]);await search;
    destinationReply(requests[0]);destinationReply(requests[1]);await Promise.all([tree,branch]);assert.equal(c.projection.cards[0].id,9);assert.equal(c.searchText,'חדש');assert.deepEqual(c.criteria.tagValueIds,[3]);assert.match(c.notice,/Color.*Blue.*#7/);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,1);assert.deepEqual(browser.advance(1000),[]);c.destroy();
});

test('K3-PENDING-RETENTION-001 a newer search keeps an already pending removal identity and consumed budget',async()=>{
    const {component:c,requests,trees,browser}=await harness();pendingRetentionFixture(c,browser);const tree=c.toggleTree(c.projection.treeRows[0]);const branch=c.chooseBranch(1);destinationReply(requests[0]);await new Promise(resolve=>setImmediate(resolve));assert.deepEqual(requests[1].q.tagValueIds,[3]);mismatch(trees[0]);await new Promise(resolve=>setImmediate(resolve));c.searchLater('חדש');const [search]=browser.advance(250);
    assert.deepEqual(requests[3].q.tagValueIds,[3]);destinationReply(requests[3]);await search;assert.equal(requests.length,4);destinationReply(requests[1]);destinationReply(requests[2]);await Promise.all([tree,branch]);assert.equal(c.projection.cards[0].id,9);assert.match(c.notice,/Color.*Blue.*#7/);assert.equal(c.searchText,'חדש');assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,1);c.destroy();
});

for(const action of ['mode','tag','page'])test(`K3-PENDING-RETENTION-001 ${action} continues the pending destination availability check`,async()=>{
    const {component:c,requests,browser}=await harness();pendingRetentionFixture(c,browser);const branch=c.chooseBranch(1);const continued=action==='mode'?c.chooseMode('tags'):action==='tag'?c.toggleTag(4):c.page(2);destinationReply(requests[1],action==='page'?25:1);await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,3);assert.deepEqual(requests[2].q.tagValueIds,[3]);destinationReply(requests[2],action==='page'?25:1);await continued;destinationReply(requests[0]);await branch;
    assert.equal(c.projection.cards[0].id,action==='page'?25:9);assert.match(c.notice,/Color.*Blue.*#7/);if(action==='tag')assert.match(c.notice,/Pressure.*001.*#4/);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,1);c.destroy();
});

test('K3-PENDING-RETENTION-001 retry retains the failed branch transition availability policy',async()=>{
    const {component:c,requests,browser}=await harness();pendingRetentionFixture(c,browser);const branch=c.chooseBranch(1);requests[0].reject(new Error('offline'));await branch;assert.ok(c.error);const retry=c.retry();destinationReply(requests[1]);await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,3);assert.deepEqual(requests[2].q.tagValueIds,[3]);destinationReply(requests[2]);await retry;
    assert.equal(c.projection.cards[0].id,9);assert.match(c.notice,/Color.*Blue.*#7/);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,1);c.destroy();
});

test('K3-PENDING-RETENTION-001 refresh preserves a pending removal notice without a duplicate reconciliation',async()=>{
    const {component:c,requests,browser}=await harness();pendingRetentionFixture(c,browser);const branch=c.chooseBranch(1);destinationReply(requests[0]);await new Promise(resolve=>setImmediate(resolve));const refresh=c.refresh();destinationReply(requests[2]);await refresh;assert.equal(requests.length,3);destinationReply(requests[1]);await branch;
    assert.equal(c.projection.cards[0].id,9);assert.match(c.notice,/Color.*Blue.*#7/);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,1);c.destroy();
});

test('K3-PENDING-RETENTION-001 a genuinely new tag set gets one check and merges earlier removal identities',async()=>{
    const {component:c,requests,browser}=await harness();pendingRetentionFixture(c,browser);const branch=c.chooseBranch(1);destinationReply(requests[0]);await new Promise(resolve=>setImmediate(resolve));const tag=c.toggleTag(8);assert.deepEqual(requests[2].q.tagValueIds,[3,8]);destinationReply(requests[2]);await new Promise(resolve=>setImmediate(resolve));assert.equal(requests.length,4);assert.deepEqual(requests[3].q.tagValueIds,[3]);destinationReply(requests[3]);await tag;destinationReply(requests[1]);await branch;
    assert.match(c.notice,/Color.*Blue.*#7/);assert.match(c.notice,/Color.*Red.*#8/);assert.equal(c.projection.cards[0].id,9);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,1);c.destroy();
});

test('K3-PENDING-RETENTION-001 explicit reselection removes obsolete removal feedback when the value is now available',async()=>{
    const {component:c,requests,browser}=await harness();pendingRetentionFixture(c,browser);const branch=c.chooseBranch(1);destinationReply(requests[0]);await new Promise(resolve=>setImmediate(resolve));const selected=c.toggleTag(7);assert.deepEqual(requests[2].q.tagValueIds,[3,7]);answer(requests[2],{treeVersion:'b'.repeat(64),tags:c.projection.tags,cards:[card(9)]});await selected;destinationReply(requests[1]);await branch;
    assert.deepEqual(c.criteria.tagValueIds,[3,7]);assert.equal(c.notice,'');assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,1);c.destroy();
});

for(const boundary of ['new-branch','reset','history'])test(`K3-PENDING-RETENTION-001 ${boundary} starts fresh without leaking an old branch notice`,async()=>{
    const {component:c,requests,browser}=await harness();pendingRetentionFixture(c,browser);const branch=c.chooseBranch(1);destinationReply(requests[0]);await new Promise(resolve=>setImmediate(resolve));let fresh;
    if(boundary==='new-branch')fresh=c.chooseBranch(2);else if(boundary==='reset')fresh=c.reset();else {browser.location.href='https://catalog.test/dashboard/catalog?g[version]=1&g[branch]=2&g[tags][]=3&g[mode]=tree';fresh=c.restore();}
    destinationReply(requests[2]);await fresh;assert.equal(requests.length,3);destinationReply(requests[1]);await branch;
    assert.equal(c.notice,'');assert.equal(c.criteria.branchId,boundary==='reset'?null:2);assert.deepEqual(c.criteria.tagValueIds,boundary==='reset'?[]:[3]);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);assert.equal(browser.history.pushes.length,boundary==='history'?0:1);c.destroy();
});

test('K3-PENDING-RETENTION-001 clear-entry policy is not replayed on a later tag intent',async()=>{
    const {component:c,requests,browser}=await harness();pendingRetentionFixture(c,browser);c.projection.presentation.branch_tag_retention='clear';const branch=c.chooseBranch(1);assert.deepEqual(requests[0].q.tagValueIds,[]);const tag=c.toggleTag(3);assert.deepEqual(requests[1].q.tagValueIds,[3]);destinationReply(requests[1]);await tag;destinationReply(requests[0]);await branch;
    assert.deepEqual(c.criteria.tagValueIds,[3]);assert.equal(c.projection.cards[0].id,9);assert.equal(c.notice,'');assert.equal(browser.history.pushes.length,1);c.destroy();
});

test('K3-PENDING-RETENTION-001 accepted transition metadata cannot affect a later ordinary search',async()=>{
    const {component:c,requests,browser}=await harness();pendingRetentionFixture(c,browser);const branch=c.chooseBranch(1);destinationReply(requests[0]);await new Promise(resolve=>setImmediate(resolve));destinationReply(requests[1]);await branch;assert.match(c.notice,/#7/);const pushes=browser.history.pushes.length;
    c.searchLater('חדש');const [search]=browser.advance(250);destinationReply(requests[2]);await search;assert.equal(requests.length,3);assert.equal(c.notice,'');assert.deepEqual(c.criteria.tagValueIds,[3]);assert.equal(browser.history.pushes.length,pushes);assert.deepEqual(navigationQueryFromUrlForTest(browser),c.criteria);c.destroy();
});

test('K3-PENDING-RETENTION-001 invalid draft in the continuation boundary retains context with a usable error',async()=>{
    const {component:c,requests,browser}=await harness();const before=browser.location.href;c.searchLater('x'.repeat(501));let task;
    assert.doesNotThrow(()=>{[task]=browser.advance(250);});await task;assert.ok(c.error);assert.equal(c.loading,false);assert.equal(requests.length,0);assert.equal(c.projection.cards[0].id,2);assert.equal(browser.location.href,before);c.destroy();
});
