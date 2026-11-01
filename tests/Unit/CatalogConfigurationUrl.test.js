import test from 'node:test';
import assert from 'node:assert/strict';
import { catalogConfigurationUrl } from '../../resources/js/catalog-configuration-url.js';

function fixture(initial = '?r=return&d[filter]=001', code = 'A0-B0') {
    let url = new URL(`https://catalog.test/dashboard/catalog/products/9${initial}`);
    const listeners = new Map(), writes = [], calls = [], copied = [];
    const browser = {
        get location() { return url; },
        history: { state: { journey: 'keep' }, pushState(state, _, href) { writes.push(['push', state, href]); url = new URL(href); }, replaceState(state, _, href) { writes.push(['replace', state, href]); url = new URL(href); } },
        addEventListener(name, fn) { listeners.set(name, fn); }, removeEventListener(name, fn) { if (listeners.get(name) === fn) listeners.delete(name); },
        navigator: { clipboard: { async writeText(value) { copied.push(value); } } },
    };
    const component = catalogConfigurationUrl({ productId: 9, componentId: 'component9', productUrl: '/dashboard/catalog/products/9', failureMessage: 'Retry', copySuccess: 'Copied', copyFailure: 'Could not copy' }, browser);
    const root = { isConnected: true, dataset: { configurationInitialCode: code ?? '' } };
    component.$el = root;
    component.$wire = Object.fromEntries(['loadConfigurationCode', 'restoreConfigurationHistory', 'chooseConfigurationOption', 'reevaluateConfiguration'].map(method => [method, (...args) => new Promise((resolve, reject) => calls.push({ method, args, resolve, reject }))]));
    component.init();
    return { component, root, browser, calls, writes, copied, listeners, setUrl(href) { url = new URL(href, url); }, emit(name, event = {}) { return listeners.get(name)?.(event); }, reply(index, changes = {}) { calls[index].resolve({ componentId: 'component9', productId: 9, accepted: true, code: 'A1-B1', ...changes }); } };
}
const tick = () => new Promise(resolve => setImmediate(resolve));

test('initial entry does not replay query or manufacture history; share uses settled code', async () => {
    const f = fixture('?configuration=rejected&r=keep');
    assert.equal(f.calls.length, 0); assert.equal(f.writes.length, 0);
    f.component.configurationDraft = 'not-current';
    await f.component.copyConfigurationLink();
    assert.equal(new URL(f.copied[0]).searchParams.get('configuration'), 'A0-B0');
    assert.equal(new URL(f.copied[0]).searchParams.get('r'), 'keep');
});

test('the server code seeds the owner once without manufacturing history or resetting a draft on reinit', async () => {
    const f = fixture();
    assert.equal(f.component.publishedCode, 'A0-B0');
    assert.equal(f.component.configurationDraft, 'A0-B0');
    assert.equal(f.writes.length, 0);
    f.component.configurationDraft = 'pending-edit';
    f.root.dataset.configurationInitialCode = 'later-server-seed';
    f.component.init();
    assert.equal(f.component.configurationDraft, 'pending-edit');
    assert.equal(f.component.publishedCode, 'A0-B0');
    assert.equal(f.listeners.size, 3);
    const request = f.component.loadConfiguration(); f.reply(0); await request;
    assert.equal(f.component.publishedCode, 'A1-B1');
    assert.equal(f.component.configurationDraft, 'A1-B1');
    assert.equal(f.browser.location.searchParams.get('configuration'), 'A1-B1');
});

test('an incomplete server seed remains unshareable without publishing a code', async () => {
    const f = fixture('?r=keep', null);
    assert.equal(f.component.publishedCode, null);
    assert.equal(f.component.configurationDraft, '');
    await f.component.copyConfigurationLink();
    assert.equal(f.copied.length, 0);
    assert.equal(f.writes.length, 0);
});

test('a disconnected event element does not discard a valid reply for its connected owner', async () => {
    const f = fixture();
    const request = f.component.chooseConfiguration('1', '2');
    f.component.$el = { isConnected: false };
    f.reply(0); await request;
    assert.equal(f.root.isConnected, true);
    assert.equal(f.browser.location.searchParams.get('configuration'), 'A1-B1');
    assert.equal(f.writes.length, 1);
});

