import { test } from 'node:test';
import assert from 'node:assert/strict';
import { browserProfile, tableProfileState, personalAppearance, localTableProfile, rememberSchemaState, adminBrowserProfile, localSectionProfile, localOrderProfile } from '../../resources/js/admin-profile.js';

function storage() {
    const values = new Map(); const reads = []; const writes = [];
    return { values, reads, writes, getItem(key) { reads.push(key); return values.get(key) ?? null; }, setItem(key, value) { writes.push(key); values.set(key, value); }, removeItem(key) { values.delete(key); } };
}

test('local profiles load only their scope, isolate accounts and avoid unchanged writes', () => {
    const store = storage(); const options = browserProfile(1, 'table:options:list', store);
    options.save({ search: 'one' }); options.save({ search: 'one' });
    assert.equal(store.writes.length, 1);
    assert.deepEqual(browserProfile(1, 'table:options:list', store).read(), { search: 'one' });
    assert.equal(browserProfile(2, 'table:options:list', store).read(), null);
    assert.equal(browserProfile(1, 'table:values:list', store).read(), null);
    assert.equal(new Set(store.reads).size, 3);
});

test('bad JSON, oversized profiles and blocked storage fall back without breaking the page', () => {
    const store = storage(); const profile = browserProfile(1, 'table:options:list', store);
    store.values.set(profile.key, '{broken'); assert.equal(profile.read(), null);
    store.values.set(profile.key, '[]'); assert.equal(profile.read(), null);
    assert.equal(profile.save({ search: 'x'.repeat(40000) }), false);
    const denied = browserProfile(1, 'table:values:list', { getItem() { throw Error(); }, setItem() { throw Error(); }, removeItem() { throw Error(); } });
    assert.equal(denied.read(), null); assert.equal(denied.save({ search: 'one' }), false); assert.doesNotThrow(() => denied.clear());
});

test('table profile excludes draft fields, selection and pending filters and preserves intentional empty state', () => {
    const state = tableProfileState({ tableColumns: [{ name: 'code', isToggled: false, label: 'Code' }], tableSort: null, tableSearch: '', tableSearchScope: 'all', tableFilters: {}, tableDeferredFilters: { tags: { values: ['unsaved'] } }, tableRecordsPerPage: 25, selectedTableRecords: [10], editorData: { label: 'draft' }, showSelection: true, showOrderControls: false });
    assert.deepEqual(state, { columns: [{ name: 'code', isToggled: false }], sort: null, search: '', searchScope: 'all', filters: {}, perPage: 25, showSelection: true, showOrderControls: false });
});


test('unavailable browser storage getter is handled and appearance restores only bounded compatible values', () => {
    const previous = Object.getOwnPropertyDescriptor(globalThis, 'localStorage');
    Object.defineProperty(globalThis, 'localStorage', { configurable: true, get() { throw Error('denied'); } });
    try { assert.equal(browserProfile(1, 'global').read(), null); assert.equal(browserProfile(1, 'global').save({ theme: 'dark' }), false); }
    finally { if (previous) Object.defineProperty(globalThis, 'localStorage', previous); else delete globalThis.localStorage; }
    const config = { settings: { header_height: 48, logo_height: 28, page_title_size: 20, cell_padding_block: 6, modal_width: 'medium', slide_over_width: 'medium', show_configurator_loading_indicator: false }, ranges: { header_height: [40, 64], logo_height: [20, 32], page_title_size: [14, 32], cell_padding_block: [4, 8] } };
    assert.equal(personalAppearance({ ...config.settings, header_height: 999 }, config), null);
    assert.equal(personalAppearance({ ...config.settings, header_height: 40, logo_height: 32 }, config), null);
    assert.equal(personalAppearance({ ...config.settings, modal_width: 'enormous' }, config), null);
    assert.equal(personalAppearance({ ...config.settings, cell_padding_block: 4 }, config).variables['--aquestia-cell-padding-block'], '4px');
});

function tableEnvironment(t) {
    const store = storage(); const callbacks = new Set(); const calls = [];
    const previous = Object.fromEntries(['window', 'localStorage'].map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    globalThis.localStorage = store;
    globalThis.window = { location: { search: '' }, Livewire: { interceptMessage(callback) { callbacks.add(callback); return () => callbacks.delete(callback); } } };
    const wire = { $id: 'one', tableColumns: [{ name: 'code', isToggled: true }], tableSort: null, tableSearch: '', tableSearchScope: 'all', tableFilters: {}, tableRecordsPerPage: 10,
        async $call(name, changes) { calls.push({ name, changes }); const mapping = { sort: 'tableSort', search: 'tableSearch', perPage: 'tableRecordsPerPage' }; for (const [key, value] of Object.entries(changes)) this[mapping[key] ?? key] = value; },
    };
    t.after(() => { for (const [key, descriptor] of Object.entries(previous)) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete globalThis[key]; } });
    return { store, callbacks, calls, wire };
}

