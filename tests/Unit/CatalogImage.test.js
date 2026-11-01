import { test } from 'node:test';
import assert from 'node:assert/strict';
import { catalogImage } from '../../resources/js/catalog-image.js';

const image = (overrides = {}) => ({ id: 1, url: '/managed/1/original.png', thumbnail_url: '/managed/1/thumb.jpg', width: 40, height: 20, ...overrides });

test('missing metadata reserves a truthful placeholder without an image request', () => {
    const state = catalogImage();
    assert.equal(state.status, 'missing');
    assert.equal(state.currentUrl, null);
    assert.deepEqual(state.attempts, []);
});

test('a successful thumbnail keeps its intrinsic size and repeated metadata does not restart it', () => {
    const state = catalogImage(image());
    const attempt = state.attemptIdentity;
    assert.equal(state.currentUrl, '/managed/1/thumb.jpg');
    state.onLoad(attempt);
    state.setImage(image());
    assert.equal(state.status, 'ready');
    assert.equal(state.attemptIdentity, attempt);
    assert.equal(state.image.width, 40);
});

test('thumbnail failure retries the original only once then becomes unavailable', () => {
    const state = catalogImage(image());
    const thumbnail = state.attemptIdentity;
    state.onError(thumbnail);
    const original = state.attemptIdentity;
    assert.notEqual(original, thumbnail);
    assert.equal(state.currentUrl, '/managed/1/original.png');
    state.onLoad(thumbnail);
    state.onError(thumbnail);
    assert.equal(state.status, 'loading');
    state.onError(original);
    assert.equal(state.status, 'unavailable');
    assert.equal(state.currentUrl, null);
    assert.deepEqual(state.attempts, []);
    state.onLoad(original);
    assert.equal(state.status, 'unavailable');
});

test('metadata original fallback needs no redundant retry and may load successfully', () => {
    const state = catalogImage(image({ thumbnail_url: '/managed/1/original.png' }));
    state.onLoad(state.attemptIdentity);
    assert.equal(state.status, 'ready');
    state.onError(state.attemptIdentity);
    assert.equal(state.status, 'unavailable');
});

test('new image identity resets even at the same URL and ignores both late event types', () => {
    const state = catalogImage(image());
    const obsolete = state.attemptIdentity;
    state.setImage(image({ id: 2 }));
    const current = state.attemptIdentity;
    assert.notEqual(current, obsolete);
    state.onError(obsolete);
    state.onLoad(obsolete);
    assert.equal(state.status, 'loading');
    assert.equal(state.currentUrl, '/managed/1/thumb.jpg');
    state.onLoad(current);
    assert.equal(state.status, 'ready');
    state.setImage(null);
    state.onError(current);
    assert.equal(state.status, 'missing');
});

test('changed URL on the same image starts a fresh attempt and obsolete original failure is ignored', () => {
    const state = catalogImage(image());
    state.onError(state.attemptIdentity);
    const original = state.attemptIdentity;
    state.setImage(image({ thumbnail_url: '/managed/1/new.jpg' }));
    state.onError(original);
    assert.equal(state.currentUrl, '/managed/1/new.jpg');
    assert.equal(state.status, 'loading');
});

test('an SSR image already broken before Alpine initializes retries the original', () => {
    const state = catalogImage(image());
    state.$refs = { initialImage: { complete: true, naturalWidth: 0 } };
    state.$nextTick = fn => fn();
    state.init();
    assert.equal(state.currentUrl, '/managed/1/original.png');
    assert.equal(state.attempts.length, 1);
});

test('an SSR image already loaded before Alpine initializes settles without another attempt', () => {
    const state = catalogImage(image());
    state.$refs = { initialImage: { complete: true, naturalWidth: 40 } };
    state.$nextTick = fn => fn();
    state.init();
    assert.equal(state.status, 'ready');
    assert.equal(state.attemptIdentity, 0);
});

test('a deferred SSR check cannot overwrite the dynamically changed current image', () => {
    const state = catalogImage(image());
    state.$refs = { initialImage: { complete: true, naturalWidth: 0 } };
    let inspect;
    state.$nextTick = fn => { inspect = fn; };
    state.init();
    state.setImage(image({ id: 2 }));
    inspect();
    assert.equal(state.currentUrl, '/managed/1/thumb.jpg');
    assert.equal(state.status, 'loading');
});
