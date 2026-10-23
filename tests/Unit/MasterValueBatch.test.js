import { test } from 'node:test';
import assert from 'node:assert/strict';
import { masterValueBatch, masterBatchColumns, nameEntryDirection } from '../../resources/js/master-value-batch.js';

test('column preferences allow only explicit booleans and never draft data', () => {
    assert.deepEqual(masterBatchColumns({ tags: false, specification: true, label: 'Secret', values: [1] }), { tags: false, specification: true });
    assert.deepEqual(masterBatchColumns({ tags: 'false', specification: null }), { tags: false, specification: false });
});

test('name entry handles only unmodified Enter outside composition or popups', () => {
    const event = { key: 'Enter', target: { closest: () => null, getAttribute: () => null } };
    assert.equal(nameEntryDirection(event), 1);
    assert.equal(nameEntryDirection({ ...event, shiftKey: true }), -1);
    for (const patch of [{ key: 'Tab' }, { isComposing: true }, { keyCode: 229 }, { ctrlKey: true }, { altKey: true }, { metaKey: true }, { repeat: true }]) assert.equal(nameEntryDirection({ ...event, ...patch }), 0);
    assert.equal(nameEntryDirection({ ...event, target: { closest: () => ({}), getAttribute: () => null } }), 0);
});

test('append focus is scoped by path and Livewire component identity', () => {
    const state = masterValueBatch({ path: 'mountedActions.1.data.masters', user: 8, context: 'shared' });
    state.$wire = { $id: 'owner' };
    const fields = [];
    state.focus = field => fields.push(field);
    state.$nextTick = callback => callback();
    state.appended({ path: 'foreign', field: 'wrong', livewireId: 'owner' });
    state.appended({ path: 'mountedActions.1.data.masters', field: 'wrong', livewireId: 'other' });
    state.appended({ path: 'mountedActions.1.data.masters', field: 'target', livewireId: 'owner' });
    assert.deepEqual(fields, ['target']);
});

test('hidden invalid optional columns reveal and focus the affected native input', () => {
    const state = masterValueBatch({});
    state.showTags = false;
    let focused = false;
    state.$nextTick = callback => callback();
    state.$el = { querySelectorAll: () => [{ dataset: { batchField: 'tags' }, querySelector: selector => selector === '[data-validation-error]' ? {} : { focus: () => { focused = true; } } }] };
    state.revealErrors();
    assert.equal(state.showTags, true);
    assert.equal(focused, true);
});

test('morph restoration reapplies explicit column visibility without changing choices', () => {
    const oldWindow = globalThis.window;
    const oldDocument = globalThis.document;
    let intercept;
    globalThis.window = { Livewire: { interceptAction: () => () => {}, interceptMessage(callback) { intercept = callback; return () => {}; } }, addEventListener() {}, removeEventListener() {} };
    globalThis.document = { addEventListener() {}, removeEventListener() {} };
    const state = masterValueBatch({ user: 'tester', context: 'test', path: 'batch' });
    state.$wire = { $id: 'owner', $get: () => ({}) };
    state.$el = { dataset: {}, querySelectorAll: () => [] };
    state.$watch = () => {};
    state.$nextTick = callback => callback();
    try {
        state.init();
        state.showTags = false;
        state.showSpecification = false;
        state.$el.dataset = {};
        intercept({ message: { component: { id: 'owner' } }, onSuccess(callback) { callback({ onMorphed(fn) { fn(); } }); } });
        assert.equal(state.$el.dataset.showTags, 'false');
        assert.equal(state.$el.dataset.showSpecification, 'false');
        assert.equal(state.showTags, false);
        state.destroy();
    } finally {
        globalThis.window = oldWindow;
        globalThis.document = oldDocument;
    }
});

test('append focus verifies the stable row reference before using its absolute path', () => {
    const state = masterValueBatch({});
    let count = 0;
    const input = { getAttributeNames: () => ['wire:model.live.blur'], getAttribute: () => 'draft.rows.uuid.label', closest: () => ({ querySelector: () => ({ value: 'new:current' }) }), focus: () => count++ };
    state.$el = { querySelectorAll: () => [input] };
    state.$wire = { $get: path => path === 'draft.rows.uuid.ref' ? 'new:current' : undefined };
    state.focus('draft.rows.uuid.label', 'new:removed');
    assert.equal(count, 0);
    state.focus('draft.rows.uuid.label', 'new:current');
    assert.equal(count, 1);
});

