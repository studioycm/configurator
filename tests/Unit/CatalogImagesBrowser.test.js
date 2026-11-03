import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { imagesMessageGuard, imageCodeChunks, catalogImagesBrowser } from '../../resources/js/catalog-images-browser.js';

const map = JSON.parse(readFileSync(new URL('../../vendor/livewire/livewire/dist/livewire.esm.js.map', import.meta.url), 'utf8'));
const source = name => map.sourcesContent[map.sources.indexOf('../js/request/' + name + '.js')];
const interceptorSource = source('interceptor').split('export class RequestInterceptor')[0].replace(/^import .*\n/gm, '').replace('export class MessageInterceptor', 'class MessageInterceptor');
const MessageInterceptor = new Function(interceptorSource + '\nreturn MessageInterceptor;')();
const Message = new Function('MessageInterceptor', source('message').replace(/^import .*\n/gm, '').replace('export default class Message', 'class Message') + '\nreturn Message;')(MessageInterceptor);
const requestSource = source('index');
const start = requestSource.indexOf('let { snapshot: snapshotEncoded, effects } = payload');
const end = requestSource.indexOf('\n                    })', start);
assert.ok(start >= 0 && end > start);
const processPayload = new Function('message', 'payload', 'request', 'Alpine', 'discardIfSuperseded', 'supersedeOlderMessages', 'requestAnimationFrame', requestSource.slice(start, end));
const serializationStart = requestSource.indexOf('message.snapshot = message.component.getEncodedSnapshotWithLatestChildrenMergedIn()');
const serializationEnd = requestSource.indexOf('\n        })', serializationStart);
assert.ok(serializationStart >= 0 && serializationEnd > serializationStart);
const serialize = new Function('message', requestSource.slice(serializationStart, serializationEnd));

const utilsSource=map.sourcesContent[map.sources.indexOf('../js/utils.js')];
const weakStart=utilsSource.indexOf('class WeakBag'), weakEnd=utilsSource.indexOf('\nexport function dispatch',weakStart);
const WeakBag=new Function(utilsSource.slice(weakStart,weakEnd)+';return WeakBag;')();
const registrySource=source('interceptor').slice(source('interceptor').indexOf('export class InterceptorRegistry')).replace('export class','class');
const InterceptorRegistry=new Function('WeakBag','MessageInterceptor',registrySource+';return InterceptorRegistry;')(WeakBag,MessageInterceptor);
const registry=new InterceptorRegistry();
globalThis.Livewire={interceptMessage:callback=>registry.addMessageInterceptor(callback)};
let nextHost=0;

const state = () => ({ imageTarget: 'product.main_image', imageNavigationMode: 'results', imageRecordId: 1,
    imageBrowserRevision: 0, imageSearch: { query: 'el 6' }, tableSort: null, tableRecordsPerPage: 10, selectedTableRecords: [], paginators: { page: 1 } });

function harness() {
    const effects = []; let generation = 0, alive = true, removed = false;
    const component = { id: 'images-'+(++nextHost), mergeNewSnapshot() { effects.push('sync'); }, processEffects() { effects.push('effects'); } };
    const wire = { ...state(), $id: component.id };
    const remove = imagesMessageGuard(wire, { generation: () => generation, alive: () => alive, onMorphed: () => effects.push('focused') });
    const message = new Message(component);
    message.addAction({ name: 'navigateImageRecord', params: ['next'], fingerprint: 'next', rejectPromise() {}, resolvePromise() {}, invokeOnFinish() {}, invokeOnSuccess() {} });
    message.updates = {};
    message.setInterceptors(registry.getMessageInterceptors(message));
    const deliver = async (response = message) => {
        const pending = [];
        processPayload(response, { snapshot: JSON.stringify({ memo: { id: response.component.id }, data: state() }), effects: { returns: [null] } }, {},
            { transaction(fn) { const promise = fn(); pending.push(promise); return promise; } }, () => false, () => {}, fn => fn());
        await Promise.all(pending);
    };
    return { wire, message, component, effects, deliver, supersede: () => generation++, destroy: () => { alive = false; remove(); removed = true; }, removed: () => removed };
}

for (const [name, change] of [
    ['target', h => { h.wire.imageTarget = 'group.main_image'; }],
    ['search', h => { h.wire.imageSearch.query = 'newer'; }],
    ['navigation intent', h => h.supersede()],
    ['checkboxes', h => { h.wire.selectedTableRecords = ['2']; }],
    ['page size', h => { h.wire.tableRecordsPerPage = 25; }],
    ['teardown', h => h.destroy()],
]) test(`obsolete ${name} response is cancelled before installed sync effects morph and focus`, async () => {
    const h = harness(); change(h); await h.deliver();
    assert.equal(h.message.isCancelled(), true); assert.deepEqual(h.effects, []); h.destroy();
});

