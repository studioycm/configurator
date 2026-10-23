import test from 'node:test';
import assert from 'node:assert/strict';
import { nextIdentifierIntent, suggestedIdentifier } from '../../resources/js/suggested-identifier.js';

function field() {
    const config = { field: 'row.semantic_id', label: 'row.label', used: 'row.used', intent: 'row.intent', ref: 'row.ref' };
    const state = { 'row.label': 'Light Blue', 'row.semantic_id': '', 'row.used': false, 'row.ref': 'new:owner' };
    const instance = suggestedIdentifier(config);
    instance.$wire = { $id: 'owner', $get: path => state[path], $set: (path, value) => { state[path] = value; } };
    instance.$el = { isConnected: true };
    instance.init();
    const candidate = (overrides = {}) => ({ field: config.field, livewireId: 'owner', rowRef: 'new:owner', label: 'Light Blue', intent: state[config.intent], candidate: 'light_blue_2', explicit: false, ...overrides });
    return { instance, state, candidate };
}

test('a current automatic candidate updates native ID and intent once', () => {
    const { instance, state, candidate } = field();
    const response = candidate();
    instance.receiveIdentifier(response);
    assert.equal(state['row.semantic_id'], 'light_blue_2');
    assert.equal(state['row.used'], true);
    assert.equal(instance.suggested, true);
    state['row.semantic_id'] = 'later';
    instance.receiveIdentifier(response);
    assert.equal(state['row.semantic_id'], 'later');
    instance.destroy();
});

for (const manual of ['manual_id', '']) {
    test(`manual input ${JSON.stringify(manual)} defeats an in-flight automatic candidate`, () => {
        const { instance, state, candidate } = field();
        const response = candidate();
        state['row.semantic_id'] = manual;
        instance.identifierInput();
        instance.receiveIdentifier(response);
        assert.equal(state['row.semantic_id'], manual);
        assert.equal(state['row.used'], true);
        assert.equal(instance.suggested, false);
        instance.destroy();
    });
}

for (const overrides of [{ field: 'other.semantic_id' }, { livewireId: 'other' }, { rowRef: 'new:other' }, { label: 'Old name' }, { intent: 'old-intent' }]) {
    test(`a foreign or obsolete candidate is ignored: ${JSON.stringify(overrides)}`, () => {
        const { instance, state, candidate } = field();
        instance.receiveIdentifier(candidate(overrides));
        assert.equal(state['row.semantic_id'], '');
        assert.equal(state['row.used'], false);
        assert.equal(instance.suggested, false);
        instance.destroy();
    });
}

test('explicit regenerate replaces manual clear only while its intent is current', () => {
    const { instance, state, candidate } = field();
    instance.identifierInput();
    instance.identifierIntent();
    instance.receiveIdentifier(candidate({ explicit: true }));
    assert.equal(state['row.semantic_id'], 'light_blue_2');
    instance.identifierIntent();
    const pending = candidate({ explicit: true });
    state['row.semantic_id'] = 'newer_manual';
    instance.identifierInput();
    instance.receiveIdentifier(pending);
    assert.equal(state['row.semantic_id'], 'newer_manual');
    instance.destroy();
});

test('destroy and replacement row identity reject old responses', () => {
    const { instance, state, candidate } = field();
    const response = candidate();
    state['row.ref'] = 'new:replacement';
    instance.receiveIdentifier(response);
    assert.equal(state['row.semantic_id'], '');
    state['row.ref'] = 'new:owner';
    instance.destroy();
    instance.receiveIdentifier(response);
    assert.equal(state['row.semantic_id'], '');
    assert.equal(instance.suggested, false);
});

test('native input intent works without secure-context randomUUID and rejects held automatic and regenerate responses', () => {
    const cryptoDescriptor = Object.getOwnPropertyDescriptor(globalThis, 'crypto');
    Object.defineProperty(globalThis, 'crypto', { configurable: true, value: {} });
    let instance;
    try {
        const mounted = field();
        instance = mounted.instance;
        const { state, candidate } = mounted;
        const initialIntent = state['row.intent'];
        assert.equal(typeof initialIntent, 'string');
        assert.notEqual(initialIntent, '');
        const heldAutomatic = candidate();
        state['row.semantic_id'] = 'temporary';
        instance.identifierInput();
        state['row.semantic_id'] = '';
        instance.identifierInput();
        assert.notEqual(state['row.intent'], initialIntent);
        assert.equal(state['row.used'], true);
        instance.receiveIdentifier(heldAutomatic);
        assert.equal(state['row.semantic_id'], '');
        assert.equal(instance.suggested, false);
        instance.identifierIntent();
        const heldRegenerate = candidate({ explicit: true });
        state['row.semantic_id'] = 'newer_manual';
        instance.identifierInput();
        instance.receiveIdentifier(heldRegenerate);
        assert.equal(state['row.semantic_id'], 'newer_manual');
        instance.identifierIntent();
        instance.receiveIdentifier(candidate({ explicit: true }));
        assert.equal(state['row.semantic_id'], 'light_blue_2');
        assert.equal(instance.suggested, true);
    } finally {
        instance?.destroy();
        if (cryptoDescriptor) Object.defineProperty(globalThis, 'crypto', cryptoDescriptor);
        else delete globalThis.crypto;
    }
});


test('name input and remounted fields share fresh page-local intent without reusing a destroyed response', () => {
    const original = field();
    const held = original.candidate();
    original.instance.destroy();
    const reopened = field();
    try {
        assert.notEqual(reopened.state['row.intent'], held.intent);
        reopened.instance.receiveIdentifier(held);
        assert.equal(reopened.state['row.semantic_id'], '');
        const beforeNameInput = reopened.candidate();
        reopened.state['row.intent'] = nextIdentifierIntent();
        reopened.instance.receiveIdentifier(beforeNameInput);
        assert.equal(reopened.state['row.semantic_id'], '');
        reopened.instance.receiveIdentifier(reopened.candidate());
        assert.equal(reopened.state['row.semantic_id'], 'light_blue_2');
    } finally {
        reopened.instance.destroy();
    }
});
