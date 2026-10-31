import { test } from 'node:test';
import assert from 'node:assert/strict';

import { catalogLabels, catalogMessages } from '../Fixtures/catalog-labels.js';

test('freshness uses the initialized component root after a child button owns the Alpine magic context', async () => {
    const originals = {document:globalThis.document, window:globalThis.window, fetch:globalThis.fetch, cancelAnimationFrame:globalThis.cancelAnimationFrame};
    const requested = [];
    let component;
    try {
        globalThis.document = new EventTarget();
        globalThis.window = new EventTarget();
        globalThis.cancelAnimationFrame = () => {};
        window.Alpine = {data: (_name, factory) => { component = factory(); }};
        globalThis.fetch = async url => { requested.push(url); return {status:503, ok:false}; };
        window.Alpine.data('catalogFilters', (await import('../../resources/js/catalog-filters.js')).catalogFilters);
        component.$el = {dataset:{catalogLabels, datasetUrl:'/dashboard/catalog/groups/1/dataset',groupId:'1'}, querySelector:()=>null};
        component.init();
        // Alpine resolves $el to the element whose expression invokes a method.
        component.$el = {dataset:{}};
        await component.refreshSnapshot();
        assert.deepEqual(requested, ['/dashboard/catalog/groups/1/dataset']);
        assert.equal(component.available, false);
        assert.match(component.unavailable, /unavailable/);
    } finally {
        component?.destroy();
        for (const [key, value] of Object.entries(originals)) {
            if (value === undefined) delete globalThis[key];
            else globalThis[key] = value;
        }
    }
});

test('only current cards can publish a properties notice and criteria, retry and suspension clear it', async () => {
    const keys = ['document', 'window', 'location', 'history', 'requestAnimationFrame', 'cancelAnimationFrame'];
    const originals = Object.fromEntries(keys.map(key => [key, globalThis[key]]));
    const pending = [];
    const frames = new Map();
    let nextFrame = 0;
    let component;
    const flush = async () => {
        for (let round = 0; round < 12; round++) {
            await Promise.resolve();
            const callbacks = [...frames.values()];
            frames.clear();
            callbacks.forEach(callback => callback());
        }
    };
    const reply = (index, notice) => {
        const request = pending[index];
        request.resolve({requestId:request.id, revision:'1', status:'ready', total:request.total,
            htmlChunks:[`<article>${index}</article>`], propertiesNotice:notice});
    };
    try {
        const snapshot = {schema:2, groupId:'1', revision:'1',
            columns:[{key:'pressure', values:['25','16']}],
            fields:[{key:'pressure', label:'Pressure', column:0,
                options:[{value:'25',label:'25',code:0},{value:'16',label:'16',code:1}]}],
            rows:[['A',0],['B',1]], presets:[],
            settings:{max_results:'all',cards_per_row:4,products_debounce_ms:0}};
        const root = new EventTarget();
        root.dataset = {catalogLabels, datasetUrl:'/dataset', groupId:'1'};
        root.querySelector = () => ({textContent:JSON.stringify(snapshot), remove() {}});
        globalThis.document = new EventTarget();
        document.hidden = true;
        globalThis.window = new EventTarget();
        window.Alpine = {data:(_name, factory) => { component = factory(); }};
        globalThis.location = {href:'https://catalog.test/dashboard/catalog/groups/1'};
        globalThis.history = {state:{}, pushState(_state, _title, url) { location.href = String(url); },
            replaceState(_state, _title, url) { location.href = String(url); }};
        globalThis.requestAnimationFrame = callback => { frames.set(++nextFrame, callback); return nextFrame; };
        globalThis.cancelAnimationFrame = id => frames.delete(id);
        window.Alpine.data('catalogFilters', (await import('../../resources/js/catalog-filters.js?card-notice-test')).catalogFilters);
        component.$el = root;
        component.$nextTick = callback => queueMicrotask(callback);
        component.$wire = {loadCards(_state, _revision, id) {
            const total = component.total;
            return new Promise(resolve => pending.push({id,total,resolve}));
        }};
        component.init();
        assert.equal(component.cardPropertiesNotice, null);
        component.choose('filter', 'pressure', '25');
        reply(1, 'Only one product matches.');
        await flush();
        assert.equal(component.cardPropertiesNotice, 'Only one product matches.');
        assert.equal(component.cardStatus, 'ready');
        const acceptedChunks = [...component.cardChunks];
        component.freshnessError = 'A newer update check failed.';
        reply(0, 'Obsolete shared properties.');
        await flush();
        assert.deepEqual(component.cardChunks, acceptedChunks);
        assert.equal(component.cardPropertiesNotice, 'Only one product matches.');
        assert.equal(component.cardStatus, 'ready');
        assert.equal(component.freshnessError, 'A newer update check failed.');

        component.retryCards();
        assert.equal(component.cardPropertiesNotice, null);
        component.choose('reset');
        reply(2, 'Obsolete retry.');
        await flush();
        assert.equal(component.cardPropertiesNotice, null);
        assert.equal(component.cardStatus, 'loading');
        reply(3, 'Current shared properties.');
        await flush();
        assert.equal(component.cardPropertiesNotice, 'Current shared properties.');
        window.dispatchEvent(new Event('pagehide'));
        assert.equal(component.cardPropertiesNotice, null);
        assert.equal(component.cardsVisible, false);
        window.dispatchEvent(new Event('pageshow'));
        reply(4, null);
        await flush();
        assert.equal(component.cardPropertiesNotice, null);
        assert.equal(component.cardStatus, 'ready');
        component.exitLeaf(404);
        assert.equal(component.cardPropertiesNotice, null);
        assert.equal(component.cardsVisible, false);
    } finally {
        component?.destroy();
        for (const [key, value] of Object.entries(originals)) {
            if (value === undefined) delete globalThis[key];
            else globalThis[key] = value;
        }
    }
});