test('a page restores all differing table settings once, respects explicit URLs, and removes its listener', async t => {
    const { store, callbacks, calls, wire } = tableEnvironment(t);
    const saved = { ...tableProfileState(wire), search: 'saved', sort: 'code:desc', perPage: 25 };
    browserProfile(1, 'table:options', store).save(saved);
    window.location.search = '?search=explicit'; wire.tableSearch = 'explicit';
    const controller = localTableProfile({ user: 1, scope: 'options', query: { search: 'search' } }); controller.$wire = wire;
    await controller.init();
    assert.deepEqual(calls, [{ name: 'restoreTableBrowserProfile', changes: { sort: 'code:desc', perPage: 25 } }]);
    assert.equal(wire.tableSearch, 'explicit');
    controller.destroy(); assert.equal(callbacks.size, 0);
});

test('matching session state causes no restore request and only successful owner messages are saved', async t => {
    const { store, callbacks, calls, wire } = tableEnvironment(t);
    browserProfile(1, 'table:options', store).save(tableProfileState(wire));
    const controller = localTableProfile({ user: 1, scope: 'options' }); controller.$wire = wire;
    await controller.init(); assert.equal(calls.length, 0);
    wire.tableSearch = 'committed';
    for (const callback of callbacks) callback({ message: { component: { id: 'other' } }, onSuccess() { throw Error('unrelated component'); } });
    assert.equal(browserProfile(1, 'table:options', store).read().search, '');
    for (const callback of callbacks) callback({ message: { component: { id: 'one' } }, onSuccess(callback) { callback({ onMorphed: callback => callback() }); } });
    assert.equal(browserProfile(1, 'table:options', store).read().search, 'committed');
});

test('failed restoration retains the saved profile for retry', async t => {
    const { store, wire } = tableEnvironment(t);
    browserProfile(1, 'table:options', store).save({ perPage: 25 });
    wire.$call = async () => { throw Error('offline'); };
    const controller = localTableProfile({ user: 1, scope: 'options' }); controller.$wire = wire;
    await controller.init();
    assert.deepEqual(browserProfile(1, 'table:options', store).read(), { perPage: 25 });
});


test('explicit nested filter URLs take precedence over the saved browser profile', async t => {
    const { store, calls, wire } = tableEnvironment(t);
    browserProfile(1, 'table:options', store).save({ filters: { active: { value: false } } });
    window.location.search = '?filters%5Bactive%5D%5Bvalue%5D=1';
    wire.tableFilters = { active: { value: true } };
    const controller = localTableProfile({ user: 1, scope: 'options', query: { filters: 'filters' } }); controller.$wire = wire;
    await controller.init();
    assert.deepEqual(calls, []);
    assert.deepEqual(browserProfile(1, 'table:options', store).read().filters, wire.tableFilters);
});

test('tabs without query persistence ignore unrelated URL parameters', t => {
    const { store } = tableEnvironment(t);
    window.location = { pathname: '/admin/products/1/edit', search: '?tab=unrelated' };
    const element = {}; const data = { tab: 'first', getTabs: () => ['first', 'second'] };
    window.Alpine = { $data: () => data };
    browserProfile(1, 'page:/admin/products/1/edit:tab:details', store).save({ value: 'second' });
    rememberSchemaState(element, 'first', { user: 1, kind: 'tab', key: 'details', query: null });
    assert.equal(data.tab, 'second');
});