test('current message applies native effects then finishes its bound focus callback', async () => {
    const h = harness(); await h.deliver();
    assert.deepEqual(h.effects, ['sync', 'effects', 'focused']); h.destroy(); assert.equal(h.removed(), true);
});

test('full paste serializes through installed payload construction below one MiB for every UTF8 chunk', () => {
    const raw = '\u0001'.repeat(524288), chunks = [...imageCodeChunks(raw)];
    const selectedTableRecords = Array.from({ length: 5000 }, (_, n) => String(900000000000 + n));
    const snapshot = JSON.stringify({ data: { ...state(), imageSearch: { query: '\u0001'.repeat(500), targets: ['code', 'name', 'reference', 'group'], combine: 'all', match: 'contains', case_sensitive: false, literal_separators: false, positions: Array.from({length:16}, (_,n) => n+1), exclude: '\u0001'.repeat(500), image_state: 'any', group_ids: selectedTableRecords, checked_only: false }, tableColumns: ['saved_image','product_code','product_name','group.name','image_reference','image_status'].map(name => ({ name, isToggled: true, isToggleable: true })), selectedTableRecords }, memo: { id: 'images', children: {} }, checksum: 'x'.repeat(64) });
    let offset = 0;
    for (const chunk of chunks) {
        const component = { getEncodedSnapshotWithLatestChildrenMergedIn: () => snapshot, getUpdates: () => ({ selectedTableRecords }) };
        const message = new Message(component);
        message.addAction({ name: 'appendExactCodePaste', params: [{ token: 'a'.repeat(40), session: 'b'.repeat(36), revision: 1 }, offset, chunk, state()], metadata: {}, fingerprint: String(offset) });
        serialize(message);
        const encoded = JSON.stringify({ _token: 't'.repeat(40), components: [message.payload] });
        assert.ok(Buffer.byteLength(encoded) < 1048576);
        assert.equal(message.payload.updates.selectedTableRecords.length, 5000);
        offset += Buffer.byteLength(chunk);
    }
    assert.equal(offset, 524288); assert.equal(chunks.join(''), raw);
    const unicode = 'a'.repeat(32767) + '💧' + 'א'.repeat(20);
    const parts = [...imageCodeChunks(unicode)];
    assert.equal(parts.join(''), unicode); assert.ok(parts.every(part => Buffer.byteLength(part) <= 32768 && !part.includes('\ufffd')));
});

test('transfer stops after a failed chunk and never finalizes while retaining local text', async () => {
    const calls = [], ui = catalogImagesBrowser({ user: 1 });
    ui.$wire = { ...state(), async beginExactCodePaste() { calls.push('begin'); return { token: 'one' }; }, async appendExactCodePaste() { calls.push('chunk'); throw new Error('network'); }, async finishExactCodePaste() { calls.push('finish'); } };
    ui.pasteText = 'EL-001';
    await ui.transferCodes();
    assert.deepEqual(calls, ['begin', 'chunk']); assert.equal(ui.transferring, false); assert.equal(ui.pasteText, 'EL-001'); assert.match(ui.clientError, /stopped/);
});

test('preference capture handles reactive proxies and never persists paste or checks', () => {
    const ui = catalogImagesBrowser({ user: 1 });
    ui.$wire = { ...state(), imageSearch: new Proxy({ query: 'el 6', targets: ['code'] }, {}), tableColumns: [], exactCodeSet: { token: 'private' } };
    ui.pasteText = 'Do not persist';
    const saved = ui.profileState();
    assert.equal(saved.search.query, 'el 6');
    assert.equal(Object.hasOwn(saved, 'selectedTableRecords'), false);
    assert.equal(JSON.stringify(saved).includes('Do not persist'), false);
    assert.equal(JSON.stringify(saved).includes('private'), false);
});