test('stale display cards cannot insert and automatic pair recovery retries only once without changing choices', async () => {
    const keys = ['document','window','location','history','fetch','requestAnimationFrame','cancelAnimationFrame'];
    const originals = Object.fromEntries(keys.map(key => [key,globalThis[key]]));
    const versionA = '01K8AAAAAAAAAAAAAAAAAAAAAA';
    const versionB = '01K8BBBBBBBBBBBBBBBBBBBBBB';
    const pending = [];
    const frames = new Map();
    let frameId = 0;
    let component;
    let refreshes = 0;
    const flush = async () => {
        for (let i = 0; i < 20; i++) {
            await Promise.resolve();
            const work = [...frames.values()];frames.clear();work.forEach(fn => fn());
        }
    };
    try {
        const initial = {schema:2,groupId:'1',revision:'1',displayVersion:versionA,presentation:{product_image_placement:'left'},
            columns:[{key:'pressure',values:['25','16']}],
            fields:[{key:'pressure',label:'Pressure',column:0,options:[{value:'25',label:'25',code:0},{value:'16',label:'16',code:1}]}],
            rows:[['A',0],['B',1]],presets:[],settings:{max_results:'all',cards_per_row:4,products_debounce_ms:0}};
        globalThis.document = new EventTarget();document.hidden = true;
        globalThis.window = new EventTarget();
        globalThis.location = {href:'https://catalog.test/groups/1'};
        globalThis.history = {state:{},replaceState(_s,_t,url) {location.href=String(url);},pushState(_s,_t,url) {location.href=String(url);}};
        globalThis.requestAnimationFrame = fn => {frames.set(++frameId,fn);return frameId;};
        globalThis.cancelAnimationFrame = id => frames.delete(id);
        const root = new EventTarget();root.dataset={catalogLabels,datasetUrl:'/dataset',groupId:'1'};
        root.querySelector = selector => selector === '[data-catalog-snapshot]' ? {textContent:JSON.stringify(initial),remove(){}} : null;
        component = (await import('../../resources/js/catalog-filters.js?display-card-pair')).catalogFilters();
        component.$el=root;component.$nextTick=fn=>queueMicrotask(fn);
        component.$wire={loadCards(state,revision,id,displayVersion) {return new Promise(resolve=>pending.push({state,revision,id,displayVersion,resolve}));}};
        globalThis.fetch = async () => {refreshes++;return {ok:true,status:200,json:async()=>({...initial,displayVersion:versionB,presentation:{product_image_placement:'right'}})};};
        component.init();
        component.choose('filter','pressure','25');
        const state = structuredClone(component.state);
        const url = location.href;
        assert.equal(pending[1].displayVersion,versionA);
        pending[1].resolve({requestId:pending[1].id,revision:'1',displayVersion:versionB,status:'ready',total:1,htmlChunks:['<article>stale pair</article>']});
        await flush();
        assert.equal(refreshes,1);
        assert.equal(pending[2].displayVersion,versionB);
        assert.deepEqual(component.state,state);
        assert.equal(location.href,url);
        assert.equal(component.total,1);
        assert.deepEqual(component.cardChunks,[]);
        pending[0].resolve({requestId:pending[0].id,revision:'1',displayVersion:versionA,status:'ready',total:2,htmlChunks:['<article>old response</article>']});
        pending[2].resolve({requestId:pending[2].id,revision:'1',displayVersion:versionA,status:'refresh_required',htmlChunks:[]});
        await flush();
        assert.equal(refreshes,1);
        assert.equal(component.cardError,catalogMessages.still_changing);
        assert.deepEqual(component.cardChunks,[]);
        component.retryCards();
        pending[3].resolve({requestId:pending[3].id,revision:'1',displayVersion:versionB,status:'ready',total:1,htmlChunks:['<article>current pair</article>']});
        await flush();
        assert.equal(component.cardsVisible,true);
        assert.equal(component.cardChunks[0].html,'<article>current pair</article>');
    } finally {
        component?.destroy();
        for (const [key,value] of Object.entries(originals)) {
            if (value === undefined) delete globalThis[key];else globalThis[key]=value;
        }
    }
});