function keyboardFixture({ count = 2, dirty = true } = {}) {
    const oldWindow = globalThis.window;
    const oldDocument = globalThis.document;
    const calls = [];
    let interceptAction, interceptMessage, lastAction, hooks = {}, nativeDisabled = false;
    globalThis.window = { Livewire: {
        interceptAction(callback) { interceptAction = callback; return () => calls.push('unsubscribed action'); },
        interceptMessage(callback) { interceptMessage = callback; return () => calls.push('unsubscribed message'); },
    }, addEventListener() {}, removeEventListener() {} };
    globalThis.document = { addEventListener() {}, removeEventListener() {} };
    const inputs = Array.from({ length: count }, (_, i) => ({
        value: 'Repeated name', path: `draft.rows.uuid${i}.label`, rowRef: `new:row${i}`,
        getAttributeNames() { return ['wire:model.live.blur']; },
        getAttribute() { return this.path; },
        closest(selector) { return selector === '[data-batch-field="label"]' ? {} : selector === 'tr' ? { querySelector: () => ({ value: this.rowRef }) } : null; },
        focus() { calls.push(`focus:${this.path}`); },
        blur() {
            calls.push(`blur:${this.path}`);
            if (!dirty) return;
            nativeDisabled = true;
            queueMicrotask(() => {
                lastAction = { name: '$commit', component: { id: 'owner' }, origin: { el: this, directive: { expression: this.path } } };
                hooks = {};
                interceptAction?.({ action: lastAction, onSuccess(callback) { hooks.success = callback; }, onFinish(callback) { hooks.finish = callback; } });
            });
        },
    }));
    const state = masterValueBatch({ user: 'tester', context: 'keyboard', path: 'draft.rows' });
    const nativeRefs = new Map(inputs.map(input => [input.path.replace(/\.label$/, '.ref'), input.rowRef]));
    state.$wire = { $id: 'owner', $get: path => path.endsWith('.ref') ? nativeRefs.get(path) : 'Repeated name' };
    state.$el = { dataset: {}, querySelectorAll: selector => selector === '[data-batch-field]' ? [] : inputs, querySelector: () => ({ click: () => { if (!nativeDisabled) calls.push('native add'); } }) };
    state.$watch = () => {};
    state.$nextTick = callback => callback();
    state.init();
    return {
        state, calls, inputs, nativeRefs,
        enter(index = 0, patch = {}) { state.keydown({ key: 'Enter', target: inputs[index], preventDefault: () => calls.push('prevented'), ...patch }); },
        morph(id = 'owner', owningAction = true) {
            interceptMessage({ message: { component: { id }, actions: new Set(owningAction ? [lastAction] : []) }, onSuccess(callback) { callback({ onMorphed(fn) { fn(); } }); } });
        },
        captureAction() { return { action: lastAction, hooks }; },
        async settle(outcome = 'success', { emptyHiddenRefs = false, squashed = false, captured = null } = {}) {
            const nativeAction = captured?.action ?? lastAction;
            const callbacks = captured?.hooks ?? hooks;
            const primary = squashed ? { name: '$commit', origin: null, squashedActions: new Set([nativeAction]) } : nativeAction;
            const message = { component: { id: 'owner' }, actions: new Set([primary]), isSkipped: () => outcome === 'skipped', superseded: outcome === 'superseded' };
            nativeAction.message = squashed ? null : message;
            const messageHooks = {};
            interceptMessage({ message, onSuccess(callback) { messageHooks.success = callback; } });
            if (outcome === 'success') {
                messageHooks.success?.({ onMorphed(callback) { messageHooks.morphed = callback; } });
                inputs.splice(0, inputs.length, ...inputs.map(input => ({ ...input, rowRef: emptyHiddenRefs ? '' : input.rowRef })));
                messageHooks.morphed?.();
            }
            if (['success', 'skipped', 'superseded'].includes(outcome)) {
                const before = calls.filter(call => call === 'native add').length;
                callbacks.success?.();
                assert.equal(calls.filter(call => call === 'native add').length, before);
            }
            callbacks.finish?.();
            nativeDisabled = false;
            await Promise.resolve();
        },
        async otherAction(owner = 'foreign', path = inputs[0]?.path) {
            const otherHooks = {};
            interceptAction({ action: { name: '$commit', component: { id: owner }, origin: { el: inputs[0], directive: { expression: path } } }, onSuccess(callback) { otherHooks.success = callback; }, onFinish(callback) { otherHooks.finish = callback; } });
            otherHooks.success?.();
            otherHooks.finish?.();
            await Promise.resolve();
        },
        cleanup() { state.destroy(); globalThis.window = oldWindow; globalThis.document = oldDocument; },
    };
}

