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