test('Load then ordinary choice then refresh publish actual replies with push push replace and preserve other state', async () => {
    const f = fixture(); f.component.configurationDraft = 'A1-B1';
    const load = f.component.loadConfiguration(); f.reply(0); await load;
    const choice = f.component.chooseConfiguration('1', '2'); f.reply(1, { code: 'A0-B1' }); await choice;
    const refresh = f.component.refreshConfiguration(); f.reply(2, { code: 'A0-B0' }); await refresh;
    assert.deepEqual(f.writes.map(w => w[0]), ['push', 'push', 'replace']);
    assert.equal(f.browser.location.searchParams.get('r'), 'return');
    assert.equal(f.browser.location.searchParams.get('d[filter]'), '001');
    assert.deepEqual(f.writes[0][1], { journey: 'keep' });
    assert.deepEqual(f.calls.map(c => c.method), ['loadConfigurationCode', 'chooseConfigurationOption', 'reevaluateConfiguration']);
});

test('rejected Load leaves URL intact while updating share to actual current result', async () => {
    const f = fixture('?configuration=A0-B0'); f.component.configurationDraft = 'bad';
    const request = f.component.loadConfiguration(); f.reply(0, { accepted: false, code: null }); await request;
    assert.equal(f.writes.length, 0); assert.equal(f.component.publishedCode, null);
    await f.component.copyConfigurationLink(); assert.equal(f.copied.length, 0);
});

test('ordinary commands are dropped while busy and latest history alone restores after settlement without writeback', async () => {
    const f = fixture(); const request = f.component.chooseConfiguration('1', '2');
    await f.component.loadConfiguration(); await f.component.refreshConfiguration();
    f.setUrl('?configuration=first'); f.emit('popstate');
    f.setUrl('?configuration=latest&r=kept'); f.emit('popstate');
    assert.equal(f.calls.length, 1);
    f.reply(0); await tick();
    assert.equal(f.calls.length, 2); assert.equal(f.writes.length, 0);
    assert.deepEqual(f.calls[1].args, ['latest']);
    f.reply(1, { code: 'latest' }); await request;
    assert.equal(f.writes.length, 0); assert.equal(f.component.publishedCode, 'latest');
});

test('absent history is explicit fresh initialization and malformed duplicate query is not treated as absence', async () => {
    const f = fixture(); f.setUrl('?r=keep'); f.emit('popstate');
    assert.deepEqual(f.calls[0].args, [null]); f.reply(0, { code: 'A0-B0' }); await tick();
    f.setUrl('?configuration=A0&configuration=B0'); f.emit('popstate');
    assert.deepEqual(f.calls[1].args, [[]]); f.reply(1, { accepted: false, code: 'A0-B0' }); await tick();
    assert.equal(f.writes.length, 0);
});

test('no-op results add no entry and incomplete results remove stale code', async () => {
    const f = fixture('?configuration=A0-B0');
    let request = f.component.refreshConfiguration(); f.reply(0, { code: 'A0-B0' }); await request;
    assert.equal(f.writes.length, 0);
    request = f.component.chooseConfiguration('1', '2'); f.reply(1, { code: null }); await request;
    assert.equal(f.browser.location.searchParams.has('configuration'), false);
    assert.equal(f.writes[0][0], 'push');
});

for (const change of [{ productId: 10 }, { componentId: 'foreign' }]) test(`foreign reply ${JSON.stringify(change)} cannot publish URL or share`, async () => {
    const f = fixture(); const request = f.component.loadConfiguration(); f.reply(0, change); await request;
    assert.equal(f.writes.length, 0); assert.equal(f.component.publishedCode, null);
});

test('teardown removes listeners and late replies cannot publish', async () => {
    const f = fixture(); const request = f.component.loadConfiguration(); f.component.destroy();
    assert.equal(f.listeners.size, 0); f.reply(0); await request;
    assert.equal(f.writes.length, 0);
});

test('changed Product path and disconnected roots ignore old replies', async () => {
    for (const dispose of [f => f.setUrl('/dashboard/catalog/products/10'), f => { f.root.isConnected = false; }]) {
        const f = fixture(); const request = f.component.loadConfiguration(); dispose(f); f.reply(0); await request;
        assert.equal(f.writes.length, 0);
    }
});