test('equal ephemeral input waits for owning native action success then finish before next-row focus', async () => {
    const f = keyboardFixture();
    try {
        f.enter();
        await Promise.resolve();
        assert.deepEqual(f.calls, ['prevented', 'blur:draft.rows.uuid0.label']);
        f.morph('foreign');
        f.morph('owner', false);
        await f.otherAction();
        await f.otherAction('owner', 'unrelated.label');
        assert.equal(f.calls.some(call => call.startsWith('focus:')), false);
        await f.settle();
        assert.deepEqual(f.calls, ['prevented', 'blur:draft.rows.uuid0.label', 'focus:draft.rows.uuid1.label']);
        await f.settle();
        assert.equal(f.calls.filter(call => call.startsWith('focus:')).length, 1);
    } finally { f.cleanup(); }
});

test('last-row Enter invokes native Add only after the delayed owning blur response', async () => {
    const f = keyboardFixture({ count: 1 });
    try {
        f.enter();
        await Promise.resolve();
        assert.equal(f.calls.includes('native add'), false);
        await f.settle();
        assert.equal(f.calls.filter(call => call === 'native add').length, 1);
    } finally { f.cleanup(); }
});

test('unchanged blur advances without making a second request or waiting indefinitely', async () => {
    const f = keyboardFixture({ dirty: false });
    try {
        f.enter();
        await Promise.resolve();
        assert.deepEqual(f.calls, ['prevented', 'blur:draft.rows.uuid0.label', 'focus:draft.rows.uuid1.label']);
    } finally { f.cleanup(); }
});

test('Shift Enter waits for its owning blur then focuses the previous name', async () => {
    const f = keyboardFixture();
    try {
        f.enter(1, { shiftKey: true });
        await Promise.resolve();
        assert.equal(f.calls.some(call => call.startsWith('focus:')), false);
        await f.settle();
        assert.equal(f.calls.at(-1), 'focus:draft.rows.uuid0.label');
    } finally { f.cleanup(); }
});

test('removing the source row while blur is pending cancels native append intent', async () => {
    const f = keyboardFixture({ count: 1 });
    try {
        f.enter();
        await Promise.resolve();
        f.inputs.splice(0);
        await f.settle();
        assert.equal(f.calls.includes('native add'), false);
    } finally { f.cleanup(); }
});

test('IME Enter does not blur, prevent the event or start native Add', async () => {
    const f = keyboardFixture({ count: 1 });
    try {
        f.enter(0, { isComposing: true });
        await Promise.resolve();
        assert.deepEqual(f.calls, []);
    } finally { f.cleanup(); }
});

test('consecutive repeated names each wait for their own delayed blur boundary', async () => {
    const f = keyboardFixture();
    try {
        f.enter(0);
        await Promise.resolve();
        assert.equal(f.calls.filter(call => call.startsWith('focus:')).length, 0);
        await f.settle();
        f.enter(1);
        await Promise.resolve();
        assert.equal(f.calls.includes('native add'), false);
        f.morph('foreign');
        assert.equal(f.calls.includes('native add'), false);
        await f.settle();
        assert.equal(f.calls.filter(call => call === 'native add').length, 1);
        assert.equal(f.calls.filter(call => call.startsWith('blur:')).length, 2);
    } finally { f.cleanup(); }
});


for (const outcome of ['cancelled', 'failure', 'error', 'skipped', 'superseded']) {
    test(`${outcome} native blur completion clears pending append and cannot resume later`, async () => {
        const f = keyboardFixture({ count: 1 });
        try {
            f.enter();
            await Promise.resolve();
            await f.settle(outcome);
            await f.settle('success');
            assert.equal(f.calls.includes('native add'), false);
        } finally { f.cleanup(); }
    });
}



