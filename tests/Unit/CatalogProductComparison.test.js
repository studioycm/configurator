import test from 'node:test';
import assert from 'node:assert/strict';
import { catalogProductComparison } from '../../resources/js/catalog-product-comparison.js';
import { catalogFilters } from '../../resources/js/catalog-filters.js';
import { catalogLabels } from '../Fixtures/catalog-labels.js';

function fixture(groupId = '1', queuedClose = false) {
    const browser = new EventTarget();
    browser.document = { activeElement: null };
    const calls = [], focused = [], closeTasks = [], dialog = new EventTarget();
    const opener = { isConnected: true, focus() { focused.push('opener'); }, closest: () => null };
    browser.document.activeElement = opener;
    dialog.open = false;
    dialog.getBoundingClientRect = () => ({ left: 100, right: 900, top: 100, bottom: 700 });
    dialog.showModal = () => { dialog.open = true; };
    dialog.close = () => {
        dialog.open = false;
        const dispatch = () => dialog.dispatchEvent(new Event('close'));
        if (queuedClose) closeTasks.push(dispatch); else dispatch();
    };
    const fallback = { isConnected: true, focus() { focused.push('tray'); } };
    const root = { dataset: { catalogComparisonGroup: groupId }, isConnected: true,
        querySelector: selector => selector === '[data-comparison-dialog]' ? dialog : fallback,
        contains: element => element === opener || element === fallback };
    const labels = { failed: 'Retry comparison', stale: 'Choose again', group_scope: 'This Group only', group_changed: 'Group changed', open_failed: 'Cannot open' };
    const component = catalogProductComparison({ groupId, componentId: 'component1', labels }, browser);
    component.$el = root;
    component.$wire = { $id: 'component1', loadComparison(ids, requestId) { return new Promise((resolve, reject) => calls.push({ ids, requestId, resolve, reject })); } };
    component.init(); component.toggleComparisonMode();
    return { component, browser, dialog, root, opener, fallback, focused, calls,
        async flushClose() { await new Promise(resolve => setImmediate(resolve)); closeTasks.splice(0).forEach(dispatch => dispatch()); },
        pair() { component.toggleComparisonProduct('1', 'Product 1', true); component.toggleComparisonProduct('2', 'Product 2', true); },
        reply(index, changes = {}) {
            const call = calls[index];
            call.resolve({ requestId: call.requestId, componentId: 'component1', groupId, pairIds: call.ids, groupRevision: '1', products: call.ids.map(id => ({ id, code: `Product ${id}` })), differences: [{ key: 'zero', label: 'Zero', values: ['0', '001'] }], same: [{ key: 'missing', label: 'Missing', values: [null, null] }], ...changes });
        },
    };
}

test('queued close from an old modal session cannot invalidate a reopened comparison or consume its new opener', async () => {
    const f = fixture('1', true); f.pair(); const first = f.component.openComparison(); f.reply(0); await first;
    f.component.closeComparison(); assert.equal(f.dialog.open, false);
    const nextOpener = { isConnected: true, closest: () => null, focus() { f.focused.push('new-opener'); } };
    f.browser.document.activeElement = nextOpener; f.root.contains = element => element === f.opener || element === nextOpener || element === f.fallback;
    const second = f.component.openComparison(); assert.equal(f.dialog.open, true);
    await f.flushClose();
    assert.equal(f.component.comparisonOpen, true); assert.equal(f.component.comparisonBusy, true);
    assert.deepEqual(f.focused, []);
    f.reply(1); await second;
    assert.ok(f.component.comparisonResult); assert.equal(f.dialog.open, true);
    f.component.closeComparison(); await f.flushClose();
    assert.deepEqual(f.focused, ['new-opener']); f.component.destroy();
});

