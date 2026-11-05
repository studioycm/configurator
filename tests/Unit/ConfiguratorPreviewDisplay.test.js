import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizePreviewDisplay, previewDisplayProfileState, configuratorPreviewDisplay } from '../../resources/js/configurator-preview-display.js';

function environment(t, raw = null, denied = false) {
    const values = new Map(); const listeners = new Map(); const writes = [];
    const window = { location: { pathname: '/admin/configurators/10/edit' }, addEventListener(name, listener) { listeners.set(name, listener); }, removeEventListener(name, listener) { if (listeners.get(name) === listener) listeners.delete(name); }, dispatchEvent(event) { listeners.get(event.type)?.(event); } };
    const storage = { getItem(key) { if (denied) throw Error('denied'); return values.get(key) ?? raw; }, setItem(key, value) { if (denied) throw Error('denied'); values.set(key, value); writes.push(key); } };
    const original = {};
    for (const [key, value] of Object.entries({ window, localStorage: storage, CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } } })) {
        original[key] = Object.getOwnPropertyDescriptor(globalThis, key); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
    }
    t.after(() => { for (const [key, descriptor] of Object.entries(original)) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete globalThis[key]; } });
    const create = (user = 1, scope) => { const display = configuratorPreviewDisplay({ user, scope, componentId: 'preview-1', configuratorId: 10 }); display.$el = { querySelector() { return null; }, contains() { return false; } }; display.$nextTick = callback => callback(); display.init(); return display; };
    return { create, values, listeners, writes, window };
}

test('display defaults and strict allowlists exclude data and invalid preference types', () => {
    const defaults = normalizePreviewDisplay(null);
    assert.equal(defaults.presentation, 'all'); assert.deepEqual(defaults.metadata, { all: { codes: true, ids: false }, catalog: { codes: false, ids: false } });
    assert.equal(defaults.notes, true); assert.equal(defaults.trace, false); assert.equal(defaults.compact, false);
    assert.equal(defaults.attributeSize, 14); assert.equal(defaults.valueSize, 12);
    assert.deepEqual(defaults.ratios, { notes: 2 / 3, trace: 2 / 3, both: 0.5 });
    assert.deepEqual(normalizePreviewDisplay({ presentation: 'other', notes: 'false', trace: 1, compact: [], attributeSize: 15, valueSize: '14', ratios: { notes: 0, trace: Infinity, both: 1 }, metadata: { all: { codes: 'false', ids: 1 } }, captures: ['private'] }), defaults);
    assert.equal(normalizePreviewDisplay({ trace: true }).trace, true);
    assert.equal(previewDisplayProfileState({ ...defaults, selections: ['private'] }).selections, undefined);
});

test('nested metadata patches retain the other presentation and all state during save', t => {
    const { create, writes } = environment(t); const display = create(); display.phase = 'request';
    display.patch({ metadata: { all: { ids: true } } }); display.setPresentation('catalog'); display.patchMetadata({ codes: true });
    assert.deepEqual(display.settings.metadata, { all: { codes: true, ids: true }, catalog: { codes: true, ids: false } });
    display.setPresentation('all'); assert.equal(display.showCodes, true); assert.equal(display.showIds, true); assert.equal(display.phase, 'request');
    assert.equal(writes.length, 4);
});

test('legacy trace migrates locally with no wire dependency and profiles isolate user and page', t => {
    const { create, window } = environment(t, '{"trace":true}'); const first = create(); assert.equal(first.settings.trace, true);
    first.patch({ notes: false, ratios: { notes: 0.7 } }); assert.equal(create().settings.notes, false);
    assert.equal(create(2).settings.notes, true); window.location.pathname = '/admin/configurators/11/edit'; assert.equal(create().settings.notes, true);
});

test('corrupt oversized and denied storage retain usable local display controls', t => {
    const { create } = environment(t, '{broken', true); const display = create(); assert.doesNotThrow(() => display.patch({ compact: true })); assert.equal(display.settings.compact, true);
});

