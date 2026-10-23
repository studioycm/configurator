import { test } from 'node:test';
import assert from 'node:assert/strict';
import { attributeDraftIsDirty, catalogDraftGuard, masterValueBatch } from '../../resources/js/master-value-batch.js';

const emptyAttribute = { ref: 'new:generated', _suggested_key: false, label: '', key: '', values: [], _master_composer: { generated: { ref: 'new:generated', _suggested_semantic_id: false, label: '', semantic_id: '', tags: [], description: null } } };

for (const [name, state] of [
    ['Label only without opening Values', { attributes: { a: { ...emptyAttribute, label: 'Material' } } }],
    ['manually edited Key only', { attributes: { a: { ...emptyAttribute, key: 'legacy-key' } } }],
    ['staged pool after composer clears', { master_values: { m: { ref: 'new:pool', label: 'Blue', semantic_id: 'blue' } }, attributes: { a: { ...emptyAttribute, _master_composer: [] } } }],
    ['association with cleared Code', { attributes: { a: { ...emptyAttribute, values: { v: { ref: 'new:association', value_id: 9, code: '' } }, _master_composer: [] } } }],
    ['inactive owner composer', { _active_owner: 'new:other', attributes: { a: { ...emptyAttribute, _master_composer: { m: { label: 'Unfinished', semantic_id: '', _suggested_semantic_id: true } } }, b: emptyAttribute } }],
    ['persisted picker selection before staging', { attributes: { a: { ...emptyAttribute, _selected_value_ids: ['8'] } } }],
    ['pending Master selection before staging', { attributes: { a: { ...emptyAttribute, _selected_pending: ['new:pool'] } } }],
]) {
    test(`outer dirty predicate protects ${name}`, () => assert.equal(attributeDraftIsDirty(state), true));
}

test('generated refs helper flags active owner and blank rows do not make an empty session dirty', () => {
    assert.equal(attributeDraftIsDirty({ _active_owner: 'new:generated', master_values: { blank: { ref: 'new:blank', label: ' ', semantic_id: '', tags: [], description: null } }, attributes: { a: emptyAttribute } }), false);
    assert.equal(attributeDraftIsDirty(undefined), false);
});

function guardFixture(initial = {}) {
    const oldWindow = globalThis.window;
    const oldDocument = globalThis.document;
    const handlers = { window: new Map(), document: new Map() };
    const prompts = [];
    let current = initial;
    const add = (target, event, handler) => { const values = handlers[target].get(event) ?? new Set(); values.add(handler); handlers[target].set(event, values); };
    const remove = (target, event, handler) => handlers[target].get(event)?.delete(handler);
    globalThis.window = { confirm: text => { prompts.push(text); return false; }, addEventListener: (event, handler) => add('window', event, handler), removeEventListener: (event, handler) => remove('window', event, handler), Livewire: { interceptAction: () => () => {}, interceptMessage: () => () => {} } };
    globalThis.document = { addEventListener: (event, handler) => add('document', event, handler), removeEventListener: (event, handler) => remove('document', event, handler) };
    const root = catalogDraftGuard({ path: 'mountedActions.2.data', discardMessage: 'Discard this batch?' }, attributeDraftIsDirty);
    const paths = [];
    root.$wire = { $get: path => { paths.push(path); return current; } };
    root.init();
    return {
        root, prompts, paths, handlers,
        set: state => { current = state; },
        dispatch(target, event) { const value = { defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } }; for (const handler of handlers[target].get(event) ?? []) handler(value); return value; },
        cleanup() { root.destroy(); globalThis.window = oldWindow; globalThis.document = oldDocument; },
    };
}