test('global preferences preserve personal overrides when shared defaults change and remove listeners', t => {
    const { store } = tableEnvironment(t);
    const styles = {}; const listeners = new Map(); const watchers = new Map();
    const previousDocument = Object.getOwnPropertyDescriptor(globalThis, 'document');
    globalThis.document = { documentElement: { style: { setProperty(key, value) { styles[key] = value; } } }, querySelector: () => null };
    t.after(() => { if (previousDocument) Object.defineProperty(globalThis, 'document', previousDocument); else delete globalThis.document; });
    const sidebar = { isOpenDesktop: true, isOpen: true, collapsedGroups: [] };
    window.innerWidth = 1440; window.Alpine = { store: () => sidebar };
    window.addEventListener = (name, callback) => listeners.set(name, callback);
    window.removeEventListener = name => listeners.delete(name);
    window.dispatchEvent = event => listeners.get(event.type)?.(event);
    const config = { user: 1, variables: {}, settings: { header_height: 48, logo_height: 28, page_title_size: 20, cell_padding_block: 6, modal_width: 'medium', slide_over_width: 'medium', show_configurator_loading_indicator: false }, ranges: { cell_padding_block: [4, 8] } };
    browserProfile(1, 'global', store).save({ sidebarExpanded: false, collapsedGroups: ['Library'], theme: 'dark', appearance: { settings: { cell_padding_block: 4, show_configurator_loading_indicator: true } } });
    const controller = adminBrowserProfile(config); controller.$watch = (key, callback) => watchers.set(key, callback); controller.$nextTick = callback => callback(); controller.init();
    assert.equal(sidebar.isOpenDesktop, false); assert.deepEqual(sidebar.collapsedGroups, ['Library']); assert.equal(styles['--aquestia-cell-padding-block'], '4px');
    listeners.get('admin-appearance-saved')({ detail: { settings: { ...config.settings, cell_padding_block: 8, header_height: 60 }, variables: {} } });
    assert.equal(styles['--aquestia-cell-padding-block'], '4px'); assert.equal(styles['--aquestia-shell-header-min-height'], '60px');
    listeners.get('admin-personal-appearance-saved')({ detail: { settings: { ...config.settings, cell_padding_block: 4, show_configurator_loading_indicator: true } } });
    assert.deepEqual(browserProfile(1, 'global', store).read().appearance.settings, { cell_padding_block: 4 });
    watchers.get('sidebar.isOpenDesktop')(true);
    assert.equal(browserProfile(1, 'global', store).read().sidebarExpanded, true);
    controller.destroy(); assert.equal(listeners.size, 0);
});


test('section preferences bind to native child state and persist subsequent toggles', t => {
    const { store } = tableEnvironment(t);
    window.location = { pathname: '/admin/configurators/1/edit', search: '' };
    const section = {}; const native = { isCollapsed: true }; let watch;
    window.Alpine = { $data: element => { assert.equal(element, section); return native; } };
    browserProfile(1, 'page:/admin/configurators/1/edit:section:context', store).save({ value: false });
    const controller = localSectionProfile({ user: 1, key: 'context' });
    controller.$el = { querySelector: selector => { assert.equal(selector, ':scope > section'); return section; } };
    controller.$nextTick = callback => callback(); controller.$watch = (key, callback) => { assert.equal(key, 'section.isCollapsed'); watch = callback; };
    controller.init(); assert.equal(native.isCollapsed, false);
    watch(true);
    assert.deepEqual(browserProfile(1, 'page:/admin/configurators/1/edit:section:context', store).read(), { value: true });
});


test('form ordering visibility persists without draft data and survives regenerated repeater identifiers', t => {
    const { store } = tableEnvironment(t); window.location = { pathname: '/admin/groups/1/edit', search: '' };
    const mount = key => {
        const controller = localOrderProfile({ user: 1, key }); const watchers = new Map();
        controller.$watch = (name, callback) => watchers.set(name, callback); controller.init();
        return { controller, watchers };
    };
    const first = mount('data.filters.00000000-0000-4000-8000-000000000001.values');
    first.controller.showDragOrder = true; first.controller.showButtonOrder = false; first.watchers.get('showDragOrder')();
    const second = mount('data.filters.00000000-0000-4000-8000-000000000002.values');
    assert.equal(second.controller.showDragOrder, true); assert.equal(second.controller.showButtonOrder, false);
    assert.deepEqual(JSON.parse([...store.values.values()][0]), { showDragOrder: true, showButtonOrder: false });
    assert.equal(localOrderProfile({ user: 2, key: 'data.filters' }).showDragOrder, false);
});

test('table profiles preserve tag selection and operators without obsolete visibility settings', () => {
    const filters = { tags: { values: ['metal', 'standard'], operator: 'and' } };
    const profile = tableProfileState({ tableTagFilters: [], tableFilters: filters });
    assert.equal(Object.hasOwn(profile, 'tagFilters'), false);
    assert.deepEqual(profile.filters, filters);
    filters.tags.values.push('third');
    assert.deepEqual(profile.filters.tags.values, ['metal', 'standard']);
});

test('legacy tag visibility is discarded while other table preferences still restore', async t => {
    const { store, calls, wire } = tableEnvironment(t);
    browserProfile(1, 'table:values', store).save({ tagFilters: [], search: 'metal' });
    const controller = localTableProfile({ user: 1, scope: 'values' }); controller.$wire = wire;
    await controller.init();
    assert.deepEqual(calls, [{ name: 'restoreTableBrowserProfile', changes: { search: 'metal' } }]);
    assert.equal(Object.hasOwn(browserProfile(1, 'table:values', store).read(), 'tagFilters'), false);
    assert.equal(browserProfile(1, 'table:values', store).read().search, 'metal');
});
