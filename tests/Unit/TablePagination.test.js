import { test } from 'node:test';
import assert from 'node:assert/strict';
import { tablePagination } from '../../resources/js/table-pagination.js';

function fixture(t, width = 480, pageWidth = 373) {
    const classes = new Set();
    const parts = { '.fi-pagination-items': { width: pageWidth }, '.fi-pagination-records-per-page-select-ctn': { width: 62 }, '.fi-pagination-overview': { width: 226 } };
    for (const part of Object.values(parts)) part.getBoundingClientRect = () => ({ width: part.width });
    const footer = {
        clientWidth: width,
        classList: { contains: name => classes.has(name), remove: name => classes.delete(name), toggle: (name, on) => on ? classes.add(name) : classes.delete(name) },
        closest: () => root,
        querySelector: selector => parts[selector],
    };
    const root = { clientWidth: width, querySelectorAll: () => [footer] };
    const observers = [];
    let frame;
    const replacements = {
        MutationObserver: class { constructor(callback) { this.callback = callback; observers.push(this); } observe(target, options) { this.options = options; } disconnect() { this.disconnected = true; } },
        ResizeObserver: class { constructor(callback) { this.callback = callback; observers.push(this); } observe() {} disconnect() { this.disconnected = true; } },
        requestAnimationFrame: callback => { frame = callback; return 1; },
        cancelAnimationFrame: () => { frame = undefined; },
    };
    const previous = Object.fromEntries(Object.keys(replacements).map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    for (const [key, value] of Object.entries(replacements)) Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
    t.after(() => { for (const [key, descriptor] of Object.entries(previous)) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete globalThis[key]; } });
    const controller = tablePagination();
    controller.$el = { closest: () => root };
    controller.$nextTick = callback => callback();
    controller.init();
    const flush = () => { const callback = frame; frame = undefined; callback?.(); };
    return { controller, root, footer, parts, observers, flush, classes };
}

test('oversized page numbers fall back and recover when the controls fit again', t => {
    const { parts, observers, flush, classes } = fixture(t, 480, 430);
    assert.equal(classes.has('catalog-pagination-overflow'), true);
    observers[0].callback(); flush();
    assert.equal(classes.has('catalog-pagination-overflow'), true);

    parts['.fi-pagination-items'].width = 373;
    observers[0].callback(); flush();
    assert.equal(classes.has('catalog-pagination-overflow'), false);
});

test('the wide footer includes its summary in the fit check and leaves hidden tables alone', t => {
    const { root, parts, observers, flush, classes } = fixture(t, 832);
    parts['.fi-pagination-records-per-page-select-ctn'].width = 146;
    parts['.fi-pagination-overview'].width = 300;
    observers[0].callback(); flush();
    assert.equal(classes.has('catalog-pagination-overflow'), true);

    root.clientWidth = 0;
    parts['.fi-pagination-overview'].width = 226;
    observers[0].callback(); flush();
    assert.equal(classes.has('catalog-pagination-overflow'), true);

    root.clientWidth = 832;
    observers[0].callback(); flush();
    assert.equal(classes.has('catalog-pagination-overflow'), false);
});

test('an attribute-only server morph restores the overflow fallback without waiting for a resize', t => {
    const { footer, observers, classes, flush } = fixture(t, 480, 430);
    assert.equal(classes.has('catalog-pagination-overflow'), true);

    classes.delete('catalog-pagination-overflow');
    for (const observer of observers) {
        if (observer.options?.attributes && observer.options.attributeFilter.includes('class')) {
            observer.callback([{ type: 'attributes', target: footer, attributeName: 'class' }]);
        }
    }
    flush();
    assert.equal(classes.has('catalog-pagination-overflow'), true);
});

test('narrow layouts clear the fit override and teardown cancels pending work', t => {
    const { root, observers, flush, classes, controller } = fixture(t);
    classes.add('catalog-pagination-overflow');
    root.clientWidth = 400;
    observers[0].callback(); flush();
    assert.equal(classes.has('catalog-pagination-overflow'), false);

    classes.add('catalog-pagination-overflow');
    observers[0].callback();
    controller.destroy(); flush();
    assert.equal(observers.every(observer => observer.disconnected), true);
    assert.equal(classes.has('catalog-pagination-overflow'), true);
});