test('ordinary queued native cancel restores fallback once and closed outstanding read stays invalid', async () => {
    const f = fixture('1', true); f.pair(); const request = f.component.openComparison();
    f.opener.isConnected = false;
    f.dialog.dispatchEvent(new Event('cancel', { cancelable: true }));
    assert.equal(f.dialog.open, false); assert.deepEqual(f.focused, []);
    await f.flushClose(); assert.deepEqual(f.focused, ['tray']);
    f.reply(0); await request; assert.equal(f.component.comparisonResult, null);
    await f.flushClose(); assert.deepEqual(f.focused, ['tray']); f.component.destroy();
});

test('disposal before queued close completion removes its handler and never restores outside focus', async () => {
    const f = fixture('1', true); f.pair(); const request = f.component.openComparison();
    f.component.closeComparison(); f.component.destroy(); await f.flushClose();
    f.reply(0); await request;
    assert.deepEqual(f.focused, []); assert.equal(f.component.comparisonResult, null); assert.equal(f.dialog.open, false);
});

test('fourth selection opens automatically once; fifth still requires explicit replacement or cancel', () => {
    const f = fixture(); f.pair();
    f.component.toggleComparisonProduct('3', 'Product 3', true);
    assert.equal(f.calls.length, 0);
    f.component.toggleComparisonProduct('4', 'Product 4', true);
    assert.equal(f.dialog.open, true);
    assert.deepEqual(f.calls[0].ids, ['1', '2', '3', '4']);
    f.component.closeComparison();
    assert.equal(f.component.comparisonItems.length, 4);
    assert.equal(f.calls.length, 1);
    f.component.toggleComparisonProduct('5', 'Product 5', true);
    assert.deepEqual(f.component.comparisonItems.map(i => i.id), ['1', '2', '3', '4']);
    f.component.cancelComparisonReplacement(); assert.equal(f.component.comparisonReplacement, null);
    f.component.toggleComparisonProduct('5', 'Product 5', true); f.component.replaceComparisonProduct(2);
    assert.deepEqual(f.component.comparisonItems.map(i => i.id), ['1', '2', '5', '4']);
    assert.equal(f.calls.length, 1); f.component.destroy();
});

test('comparison entry requires three or more matches and ready displayed cards', () => {
    const filters = catalogFilters();
    for (const count of [null, 0, 1, 2, 3, 4]) {
        Object.assign(filters, { available: true, cardsVisible: true, cardStatus: 'ready', total: count });
        assert.equal(filters.comparisonEntryVisible, count >= 3);
    }
    for (const state of [{ available: false }, { cardsVisible: false }, { cardStatus: 'loading' }, { cardStatus: 'error' }, { cardStatus: 'above_threshold' }]) {
        Object.assign(filters, { available: true, cardsVisible: true, cardStatus: 'ready', total: 4 }, state);
        assert.equal(filters.comparisonEntryVisible, false);
    }
});

test('only a complete backdrop click dismisses comparison and keeps the selection', async () => {
    const f = fixture(); f.pair(); const request = f.component.openComparison(); f.reply(0); await request;
    const pointer = (type, x, y) => {
        const event = new Event(type);
        Object.assign(event, { clientX: x, clientY: y });
        f.dialog.dispatchEvent(event);
    };

    pointer('pointerdown', 200, 200); pointer('click', 200, 200);
    assert.equal(f.dialog.open, true);
    pointer('pointerdown', 200, 200); pointer('click', 50, 50);
    assert.equal(f.dialog.open, true);
    pointer('pointerdown', 50, 50); pointer('click', 200, 200);
    assert.equal(f.dialog.open, true);
    pointer('pointerdown', 50, 50); pointer('pointercancel', 50, 50); pointer('click', 50, 50);
    assert.equal(f.dialog.open, true);
    pointer('pointerdown', 50, 50); pointer('click', 50, 50);
    assert.equal(f.dialog.open, false);
    assert.deepEqual(f.component.comparisonItems.map(item => item.id), ['1', '2']);
    assert.equal(f.component.comparisonEnabled, true);
    assert.deepEqual(f.focused, ['opener']); f.component.destroy();
});