test('outer unload and declined navigation read current native state without copying or clearing it', () => {
    const draft = { attributes: { a: { ...emptyAttribute, label: 'Keep exactly' } } };
    const f = guardFixture(draft);
    try {
        assert.equal(f.dispatch('window', 'beforeunload').defaultPrevented, true);
        assert.equal(f.dispatch('document', 'livewire:navigate').defaultPrevented, true);
        assert.deepEqual(f.prompts, ['Discard this batch?']);
        assert.deepEqual(f.paths, ['mountedActions.2.data', 'mountedActions.2.data']);
        assert.equal(draft.attributes.a.label, 'Keep exactly');
        f.set({ attributes: { a: emptyAttribute } });
        assert.equal(f.dispatch('window', 'beforeunload').defaultPrevented, false);
        assert.equal(f.dispatch('document', 'livewire:navigate').defaultPrevented, false);
    } finally { f.cleanup(); }
});

test('native success or confirmed Discard removes the state and leaves no stale guard after destroy', () => {
    const f = guardFixture({ attributes: { a: { ...emptyAttribute, label: 'Dirty' } } });
    try {
        f.set(undefined);
        assert.equal(f.dispatch('window', 'beforeunload').defaultPrevented, false);
        assert.equal(f.dispatch('document', 'livewire:navigate').defaultPrevented, false);
        f.root.destroy();
        assert.equal(f.handlers.window.get('beforeunload').size, 0);
        assert.equal(f.handlers.document.get('livewire:navigate').size, 0);
    } finally { f.cleanup(); }
});

test('active nested Master composer delegates its guard to the outer session and registers no duplicate prompt', () => {
    const f = guardFixture({ attributes: { a: { ...emptyAttribute, _master_composer: { m: { label: 'Pending' } } } } });
    const composer = masterValueBatch({ user: 'guard-test', context: 'shared', path: 'mountedActions.2.data.attributes.a._master_composer', discardMessage: 'Discard composer?' });
    composer.$wire = { $id: 'owner', $get: () => ({ m: { label: 'Pending' } }) };
    composer.$el = { dataset: {}, closest: selector => selector === '[data-catalog-draft-guard]' ? {} : null, querySelectorAll: () => [] };
    composer.$watch = () => {};
    composer.$nextTick = callback => callback();
    try {
        composer.init();
        assert.equal(f.handlers.window.get('beforeunload').size, 1);
        assert.equal(f.handlers.document.get('livewire:navigate').size, 1);
        assert.equal(f.dispatch('document', 'livewire:navigate').defaultPrevented, true);
        assert.deepEqual(f.prompts, ['Discard this batch?']);
        composer.destroy();
        assert.equal(f.handlers.document.get('livewire:navigate').size, 1);
    } finally { composer.destroy(); f.cleanup(); }
});

test('navigation that was already declined is not prompted again and accepted navigation is allowed', () => {
    const f = guardFixture({ attributes: { a: { ...emptyAttribute, key: 'manual' } } });
    try {
        const event = { defaultPrevented: true, preventDefault() { this.defaultPrevented = true; } };
        for (const handler of f.handlers.document.get('livewire:navigate')) handler(event);
        assert.deepEqual(f.prompts, []);
        globalThis.window.confirm = () => true;
        assert.equal(f.dispatch('document', 'livewire:navigate').defaultPrevented, false);
    } finally { f.cleanup(); }
});

test('standalone Master batch keeps its existing navigation and unload guard without an outer session', () => {
    const f = guardFixture({});
    f.root.destroy();
    const composer = masterValueBatch({ user: 'guard-test', context: 'master', path: 'rows', discardMessage: 'Discard Masters?' });
    composer.$wire = { $id: 'owner', $get: () => ({ m: { label: 'Standalone unsaved' } }) };
    composer.$el = { dataset: {}, closest: () => null, querySelectorAll: () => [] };
    composer.$watch = () => {};
    composer.$nextTick = callback => callback();
    try {
        composer.init();
        assert.equal(f.dispatch('window', 'beforeunload').defaultPrevented, true);
        assert.equal(f.dispatch('document', 'livewire:navigate').defaultPrevented, true);
        assert.deepEqual(f.prompts, ['Discard Masters?']);
        composer.destroy();
        assert.equal(f.handlers.window.get('beforeunload').size, 0);
        assert.equal(f.handlers.document.get('livewire:navigate').size, 0);
    } finally { composer.destroy(); f.cleanup(); }
});