test('inspect passes its rendered target context and teardown stops an in-flight transfer before the next chunk', async () => {
    const calls = [], ui = catalogImagesBrowser({ user: 1 });
    ui.$wire = { ...state(), async $call(...args) { calls.push(args); } };
    await ui.inspect({ method: 'inspectProductImage', record: 5, context: { target: 'product.main_image', mode: 'results', record: null, revision: 0 } });
    assert.equal(calls[0][0], 'inspectProductImage');
    assert.equal(calls[0][2].record, null);
    assert.equal(calls[0][2].target, 'product.main_image');
    let release;
    ui.$el = { removeEventListener() {} };
    ui.$wire.beginExactCodePaste = async () => ({ token: 'owned' });
    ui.$wire.appendExactCodePaste = async () => new Promise(resolve => { calls.push('chunk'); release = resolve; });
    ui.$wire.finishExactCodePaste = async () => calls.push('finish');
    ui.pasteText = 'X'.repeat(65536);
    const transfer = ui.transferCodes();
    await new Promise(resolve => setImmediate(resolve));
    ui.destroy(); release({ bytes: 32768 }); await transfer;
    assert.equal(calls.filter(x => x === 'chunk').length, 1);
    assert.equal(calls.includes('finish'), false);
});

const eventsSource = map.sourcesContent[map.sources.indexOf('../js/events.js')];
const installedDispatch = new Function(eventsSource.replace(/^import .*\n/gm, '').replace(/^export /gm, '') + '\nreturn dispatch;')();
const dispatchSupportSource = map.sourcesContent[map.sources.indexOf('../js/features/supportDispatches.js')];
const loadDispatchSupport = new Function('interceptMessage', 'on', 'dispatch', 'dispatchEl', 'dispatchRef', 'dispatchSelf', 'dispatchTo',
    dispatchSupportSource.replace(/^import .*\n/gm, ''));

async function focusHarness(localFirst = false) {
    const effects = [], root = new EventTarget(), nested = new EventTarget();
    const listeners = new Set();
    const add = root.addEventListener.bind(root), remove = root.removeEventListener.bind(root);
    root.addEventListener = (name, callback) => { listeners.add(callback); add(name, callback); };
    root.removeEventListener = (name, callback) => { listeners.delete(callback); remove(name, callback); };
    root.listenerCount = () => listeners.size;
    nested.parentElement = root;
    const heading = { focus() { effects.push('focus'); } };
    nested.querySelector = selector => selector === '[data-catalog-images-inspector-heading]' ? heading : null;
    let localInterceptor, dispatchInterceptor;
    const wire = { ...state(), $id: 'images', $el: root, tableColumns: [],
        interceptMessage(callback) { localInterceptor = callback; return () => { localInterceptor = null; }; },
        $watch() { return () => {}; },
    };
    const ui = catalogImagesBrowser({ user: 1 });
    ui.$el = nested; ui.$wire = wire;
    // The inspector heading belongs to the descendant splitter's Alpine scope.
    ui.$refs = {};
    const previousStorage = globalThis.localStorage;
    globalThis.localStorage = { getItem() { return null; }, setItem() {} };
    await ui.init();
    localInterceptor = registry.messageInterceptorCallbacks.at(-1);
    const unsupportedDispatch = () => assert.fail('Unexpected non-component dispatch');
    loadDispatchSupport(callback => { dispatchInterceptor = callback; }, () => {}, installedDispatch,
        unsupportedDispatch, unsupportedDispatch, unsupportedDispatch, unsupportedDispatch);
    const component = { id: 'images', el: root,
        mergeNewSnapshot(encoded) { Object.assign(wire, JSON.parse(encoded).data); effects.push('sync'); },
        processEffects() { effects.push('effects'); },
    };
    const message = new Message(component);
    message.addAction({ name: 'inspectProductImage', params: [2], fingerprint: 'inspect', rejectPromise() {}, resolvePromise() {}, invokeOnFinish() {}, invokeOnSuccess() {} });
    message.updates = {};
    for (const callback of localFirst ? [localInterceptor, dispatchInterceptor] : [dispatchInterceptor, localInterceptor]) message.addInterceptor(callback);
    message.addInterceptor(({ onSuccess }) => onSuccess(({ onMorph }) => onMorph(async () => effects.push('morph'))));
    const context = { target: 'product.main_image', mode: 'results', record: 2, revision: 1 };
    const deliver = async (params = { host: 'images', context }) => {
        const pending = [];
        processPayload(message, { snapshot: JSON.stringify({ memo: { id: 'images' }, data: { ...state(), imageRecordId: 2, imageBrowserRevision: 1 } }),
            effects: { returns: [null], dispatches: [{ name: 'catalog-images-inspected', params }] } }, {},
            { transaction(fn) { const promise = fn(); pending.push(promise); return promise; } }, () => false, () => {}, fn => fn());
        await Promise.all(pending);
    };
    return { ui, wire, root, nested, effects, context, message, deliver, cleanup() { ui.destroy(); globalThis.localStorage = previousStorage; } };
}