test('clear and exit returns focus to results when the entry is hidden by narrowed filters', () => {
    const f = fixture(); f.pair();
    const query = f.root.querySelector;
    f.root.querySelector = selector => selector === '[data-comparison-toggle]' ? { getClientRects: () => [] }
        : selector === '[data-comparison-results]' ? { focus() { f.focused.push('results'); } } : query(selector);

    f.component.clearComparison();

    assert.deepEqual(f.focused, ['results']);
    assert.equal(f.component.comparisonEnabled, false);
    assert.deepEqual(f.component.comparisonItems, []); f.component.destroy();
});

test('clear and exit invalidates an outstanding four Product read and hides comparison controls', async () => {
    const f = fixture(); f.pair();
    for (const id of ['3', '4']) f.component.toggleComparisonProduct(id, id, true);
    const request = f.component.openComparison();
    assert.equal(f.calls[0].ids.length, 4);
    f.component.clearComparison();
    f.reply(0); await request;
    assert.equal(f.component.comparisonEnabled, false);
    assert.deepEqual(f.component.comparisonItems, []);
    assert.equal(f.component.comparisonResult, null);
    assert.equal(f.dialog.open, false); f.component.destroy();
});

test('invalid identities are not coerced and pending replacement cannot survive a changed pair', () => {
    const f = fixture(); f.pair();
    for (const id of ['01', '1e0', 3, false, '-1', '999999999999999999999999']) f.component.toggleComparisonProduct(id, 'Invalid', true);
    assert.equal(f.component.comparisonReplacement, null);
    f.component.toggleComparisonProduct('3', 'Product 3', true); f.component.toggleComparisonProduct('4', 'Product 4', true); f.component.toggleComparisonProduct('5', 'Product 5', true); f.component.removeComparisonProduct('1');
    f.component.replaceComparisonProduct(0);
    assert.deepEqual(f.component.comparisonItems.map(i => i.id), ['2', '3', '4']); f.component.destroy();
});

test('filter membership labels only two selections and filter reset restores them without removing identities', () => {
    const f = fixture(); f.pair();
    f.component.updateComparisonMatches(['1', ...Array.from({ length: 30 }, (_, i) => String(i + 3))], '1');
    assert.deepEqual(f.component.comparisonItems.map(i => i.inResults), [true, false]);
    f.component.updateComparisonMatches(['1', '2'], '1');
    assert.deepEqual(f.component.comparisonItems.map(i => i.inResults), [true, true]);
    f.component.updateComparisonMatches([], 'other');
    assert.equal(f.component.comparisonItems.length, 0); f.component.destroy();
});

test('opening compares current ordered pair once and Close clears facts and returns focus', async () => {
    const f = fixture(); f.pair();
    const request = f.component.openComparison(); await f.component.openComparison();
    assert.equal(f.calls.length, 1); assert.equal(f.dialog.open, true); assert.equal(f.component.comparisonResult, null);
    f.reply(0); await request;
    assert.equal(f.component.comparisonResult.differences[0].values[1], '001');
    f.component.closeComparison();
    assert.equal(f.dialog.open, false); assert.equal(f.component.comparisonResult, null);
    assert.deepEqual(f.focused, ['opener']); assert.equal(f.component.comparisonItems.length, 2); f.component.destroy();
});

test('native cancel closes and missing opener uses stable tray focus once', async () => {
    const f = fixture(); f.pair(); const request = f.component.openComparison(); f.reply(0); await request;
    f.opener.isConnected = false;
    const event = new Event('cancel', { cancelable: true }); f.dialog.dispatchEvent(event);
    assert.equal(event.defaultPrevented, true); assert.equal(f.dialog.open, false);
    assert.deepEqual(f.focused, ['tray']); f.component.afterComparisonClose(); assert.deepEqual(f.focused, ['tray']); f.component.destroy();
});

for (const makeUnavailable of [opener => { opener.getClientRects = () => []; }, opener => { opener.disabled = true; }]) test('invisible or disabled old card opener falls back to the tray', async () => {
    const f = fixture(); f.pair(); const request = f.component.openComparison(); f.reply(0); await request;
    makeUnavailable(f.opener); f.component.closeComparison();
    assert.deepEqual(f.focused, ['tray']); f.component.destroy();
});

