import { test } from 'node:test';
import assert from 'node:assert/strict';
import { itemCountPreview } from '../../resources/js/item-count-preview.js';

function setup(t) {
    const keys = ['document','innerWidth','innerHeight'];
    const originals = Object.fromEntries(keys.map(key => [key,globalThis[key]]));
    const nodes = [];
    const pending = [];
    globalThis.innerWidth = 1000;
    globalThis.innerHeight = 800;
    globalThis.document = {createElement() { return {style:{},setAttribute() {},remove() { this.removed = true; }}; },body:{append(node) { nodes.push(node); }}};
    const preview = itemCountPreview('group-products',1,{loading:'LOADING',unavailable:'UNAVAILABLE'});
    preview.$el = {querySelector:() => ({setAttribute() {}}),getBoundingClientRect:() => ({left:40,bottom:100})};
    preview.$wire = {itemCountPreview:() => new Promise((resolve,reject) => pending.push({resolve,reject}))};
    t.after(() => { preview.destroy(); for (const [key,value] of Object.entries(originals)) {
        if (value === undefined) delete globalThis[key]; else globalThis[key] = value;
    }});
    return {preview,pending,nodes};
}

test('item count tooltip uses supplied loading and error wording and displays successful lines', async t => {
    const {preview,pending,nodes} = setup(t);
    const first = preview.show();
    assert.equal(nodes[0].textContent,'LOADING');
    pending[0].reject(new Error('offline'));
    await first;
    assert.equal(nodes[0].textContent,'UNAVAILABLE');
    const retry = preview.show();
    pending[1].resolve(['First item','1 more item']);
    await retry;
    assert.equal(nodes[0].textContent,'First item\n1 more item');
    assert.equal(nodes[0].hidden,false);
});

for (const failed of [false,true]) {
    test(`a stale ${failed ? 'failure' : 'success'} cannot overwrite a newer tooltip request`, async t => {
        const {preview,pending,nodes} = setup(t);
        const obsolete = preview.show();
        preview.hide();
        const current = preview.show();
        if (failed) pending[0].reject(new Error('obsolete')); else pending[0].resolve(['Obsolete']);
        await obsolete;
        assert.equal(nodes[0].textContent,'LOADING');
        pending[1].resolve(['Current item']);
        await current;
        assert.equal(nodes[0].textContent,'Current item');
        preview.hide();
        assert.equal(nodes[0].hidden,true);
    });
}

test('destroy removes the tooltip and ignores a late response', async t => {
    const {preview,pending,nodes} = setup(t);
    const request = preview.show();
    preview.destroy();
    pending[0].resolve(['Late']);
    await request;
    assert.equal(nodes[0].removed,true);
    assert.equal(nodes[0].textContent,'LOADING');
});