for (const localFirst of [false, true]) test(`server inspector dispatch focuses after its own morph with ${localFirst ? 'local' : 'framework'} callback first`, async () => {
    const h = await focusHarness(localFirst);
    try {
        assert.notEqual(h.root, h.nested); assert.equal(h.nested.parentElement, h.root); assert.equal(h.root.listenerCount(), 1);
        await h.deliver();
        assert.deepEqual(h.effects, ['sync', 'effects', 'morph', 'focus']);
    } finally { h.cleanup(); assert.equal(h.root.listenerCount(), 0); }
});

for (const kind of ['foreign host', 'stale context', 'obsolete response', 'teardown']) test(`server inspector completion ignores ${kind}`, async () => {
    const h = await focusHarness();
    try {
        let params = { host: 'images', context: h.context };
        if (kind === 'foreign host') params.host = 'another-host';
        if (kind === 'stale context') params.context = { ...h.context, revision: 0 };
        if (kind === 'obsolete response') h.wire.imageSearch.query = 'newer';
        if (kind === 'teardown') h.ui.destroy();
        await h.deliver(params);
        assert.equal(h.effects.includes('focus'), false);
        if (kind === 'obsolete response' || kind === 'teardown') assert.equal(h.message.isCancelled(), true);
        if (kind === 'teardown') {
            installedDispatch({ el: h.root }, 'catalog-images-inspected', { host: 'images', context: h.context });
            assert.equal(h.effects.includes('focus'), false);
        }
    } finally { h.cleanup(); }
});

const wireSource = map.sourcesContent[map.sources.indexOf('../js/$wire.js')];
const magicStart = wireSource.indexOf("Alpine.magic('wire'");
const magicEnd = wireSource.indexOf("\nwireProperty('__instance'", magicStart);
assert.ok(magicStart >= 0 && magicEnd > magicStart);
const loadWireMagic = new Function('Alpine', 'findComponentByEl', 'generateEntangleFunction', 'generateWatchFunction', wireSource.slice(magicStart, magicEnd));

test('code transfer keeps the native component wire reference when its initiating button is replaced by the first morph', async () => {
    let magic;
    const clickedButton = { connected: true }, calls = [];
    const component = { $wire: { ...state(),
        async beginExactCodePaste() { calls.push('begin'); clickedButton.connected = false; return { token: 'owned', session: 'one', revision: 1 }; },
        async appendExactCodePaste(identity, offset, chunk) { calls.push('append'); return { bytes: offset + Buffer.byteLength(chunk) }; },
        async finishExactCodePaste() { calls.push('finish'); },
    } };
    loadWireMagic({ magic(name, factory) { assert.equal(name, 'wire'); magic = factory; } }, element => {
        if (!element.connected) throw new Error('Component not found');
        return component;
    }, () => {}, () => {});
    const ui = catalogImagesBrowser({ user: 1 });
    // Alpine magic access can create a fresh lazy proxy for the original event element.
    Object.defineProperty(ui, '$wire', { get() { return magic(clickedButton, { cleanup() {} }); } });
    ui.pasteText = 'EL-001\nEL-002';
    await ui.transferCodes();
    assert.deepEqual(calls, ['begin', 'append', 'finish']);
    assert.equal(ui.clientError, ''); assert.equal(ui.transferring, false);
});