test('focus after stale selection uses the tray region if its Compare button is disabled', async () => {
    const f = fixture(); f.pair(); const request = f.component.openComparison(); f.reply(0); await request;
    f.opener.isConnected = false; f.fallback.disabled = true;
    const query = f.root.querySelector;
    f.root.querySelector = selector => selector === '[data-comparison-tray]' ? { focus() { f.focused.push('region'); } } : query(selector);
    f.component.closeComparison(); assert.deepEqual(f.focused, ['region']); f.component.destroy();
});

for (const change of [f => f.component.closeComparison(), f => f.component.removeComparisonProduct('1'), f => f.component.destroy(), f => f.browser.dispatchEvent(new Event('pagehide')), f => { f.root.dataset.catalogComparisonGroup = '2'; }, f => { f.root.isConnected = false; }]) {
    test(`obsolete response cannot publish after ${change.toString()}`, async () => {
        const f = fixture(); f.pair(); const request = f.component.openComparison(); change(f); f.reply(0); await request;
        assert.equal(f.component.comparisonResult, null); f.component.destroy();
    });
}

for (const changes of [{ componentId: 'other' }, { groupId: '2' }, { requestId: 'old' }, { pairIds: ['2', '1'] }, { products: [{ id: '9', code: 'foreign' }, { id: '2', code: 'Product 2' }] }]) {
    test(`foreign reply ${JSON.stringify(changes)} cannot leak facts`, async () => {
        const f = fixture(); f.pair(); const request = f.component.openComparison(); f.reply(0, changes); await request;
        assert.equal(f.component.comparisonResult, null); assert.equal(f.component.comparisonError, 'Retry comparison'); f.component.destroy();
    });
}

test('network retry clears old facts and stale-owner denial clears the pair with no foreign details', async () => {
    const f = fixture(); f.pair(); let request = f.component.openComparison(); f.calls[0].reject(new Error('network')); await request;
    assert.equal(f.component.comparisonError, 'Retry comparison'); assert.equal(f.component.comparisonItems.length, 2);
    request = f.component.retryComparison(); f.reply(1); await request;
    assert.ok(f.component.comparisonResult);
    request = f.component.retryComparison(); assert.equal(f.component.comparisonResult, null);
    f.calls[2].reject({ status: 404, message: 'secret foreign identity' }); await request;
    assert.equal(f.component.comparisonItems.length, 0); assert.equal(f.component.comparisonError, 'Choose again'); f.component.destroy();
});

test('window focus rereads while open, and BFCache return never revives old result data', async () => {
    const f = fixture(); f.pair(); const first = f.component.openComparison(); f.reply(0); await first;
    f.browser.dispatchEvent(new Event('focus')); assert.equal(f.calls.length, 2); assert.equal(f.component.comparisonResult, null);
    f.reply(1); await new Promise(resolve => setImmediate(resolve)); assert.ok(f.component.comparisonResult);
    f.browser.dispatchEvent(new Event('pagehide')); assert.equal(f.dialog.open, false); assert.equal(f.component.comparisonResult, null);
    f.browser.dispatchEvent(new Event('pageshow')); assert.equal(f.calls.length, 2);
    const next = f.component.openComparison(); f.reply(2); await next; assert.ok(f.component.comparisonResult); f.component.destroy();
});

test('a window-focus reread supersedes an outstanding read without old reply clearing current busy state', async () => {
    const f = fixture(); f.pair(); const first = f.component.openComparison();
    f.browser.dispatchEvent(new Event('focus')); assert.equal(f.calls.length, 2);
    f.reply(0); await first; assert.equal(f.component.comparisonResult, null); assert.equal(f.component.comparisonBusy, true);
    f.reply(1); await new Promise(resolve => setImmediate(resolve));
    assert.ok(f.component.comparisonResult); assert.equal(f.component.comparisonBusy, false); f.component.destroy();
});