test('BFCache return intentionally restores current query once and repeated init does not multiply listeners', async () => {
    const f = fixture(); f.component.init(); assert.equal(f.listeners.size, 3);
    f.emit('pagehide'); f.setUrl('?configuration=A1-B1'); f.emit('pageshow', { persisted: true });
    assert.equal(f.calls.length, 1); f.reply(0); await tick();
    assert.equal(f.writes.length, 0); assert.equal(f.component.configurationBusy, false);
});

test('failed requests and clipboard failures do not claim or share unverified success', async () => {
    const f = fixture(); const request = f.component.loadConfiguration(); f.calls[0].reject(new Error('network')); await request;
    assert.equal(f.component.publishedCode, null); assert.equal(f.component.configurationError, 'Retry');
    f.component.publishedCode = 'A0-B0'; f.browser.navigator.clipboard.writeText = async () => { throw new Error('denied'); };
    await f.component.copyConfigurationLink(); assert.equal(f.component.configurationCopyStatus, 'Could not copy');
});

test('accepted Load recovers malformed configuration families while preserving unrelated state', async () => {
    const f = fixture('?configuration[]=bad&configuration[x]=wrong&configuration=old&r=keep&d[filter]=001&configuration_note=retain');
    const request = f.component.loadConfiguration(); f.reply(0); await request;
    assert.deepEqual([...f.browser.location.searchParams.keys()].filter(key => key === 'configuration' || key.startsWith('configuration[')), ['configuration']);
    assert.equal(f.browser.location.searchParams.get('configuration'), 'A1-B1');
    assert.equal(f.browser.location.searchParams.get('r'), 'keep');
    assert.equal(f.browser.location.searchParams.get('d[filter]'), '001');
    assert.equal(f.browser.location.searchParams.get('configuration_note'), 'retain');
    assert.deepEqual(f.writes[0][1], { journey: 'keep' });
});

test('copied link cleans malformed configuration family without changing current URL or history', async () => {
    const f = fixture('?configuration[]=bad&configuration[x]=wrong&r=keep&d[filter]=001');
    const before = f.browser.location.href;
    await f.component.copyConfigurationLink();
    const share = new URL(f.copied[0]);
    assert.deepEqual([...share.searchParams.keys()].filter(key => key === 'configuration' || key.startsWith('configuration[')), ['configuration']);
    assert.equal(share.searchParams.get('configuration'), 'A0-B0');
    assert.equal(share.searchParams.get('r'), 'keep'); assert.equal(share.searchParams.get('d[filter]'), '001');
    assert.equal(f.browser.location.href, before); assert.equal(f.writes.length, 0);
});

for (const method of ['chooseConfiguration', 'refreshConfiguration']) test(`incomplete ${method} removes the whole owned query family`, async () => {
    const f = fixture('?configuration[]=bad&configuration[deep][key]=wrong&configuration=old&r=keep');
    const request = f.component[method]('1', '2'); f.reply(0, { code: null }); await request;
    assert.deepEqual([...f.browser.location.searchParams.keys()], ['r']);
    assert.equal(f.browser.location.searchParams.get('r'), 'keep');
    assert.equal(f.writes[0][0], method === 'refreshConfiguration' ? 'replace' : 'push');
});

test('rejected Load and accepted or rejected history preserve malformed address exactly', async () => {
    const f = fixture('?configuration[]=bad&r=keep'); const before = f.browser.location.href;
    let request = f.component.loadConfiguration(); f.reply(0, { accepted: false, code: 'A0-B0' }); await request;
    assert.equal(f.browser.location.href, before); assert.equal(f.writes.length, 0);
    request = f.component.restoreConfigurationUrl(); f.reply(1, { accepted: false, code: 'A0-B0' }); await request;
    assert.equal(f.browser.location.href, before); assert.equal(f.writes.length, 0);
    request = f.component.restoreConfigurationUrl(); f.reply(2); await request;
    assert.equal(f.browser.location.href, before); assert.equal(f.writes.length, 0);
});

test('an already canonical unchanged code keeps query ordering and creates no history entry', async () => {
    const f = fixture('?configuration=A0-B0&r=keep&d[filter]=001');
    const before = f.browser.location.href;
    const request = f.component.refreshConfiguration(); f.reply(0, { code: 'A0-B0' }); await request;
    assert.equal(f.browser.location.href, before); assert.equal(f.writes.length, 0);
});