test('deferred target watcher cannot let a morph overwrite the previous target profile key', async () => {
    const values = new Map(), previousStorage = globalThis.localStorage;
    globalThis.localStorage = { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key) };
    let intercept, watch;
    const root = new EventTarget(), nested = new EventTarget(); nested.querySelector = () => null;
    const wire = { ...state(), $id: 'images', $el: root, imageSearch: { query: 'product query' }, tableColumns: [],
        interceptMessage(callback) { intercept = callback; return () => {}; }, $watch(path, callback) { watch = callback; return () => {}; },
        async restoreTableBrowserProfile(saved) {
            assert.equal(saved.scope, ui.profileState().scope);
            wire.imageSearch = saved.search; wire.tableRecordsPerPage = saved.perPage; wire.tableSort = saved.sort; wire.tableColumns = saved.columns;
        },
    };
    const ui = catalogImagesBrowser({ user: 1 }); ui.$wire = wire; ui.$el = nested; ui.$refs = {};
    const component = { id: 'images', mergeNewSnapshot(encoded) { Object.assign(wire, JSON.parse(encoded).data); }, processEffects() {} };
    async function commit(data) {
        const message = new Message(component), pending = [];
        message.addAction({ name: 'switchImageTarget', params: [], fingerprint: 'switch', rejectPromise() {}, resolvePromise() {}, invokeOnFinish() {}, invokeOnSuccess() {} });
        message.updates = {}; message.addInterceptor(intercept);
        processPayload(message, { snapshot: JSON.stringify({ memo: { id: 'images' }, data }), effects: { returns: [null] } }, {},
            { transaction(fn) { const promise = fn(); pending.push(promise); return promise; } }, () => false, () => {}, fn => fn());
        await Promise.all(pending);
    }
    try {
        await ui.init();
        intercept = registry.messageInterceptorCallbacks.at(-1);
        const productKey = [...values.keys()][0], productScope = ui.profileState().scope;
        await commit({ ...state(), imageTarget: 'group.main_image', imageSearch: { query: '' } });
        assert.equal(JSON.parse(values.get(productKey)).scope, productScope);
        assert.equal(JSON.parse(values.get(productKey)).search.query, 'product query');
        watch(); await new Promise(resolve => setImmediate(resolve));
        const groupKey = [...values.keys()].find(key => key !== productKey), groupScope = ui.profileState().scope;
        await commit({ ...wire, imageSearch: { query: 'group query' } });
        await commit({ ...state(), imageTarget: 'product.main_image', imageSearch: { query: '' } });
        assert.equal(JSON.parse(values.get(groupKey)).scope, groupScope);
        assert.equal(JSON.parse(values.get(groupKey)).search.query, 'group query');
        watch(); await new Promise(resolve => setImmediate(resolve));
        assert.equal(wire.imageSearch.query, 'product query'); assert.equal(ui.clientError, '');
    } finally { ui.destroy(); globalThis.localStorage = previousStorage; }
});

test('an already contaminated target profile falls back locally without sending a foreign scope to the server', async () => {
    const previousStorage = globalThis.localStorage, saved = [], root = new EventTarget();
    globalThis.localStorage = {
        getItem() { return JSON.stringify({ scope: 'foreign-target', search: { query: 'wrong' } }); },
        setItem(key, raw) { saved.push(JSON.parse(raw)); },
    };
    const ui = catalogImagesBrowser({ user: 1 });
    ui.$wire = { ...state(), $id: 'images', $el: root, tableColumns: [], interceptMessage() { return () => {}; }, $watch() { return () => {}; },
        async restoreTableBrowserProfile() { assert.fail('A foreign scope must not reach restoration'); } };
    ui.$el = { querySelector() { return null; } };
    try {
        await ui.init();
        assert.equal(saved.length, 1); assert.equal(saved[0].scope, ui.profileState().scope);
        assert.equal(saved[0].search.query, 'el 6'); assert.equal(ui.clientError, '');
    } finally { ui.destroy(); globalThis.localStorage = previousStorage; }
});


test('outer guard disposal releases its installed callback and leaves a new same-component response intact', async () => {
    const h=harness();
    h.destroy();h.destroy();
    assert.equal(registry.messageInterceptorCallbacks.length,0);
    const remove=imagesMessageGuard(h.wire);
    const fresh=new Message(h.component);
    fresh.addAction({name:'catalogMainImageChooseSource',params:['source:current'],fingerprint:'source',rejectPromise(){},resolvePromise(){},invokeOnFinish(){},invokeOnSuccess(){}});
    fresh.updates={};fresh.setInterceptors(registry.getMessageInterceptors(fresh));
    await h.deliver(fresh);assert.equal(fresh.isCancelled(),false);assert.deepEqual(h.effects,['sync','effects']);
    h.effects.length=0;
    await h.deliver();assert.equal(h.message.isCancelled(),true);assert.deepEqual(h.effects,[]);
    remove();remove();assert.equal(registry.messageInterceptorCallbacks.length,0);
});

test('an outer guard cannot cancel or focus a different component response', async () => {
    const h=harness(); h.wire.imageSearch.query='newer';
    const foreign=new Message({...h.component,id:'foreign-page'});
    foreign.addAction({name:'navigateImageRecord',params:['next'],fingerprint:'foreign',rejectPromise(){},resolvePromise(){},invokeOnFinish(){},invokeOnSuccess(){}});
    foreign.updates={};foreign.setInterceptors(registry.getMessageInterceptors(foreign));
    await h.deliver(foreign);
    assert.equal(foreign.isCancelled(),false);assert.deepEqual(h.effects,['sync','effects']);h.destroy();
});
