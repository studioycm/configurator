import { test } from 'node:test';
import assert from 'node:assert/strict';

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
        await import('../../resources/js/catalog-filters.js');
        document.dispatchEvent(new Event('alpine:init'));
        component.$el = {dataset:{datasetUrl:'/dashboard/catalog/groups/1/dataset',groupId:'1'}, querySelector:()=>null};
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
        const snapshot = {schema:1, groupId:'1', revision:'1',
            columns:[{key:'pressure', values:['25','16']}],
            fields:[{key:'pressure', label:'Pressure', column:0,
                options:[{value:'25',label:'25',code:0},{value:'16',label:'16',code:1}]}],
            rows:[['A',0],['B',1]], presets:[],
            settings:{max_results:'all',cards_per_row:4,products_debounce_ms:0}};
        const root = new EventTarget();
        root.dataset = {datasetUrl:'/dataset', groupId:'1'};
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
        await import('../../resources/js/catalog-filters.js?card-notice-test');
        document.dispatchEvent(new Event('alpine:init'));
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