test('normal blur morph may empty hidden DOM ref values while native state still drives append identity', async () => {
    const f = keyboardFixture({ count: 1 });
    try {
        f.enter();
        await Promise.resolve();
        await f.settle('success', { emptyHiddenRefs: true });
        assert.equal(f.inputs[0].rowRef, '');
        assert.equal(f.nativeRefs.get('draft.rows.uuid0.ref'), 'new:row0');
        assert.equal(f.calls.filter(call => call === 'native add').length, 1);
    } finally { f.cleanup(); }
});

test('a missing native draft ref does not become an unprotected focus or append target', async () => {
    const f = keyboardFixture({ count: 1 });
    try {
        f.nativeRefs.delete('draft.rows.uuid0.ref');
        f.enter();
        await Promise.resolve();
        if (f.calls.some(call => call.startsWith('blur:'))) await f.settle();
        assert.equal(f.calls.includes('native add'), false);
    } finally { f.cleanup(); }
});


for (const outcome of ['success', 'superseded', 'skipped', 'cancelled']) {
    test(`squashed native action with null message handles ${outcome} through its primary message outcome`, async () => {
        const f = keyboardFixture({ count: 1 });
        try {
            f.enter();
            await Promise.resolve();
            await f.settle(outcome, { squashed: true, emptyHiddenRefs: true });
            assert.equal(f.calls.filter(call => call === 'native add').length, outcome === 'success' ? 1 : 0);
        } finally { f.cleanup(); }
    });
}


for (const outcome of ['success', 'superseded']) {
    test(`an old unrelated owning-component ${outcome} cannot release or cancel a newer field intent`, async () => {
        const f = keyboardFixture();
        try {
            f.enter(0);
            await Promise.resolve();
            const old = f.captureAction();
            f.enter(1);
            await Promise.resolve();
            await f.settle(outcome, { captured: old });
            assert.equal(f.calls.includes('native add'), false);
            await f.settle('success', { emptyHiddenRefs: true });
            assert.equal(f.calls.filter(call => call === 'native add').length, 1);
        } finally { f.cleanup(); }
    });
}


test('new batch defaults and Reset columns hide optional fields while explicit preferences survive', () => {
    assert.deepEqual(masterBatchColumns(), { tags: false, specification: false });
    assert.deepEqual(masterBatchColumns({ tags: true, specification: false }), { tags: true, specification: false });
    const state = masterValueBatch({});
    state.showTags = true;
    state.showSpecification = true;
    state.resetColumns();
    assert.equal(state.showTags, false);
    assert.equal(state.showSpecification, false);
});

test('remembered visible columns override the hidden default and Reset retains native draft data', () => {
    const oldWindow = globalThis.window;
    const oldDocument = globalThis.document;
    const oldStorage = globalThis.localStorage;
    const saved = new Map([['aquestia:profile:columns-test:admin:catalog-create:shared:master-columns:v1', JSON.stringify({ tags: true, specification: true })]]);
    const draft = { uuid: { ref: 'new:kept', label: 'Blue', semantic_id: 'manual_blue', tags: ['kept'], description: 'Exact specification' } };
    const before = structuredClone(draft);
    const watchers = [];
    globalThis.localStorage = { getItem: key => saved.get(key) ?? null, setItem: (key, value) => saved.set(key, value) };
    globalThis.window = { Livewire: { interceptAction: () => () => {}, interceptMessage: () => () => {} }, addEventListener() {}, removeEventListener() {} };
    globalThis.document = { addEventListener() {}, removeEventListener() {} };
    const state = masterValueBatch({ user: 'columns-test', context: 'shared', path: 'draft' });
    state.$wire = { $id: 'owner', $get: () => draft };
    state.$el = { dataset: {}, querySelectorAll: () => [], closest: () => null };
    state.$watch = (_, callback) => watchers.push(callback);
    state.$nextTick = callback => callback();
    try {
        state.init();
        assert.equal(state.showTags, true);
        assert.equal(state.showSpecification, true);
        state.resetColumns();
        for (const watcher of watchers) watcher();
        assert.deepEqual(JSON.parse([...saved.values()][0]), { tags: false, specification: false });
        assert.deepEqual(draft, before);
    } finally {
        state.destroy();
        globalThis.window = oldWindow;
        globalThis.document = oldDocument;
        globalThis.localStorage = oldStorage;
    }
});
