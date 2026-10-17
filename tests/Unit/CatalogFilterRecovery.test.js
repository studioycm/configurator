import { test } from 'node:test';
import assert from 'node:assert/strict';
import { catalogLabels, catalogMessages } from '../Fixtures/catalog-labels.js';

const snapshot = {schema:1, groupId:'1', revision:'2', columns:[{key:'pressure',values:['25','16']}],
    fields:[{key:'pressure',label:'Pressure',column:0,options:[{value:'25',label:'25',code:0},{value:'16',label:'16',code:1}]}],
    presets:[], rows:[['A',0],['B',1]], settings:{max_results:'all',cards_per_row:4,products_debounce_ms:60000}};
let moduleId = 0;
async function harness(t, payload, initial = snapshot) {
    if (arguments.length < 2) payload = catalogLabels;
    const keys = ['document','window','location','history','fetch','requestAnimationFrame','cancelAnimationFrame'];
    const originals = Object.fromEntries(keys.map(key => [key,globalThis[key]]));
    let component;
    globalThis.document = new EventTarget();
    document.hidden = true;
    globalThis.window = new EventTarget();
    window.Alpine = {data:(_name, factory) => { component = factory(); }};
    globalThis.location = {href:'https://catalog.test/groups/1'};
    globalThis.history = {state:{},replaceState(_state,_title,url) { location.href = String(url); },pushState(_state,_title,url) { location.href = String(url); }};
    globalThis.requestAnimationFrame = () => 1;
    globalThis.cancelAnimationFrame = () => {};
    const source = {textContent:JSON.stringify(initial),remove() {}};
    const root = {dataset:{catalogLabels:payload,datasetUrl:'/dataset',groupId:'1'},
        querySelector: selector => selector === '[data-catalog-snapshot]' ? source : selector === '[data-catalog-recovery]' ? {textContent:'Localized recovery — reload'} : null};
    await import(`../../resources/js/catalog-filters.js?recovery-${++moduleId}`);
    document.dispatchEvent(new Event('alpine:init'));
    component.$el = root;
    component.$nextTick = () => {};
    component.$wire = {loadCards:async () => ({})};
    t.after(() => {
        component.destroy();
        for (const [key,value] of Object.entries(originals)) {
            if (value === undefined) delete globalThis[key]; else globalThis[key] = value;
        }
    });
    return component;
}

for (const payload of [undefined, '{', 'null', '[]', '{}', JSON.stringify({...catalogMessages, field_cleared:5})]) {
    test(`invalid catalog labels recover visibly without an uncaught error: ${payload}`, async t => {
        const c = await harness(t,payload);
        assert.doesNotThrow(() => c.init());
        assert.equal(c.available,false);
        assert.equal(c.reloadRequired,true);
        assert.equal(c.unavailable,'Localized recovery — reload');
        assert.equal(await c.refreshSnapshot(),false);
    });
}

test('a missing snapshot can retry while an unsupported snapshot requires a localized reload', async t => {
    const c = await harness(t,catalogLabels,null);
    c.init();
    assert.equal(c.unavailable,catalogMessages.filters_unavailable);
    fetch = async () => ({ok:true,status:200,json:async () => ({...snapshot,schema:99})});
    assert.equal(await c.refreshSnapshot(),false);
    assert.equal(c.reloadRequired,true);
    assert.equal(c.freshnessError,catalogMessages.format_changed);
});

test('304 preserves current choices without reloading cards and a revision refresh keeps valid choices', async t => {
    const c = await harness(t);
    c.init();
    c.choose('filter','pressure','25');
    const state = structuredClone(c.state);
    let calls = 0;
    c.$wire.loadCards = () => { calls++; };
    fetch = async () => ({status:304});
    assert.equal(await c.refreshSnapshot(),true);
    assert.deepEqual(c.state,state);
    assert.equal(calls,0);
    fetch = async () => ({ok:true,status:200,json:async () => ({...snapshot,revision:'3'})});
    assert.equal(await c.refreshSnapshot(),true);
    assert.deepEqual(c.state,state);
    assert.equal(c.total,1);
});

for (const status of [401,403,419,404,409,503]) {
    test(`refresh status ${status} shows the appropriate localized recovery and retains usable choices`, async t => {
        const c = await harness(t);
        c.init();
        c.choose('filter','pressure','25');
        fetch = async () => ({ok:false,status});
        assert.equal(await c.refreshSnapshot(),false);
        if ([404,409].includes(status)) {
            assert.equal(c.available,false);
            assert.equal(c.reloadRequired,true);
            assert.equal(c.unavailable,status === 404 ? catalogMessages.group_unavailable : catalogMessages.group_is_branch);
        } else {
            assert.equal(c.available,true);
            assert.equal(c.freshnessError,status === 503 ? catalogMessages.update_failed : catalogMessages.session_changed);
            assert.equal(c.total,1);
        }
    });
}

test('invalid identities and older revisions cannot overwrite current data', async t => {
    const c = await harness(t);
    c.init();
    c.choose('filter','pressure','25');
    for (const change of [{groupId:'2'}, {revision:'invalid'}, {revision:'1'}]) {
        fetch = async () => ({ok:true,status:200,json:async () => ({...snapshot,...change})});
        assert.equal(await c.refreshSnapshot(),false);
        assert.equal(c.total,1);
        assert.deepEqual(c.state.filters,{pressure:'25'});
    }
});

for (const action of ['destroy','pagehide']) {
    test(`${action} aborts refreshes and ignores late responses`, async t => {
        const c = await harness(t);
        c.init();
        let resolve;
        let signal;
        fetch = (_url,options) => { signal = options.signal; return new Promise(done => { resolve = done; }); };
        const pending = c.refreshSnapshot();
        if (action === 'destroy') c.destroy(); else window.dispatchEvent(new Event('pagehide'));
        assert.equal(signal.aborted,true);
        resolve({ok:true,status:200,json:async () => ({...snapshot,revision:'3',rows:[]})});
        assert.equal(await pending,false);
        assert.equal(c.total,2);
    });
}

test('exhausted automatic card retries use the translated message', async t => {
    const c = await harness(t,JSON.stringify({...catalogMessages,still_changing:'RETRY_TRANSLATION'}));
    c.init();
    c.$wire.loadCards = async (_state,_revision,id) => ({requestId:id,revision:'2',status:'refresh_required'});
    fetch = async () => ({status:304});
    // First response consumes the one automatic retry; the second must provide recovery text.
    await c.loadCards(1);
    await c.loadCards(1);
    assert.equal(c.cardStatus,'error');
    assert.equal(c.cardError,'RETRY_TRANSLATION');
});