test('new Group starts empty with an explanatory notice and unavailable filter root clears comparison safely', async () => {
    const f = fixture('10'); f.pair(); f.component.destroy();
    const other = fixture('11'); assert.equal(other.component.comparisonItems.length, 0); assert.equal(other.component.comparisonNotice, 'Group changed');
    other.pair(); const request = other.component.openComparison();
    other.component.updateComparisonMatches([], '11', { unavailable: true }); other.reply(0); await request;
    assert.equal(other.component.comparisonItems.length, 0); assert.equal(other.component.comparisonResult, null); assert.equal(other.dialog.open, false); other.component.destroy();
});

test('real catalogFilters hooks track full >24 results and preserve choices href return and request counts', async () => {
    const keys = ['window', 'document', 'location', 'history', 'requestAnimationFrame', 'cancelAnimationFrame'];
    const originals = Object.fromEntries(keys.map(key => [key, globalThis[key]]));
    const f = fixture(); let filters; const calls = [], frames = new Map(); let nextFrame = 0;
    try {
        globalThis.window = new EventTarget(); globalThis.document = new EventTarget(); document.hidden = true;
        globalThis.location = { href: 'https://catalog.test/dashboard/catalog/groups/1?external=keep' };
        globalThis.history = { state: { retain: true }, pushState(_s, _t, u) { location.href = String(u); }, replaceState(_s, _t, u) { location.href = String(u); } };
        globalThis.requestAnimationFrame = fn => { frames.set(++nextFrame, fn); return nextFrame; }; globalThis.cancelAnimationFrame = id => frames.delete(id);
        const snapshot = { schema: 2, groupId: '1', revision: '1', columns: [{ key: 'pressure', values: ['0', '001'] }], fields: [{ key: 'pressure', label: 'Pressure', column: 0, options: [{ value: '0', label: '0', code: 0 }, { value: '001', label: '001', code: 1 }] }], rows: Array.from({ length: 30 }, (_, i) => [String(i + 1), i === 29 ? 1 : 0]), presets: [], settings: { max_results: 'all', cards_per_row: 4, products_debounce_ms: 0 } };
        const root = new EventTarget(); root.dataset = { catalogLabels, datasetUrl: '/dataset', groupId: '1' }; root.querySelector = selector => selector === '[data-catalog-snapshot]' ? { textContent: JSON.stringify(snapshot), remove() {} } : null;
        filters = catalogFilters(); filters.$el = root; filters.$nextTick = fn => queueMicrotask(fn);
        filters.$wire = { loadCards(criteria, revision, requestId) { calls.push({ criteria, revision, requestId }); return new Promise(() => {}); } };
        filters.toggleComparisonProduct = (...args) => f.component.toggleComparisonProduct(...args);
        filters.updateComparisonMatches = (...args) => f.component.updateComparisonMatches(...args);
        filters.init();
        const before = JSON.stringify(filters.state), href = filters.productHref('/dashboard/catalog/products/30', '30'), count = calls.length;
        filters.chooseProductComparison('1', 'First'); filters.chooseProductComparison('30', 'Last chunk');
        assert.equal(f.component.comparisonItems[1].inResults, true); assert.equal(JSON.stringify(filters.state), before);
        assert.equal(filters.productHref('/dashboard/catalog/products/30', '30'), href); assert.equal(calls.length, count);
        filters.choose('filter', 'pressure', '0'); assert.equal(f.component.comparisonItems[1].inResults, false);
        assert.equal(f.component.comparisonItems.length, 2); filters.choose('reset'); assert.equal(f.component.comparisonItems[1].inResults, true);
        filters.exitLeaf(404); assert.equal(f.component.comparisonItems.length, 0);
    } finally {
        filters?.destroy(); f.component.destroy();
        await new Promise(resolve => setImmediate(resolve));
        for (const [key, value] of Object.entries(originals)) { if (value === undefined) delete globalThis[key]; else globalThis[key] = value; }
    }
});