test('oversized storage falls back and reset restores every ratio without changing theme', t => {
    const { create, window } = environment(t, JSON.stringify({ trace: true, extra: 'x'.repeat(33000) })); let themeEvents = 0; window.addEventListener('theme-changed', () => themeEvents++);
    const display = create(); assert.equal(display.settings.trace, false); display.patch({ trace: true, notes: false, ratios: { both: 0.8 } }); display.resetDisplay();
    assert.deepEqual(display.settings, normalizePreviewDisplay(null)); assert.equal(themeEvents, 0); display.setTheme('dark'); assert.equal(themeEvents, 1);
});

test('inspection reveal matches component scope and cleanup removes listeners', t => {
    const { create, listeners, window } = environment(t); const display = create();
    window.dispatchEvent(new CustomEvent('configurator-preview-inspection-reveal', { detail: { componentId: 'other', configuratorId: 10 } })); assert.equal(display.settings.trace, false);
    window.dispatchEvent(new CustomEvent('configurator-preview-inspection-reveal', { detail: { componentId: 'preview-1', configuratorId: 10 } })); assert.equal(display.settings.trace, true);
    display.destroy(); assert.equal(listeners.size, 0);
});

test('all panel combinations retain independent ratios through hide restore reload and reset without server access', t => {
    const { create } = environment(t); const display = create();
    display.$wire = new Proxy({}, { get() { throw Error('Display controls must not access Livewire'); } });
    const editor = { source: 'unsaved', choices: { a: 'a1' }, code: 'A1', drafts: ['later text'] };
    const before = structuredClone(editor);
    display.setSplitRatio(0.72); display.patch({ notes: false, trace: true }); assert.equal(display.panelCombination, 'trace'); assert.equal(display.splitRatio, 2 / 3);
    display.setSplitRatio(0.63); display.patch({ notes: true }); assert.equal(display.panelCombination, 'both'); assert.equal(display.splitRatio, 0.5); display.setSplitRatio(0.54);
    display.patch({ notes: false, trace: false }); assert.equal(display.visibleSideCount, 0);
    display.patch({ notes: true }); assert.equal(display.splitRatio, 0.72);
    display.patch({ trace: true }); assert.equal(display.splitRatio, 0.54);
    display.patch({ notes: false }); assert.equal(display.splitRatio, 0.63);
    assert.deepEqual(create().settings.ratios, { notes: 0.72, trace: 0.63, both: 0.54 });
    for (let index = 0; index < 100; index++) { display.setPresentation(index % 2 ? 'all' : 'catalog'); display.patch({ compact: index % 2 === 0, attributeSize: [12,14,16][index % 3], valueSize: [11,12,14][index % 3] }); }
    display.patch({ notes: 'bad', valueSize: 999, metadata: { all: { ids: 'bad' } } }); assert.equal(display.settings.notes, false);
    assert.deepEqual(editor, before); display.resetDisplay(); assert.deepEqual(display.settings.ratios, { notes: 2 / 3, trace: 2 / 3, both: 0.5 });
});

test('application registers the display controller both before and after Alpine initialization', async () => {
    const { readFileSync } = await import('node:fs');
    const { runInNewContext } = await import('node:vm');
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const importedNames = [...source.matchAll(/^import \{ ([^}]+) \}/gm)].flatMap(match => match[1].split(',').map(name => name.trim()));
    for (const alreadyInitialized of [false, true]) {
        const registered = new Map(); let initialize;
        const stores = new Map();
        const alpine = {
            data(name, factory) { registered.set(name, factory); },
            store(name, value) {
                if (arguments.length > 1) stores.set(name, value);
                return stores.get(name);
            },
        };
        const context = { ...Object.fromEntries(importedNames.map(name => [name, () => {}])), configuratorPreviewDisplay, window: { Alpine: alreadyInitialized ? alpine : undefined }, document: { addEventListener(name, callback) { if (name === 'alpine:init') initialize = callback; } } };
        runInNewContext(source.replace(/^import .*;\n/gm, ''), context);
        if (!alreadyInitialized) { context.window.Alpine = alpine; initialize(); }
        assert.equal(registered.get('configuratorPreviewDisplay'), configuratorPreviewDisplay);
        assert.equal(registered.has('localTraceProfile'), false);
    }
});
