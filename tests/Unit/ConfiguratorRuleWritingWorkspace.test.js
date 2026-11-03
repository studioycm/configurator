import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ruleWritingWorkspace } from '../../resources/js/configurator-rule-writing.js';
const draft = (id = 'a', source = '// old') => ({ id, kind: 'Advanced', source, label: null, description: null, revision: 1, origin_rule_id: null, format_version: 1 });
const deferred = () => { let resolve; const promise = new Promise(done => resolve = done); return { promise, resolve }; };
const opened = d => ({ draft: d });
const ack = (d, sequence) => ({ draft: d, acknowledgement: { sequence, revision: d.revision, source: d.source, label: d.label, description: d.description } });
function workspace(request) { return ruleWritingWorkspace({}, { request, setTimer: () => null, clearTimer: () => {}, confirm: () => true }); }
function append(w, text) { const source = w.current.source; w.changeSource(source + text, { start: source.length, end: source.length, inputType: 'insertText' }); }

test('slow draft acknowledgement preserves later text and a different active draft', async () => {
    const pending = deferred();
    const w = workspace(() => pending.promise);
    w.acceptOpened(opened(draft())); append(w, '1');
    const saving = w.flush('a'); await Promise.resolve(); await Promise.resolve();
    append(w, '2'); w.acceptOpened(opened(draft('b')));
    pending.resolve({ ok: true, data: ack({ ...draft(), source: '// old1', revision: 2 }, 1) });
    await saving;
    assert.equal(w.buffers.a.source, '// old12');
    assert.equal(w.buffers.a.revision, 2);
    assert.equal(w.buffers.a.ack.sequence, 1);
    assert.equal(w.currentId, 'b');
    assert.equal(w.dirty(w.buffers.a), true);
    w.destroy();
});

test('selected save flushes click-time writing, keeps later writing and stores receipt before failed inspect', async () => {
    const saving = deferred(), calls = [];
    const w = workspace(async (op, input) => {
        calls.push([op, input]);
        if (op === 'update-draft') return { ok: true, data: ack({ ...draft(), ...input.authoring, revision: 2 }, input.sequence) };
        if (op === 'save-selected') return saving.promise;
        throw new Error('inspection unavailable');
    });
    w.acceptOpened(opened(draft())); append(w, '1'); w.selected = ['a'];
    const result = w.saveSelected();
    while (!calls.some(([op]) => op === 'save-selected')) await new Promise(done => setImmediate(done));
    append(w, '2');
    saving.resolve({ ok: true, data: { receipt_json: '{"committed":true}', items: [{ ...ack({ ...draft(), source: '// old1', revision: 3, origin_rule_id: '99' }, 1), rule_id: '99' }] } });
    await result;
    assert.equal(w.receipt, '{"committed":true}');
    assert.equal(w.buffers.a.source, '// old12');
    assert.equal(w.buffers.a.origin_rule_id, '99');
    assert.equal(w.buffers.a.ack.source, '// old1');
    assert.equal(w.buffers.a.unknown, false);
    assert.deepEqual(w.selected, ['a']);
    assert.equal(calls.filter(([op]) => op === 'save-selected').length, 1);
    w.destroy();
});

test('refresh flushes exact Mine before capture and any local edit invalidates the chooser', async () => {
    const calls = [];
    const w = workspace(async (op, input) => {
        calls.push([op, input]);
        if (op === 'update-draft') return { ok: true, data: ack({ ...draft(), ...input.authoring, revision: 2, origin_rule_id: '10' }, input.sequence) };
        return { ok: true, data: { draft: { ...draft(), revision: 2, origin_rule_id: '10' }, snapshot_json: 'opaque', latest_source: '// latest', latest_label: 'Latest' } };
    });
    w.acceptOpened(opened({ ...draft(), origin_rule_id: '10' })); append(w, ' Mine');
    await w.captureLatest();
    assert.deepEqual(calls.map(([op]) => op), ['update-draft', 'capture-latest']);
    assert.equal(calls[1][1].expected_revision, 2);
    assert.equal(w.current.capture.snapshot_json, 'opaque');
    append(w, ' later');
    assert.equal(w.current.capture, null);
    await w.refreshSections();
    assert.equal(calls.some(([op]) => op === 'refresh-sections'), false);
    w.destroy();
});

test('shared draft collision retains writing and never calls the executable writer', async () => {
    const calls = [];
    const w = workspace(async (op) => { calls.push(op); return { ok: false, type: 'validation', errors: { draft_revision: ['Changed'] } }; });
    w.acceptOpened(opened(draft())); append(w, ' local'); w.selected = ['a'];
    await w.saveSelected();
    assert.deepEqual(calls, ['update-draft']);
    assert.equal(w.current.source, '// old local');
    assert.equal(w.current.conflict, true);
    w.destroy();
});

test('unknown executable outcome is not replayed and later view changes call no backend', async () => {
    let saves = 0;
    const w = workspace(async op => { if (op === 'save-selected') { saves++; throw new Error('lost'); } return { ok: true, data: opened(draft()) }; });
    w.acceptOpened(opened(draft())); w.selected = ['a'];
    await w.saveSelected(); await w.saveSelected();
    assert.equal(saves, 1);
    assert.equal(w.current.unknown, true);
    w.dataView = 'json'; w.dataView = 'yaml'; w.dataView = 'debug';
    assert.equal(saves, 1);
    w.destroy();
});

test('destroy ignores a late authoring acknowledgement', async () => {
    const pending = deferred(), w = workspace(() => pending.promise);
    w.acceptOpened(opened(draft())); append(w, ' new');
    const saving = w.flush('a'); await Promise.resolve(); await Promise.resolve(); w.destroy();
    pending.resolve({ ok: true, data: ack({ ...draft(), source: '// old new', revision: 2 }, 1) }); await saving;
    assert.equal(w.buffers.a.revision, 1);
});

test('opening unsupported saved Rule offers the regular owned edit route without English parsing', async () => {
    const w = ruleWritingWorkspace({ backUrl: 'http://writing.test/admin/configurators/1/edit?tab=rules%3A%3Adata%3A%3Atab' }, { uuid: () => 'new-id', request: async () => ({ ok: false, type: 'validation', errors: { rule: ['Unsupported graph'] } }) });
    await w.openRule('17');
    assert.equal(w.failedRuleId, '17');
    assert.equal(new URL(w.ruleEditUrl(w.failedRuleId)).searchParams.get('rule'), '17');
    w.destroy();
});

test('switching drafts or changing Mine while capture is in flight cannot reactivate a chooser', async () => {
    const pending = deferred();
    const w = workspace(() => pending.promise);
    w.acceptOpened(opened({ ...draft(), origin_rule_id: '10' }));
    const capturing = w.captureLatest(); await Promise.resolve(); await Promise.resolve();
    w.acceptOpened(opened(draft('b')));
    pending.resolve({ ok: true, data: { draft: { ...draft(), origin_rule_id: '10' }, snapshot_json: 'opaque', latest_source: '// latest' } });
    await capturing;
    assert.equal(w.buffers.a.capture, null);
    w.destroy();
});

test('editing during a refresh flush prevents capturing older DB Mine', async () => {
    const pending = deferred(), calls = [];
    const w = workspace(async (op, input) => { calls.push(op); return pending.promise; });
    w.acceptOpened(opened({ ...draft(), origin_rule_id: '10' })); append(w, '1');
    const refreshing = w.captureLatest(); await Promise.resolve(); await Promise.resolve(); append(w, '2');
    pending.resolve({ ok: true, data: ack({ ...draft(), source: '// old1', origin_rule_id: '10', revision: 2 }, 1) }); await refreshing;
    assert.deepEqual(calls, ['update-draft']);
    assert.equal(w.current.capture, null);
    assert.equal(w.current.source, '// old12'); w.destroy();
});

test('explicit section refresh changes draft only and preserves selected membership', async () => {
    const calls = [];
    const w = workspace(async (op, input) => {
        calls.push(op);
        if (op === 'capture-latest') return { ok: true, data: { draft: { ...draft(), origin_rule_id: '10' }, snapshot_json: 'opaque', latest_source: '// new', latest_label: 'Latest' } };
        return { ok: true, data: ack({ ...draft(), source: '// new', origin_rule_id: '10', revision: 2 }, input.sequence) };
    });
    w.acceptOpened(opened({ ...draft(), origin_rule_id: '10' })); w.selected = ['a']; await w.captureLatest();
    w.current.capture.sections = ['effects']; await w.refreshSections();
    assert.equal(w.current.source, '// new'); assert.equal(w.current.ack.source, '// new');
    assert.deepEqual(w.selected, ['a']); assert.deepEqual(calls, ['capture-latest', 'refresh-sections']); w.destroy();
});

for (const kind of ['Mapping', 'Advanced']) {
    test(`${kind} section refresh repaints the textarea after its originating control is removed`, async () => {
        const source = kind === 'Mapping' ? '/pair @A -> @B\r\n/case #x -> #y\r\n/other disable\r\n// שלום' : '/hide @B\r\n// שלום';
        const refreshed = source.replace(kind === 'Mapping' ? 'disable' : '/hide', kind === 'Mapping' ? 'hide' : '/disable');
        const original = { ...draft('a', source), kind, origin_rule_id: '10' }, ticks = [], calls = [];
        const w = workspace(async (operation, input) => {
            calls.push(operation);
            if (operation === 'capture-latest') return { ok: true, data: { draft: original, snapshot_json: 'opaque', latest_source: refreshed } };
            return { ok: true, data: ack({ ...original, source: refreshed, revision: 2 }, input.sequence) };
        });
        w.acceptOpened(opened(original)); w.selected = ['a']; await w.captureLatest();
        w.current.capture.sections = [kind === 'Mapping' ? 'mapping' : 'effects'];
        const element = { value: source.replaceAll('\r\n', '\n'), isConnected: true, setSelectionRange() {} };
        let triggerConnected = true;
        Object.defineProperty(w, '$refs', { get: () => triggerConnected ? { source: element } : {} });
        w.$nextTick = callback => ticks.push(callback);
        await w.refreshSections();
        assert.equal(w.current.capture, null);
        triggerConnected = false;
        ticks.splice(0).forEach(callback => callback());
        assert.equal(element.value, refreshed.replaceAll('\r\n', '\n'));
        assert.equal(w.current.source, refreshed); assert.equal(w.current.ack.source, refreshed);
        assert.equal(w.dirty(w.current), false); assert.deepEqual(w.selected, ['a']);
        assert.deepEqual(calls, ['capture-latest', 'refresh-sections']); w.destroy();
    });
}

test('first draft opening leaves editor reference lookup to its mounted textarea', () => {
    const w = workspace(async () => ({})), ticks = [];
    let mounted = false, earlyLookups = 0;
    const element = { value: '', isConnected: true, setSelectionRange() {} };
    Object.defineProperty(w, '$refs', { get() { if (!mounted) earlyLookups++; return mounted ? { source: element } : {}; } });
    w.$nextTick = callback => ticks.push(callback);
    w.acceptOpened(opened(draft('a', 'שלום\r\nB')));
    assert.equal(earlyLookups, 0);
    mounted = true; ticks.splice(0).forEach(callback => callback());
    assert.equal(element.value, 'שלום\nB'); assert.equal(w.current.source, 'שלום\r\nB'); w.destroy();
});

test('queued editor synchronization ignores another draft and a disconnected textarea', () => {
    const w = workspace(async () => ({})), ticks = [];
    w.acceptOpened(opened(draft('a', 'A'))); w.acceptOpened(opened(draft('b', 'B')), false, false);
    const element = { value: 'unchanged', isConnected: true, setSelectionRange() {} };
    w.$refs = { source: element }; w.$nextTick = callback => ticks.push(callback);
    let paints = 0; const sync = w.syncEditor; w.syncEditor = function (...args) { paints++; return sync.apply(this, args); };
    w.focusDraft('a'); w.currentId = 'b'; ticks.splice(0).forEach(callback => callback());
    assert.equal(paints, 0); assert.equal(element.value, 'unchanged');
    w.focusDraft('b'); element.isConnected = false; ticks.splice(0).forEach(callback => callback());
    assert.equal(paints, 1); assert.equal(element.value, 'unchanged'); w.destroy();
});

test('both native history event paths apply once and preserve raw CRLF on undo', () => {
    const w = workspace(async () => ({})); w.acceptOpened(opened(draft('a', 'A\r\n\rB')));
    const element = { value: 'A\nB', selectionStart: 1, selectionEnd: 1, setSelectionRange() {} }; w.$refs = { source: element };
    w.changeSource('A\nB', { start: 1, end: 2, inputType: 'deleteContentBackward' });
    let prevented = 0;
    w.beforeInput({ inputType: 'historyUndo', cancelable: true, target: element, preventDefault: () => prevented++ });
    w.input({ inputType: 'historyUndo', target: element });
    assert.equal(prevented, 1); assert.equal(w.current.source, 'A\r\n\rB');
    w.beforeInput({ inputType: 'historyRedo', cancelable: false, target: element });
    w.input({ inputType: 'historyRedo', target: element });
    assert.equal(w.current.source, 'A\rB'); w.destroy();
});

test('composition commits one source patch and invalid UTF16 remains local', async () => {
    let requests = 0; const w = workspace(async () => { requests++; return {}; });
    w.acceptOpened(opened(draft('a', 'A\r\nB')));
    const element = { value: 'A\nשלוםB', selectionStart: 2, selectionEnd: 2 };
    w.compositionStart({ target: element }); w.input({ target: element, isComposing: true });
    assert.equal(w.current.sequence, 0);
    element.selectionStart = element.selectionEnd = 6; w.compositionEnd({ target: element });
    assert.equal(w.current.source, 'A\r\nשלוםB'); w.history('undo'); assert.equal(w.current.source, 'A\r\nB');
    w.changeSource('A\nB\uD800', { start: 3, end: 3, inputType: 'insertText' }); await w.flush();
    assert.equal(requests, 0); assert.equal(w.current.source, 'A\r\nB\uD800'); w.destroy();
});

test('an invalid batch or proven rollback retains every selected draft without claiming success', async () => {
    const w = workspace(async () => ({ ok: false, type: 'failure', rolledBack: true, message: 'Rolled back' }));
    w.acceptOpened(opened(draft())); w.selected = ['a']; await w.saveSelected();
    assert.equal(w.receipt, null); assert.equal(w.current.unknown, false); assert.deepEqual(w.selected, ['a']); w.destroy();
});

test('wrong authoring ack is uncertain and cannot advance shared revision', async () => {
    const w = workspace(async () => ({ ok: true, data: ack({ ...draft(), source: 'different', revision: 8 }, 1) }));
    w.acceptOpened(opened(draft())); append(w, '1'); await w.flush();
    assert.equal(w.current.revision, 1); assert.equal(w.current.source, '// old1'); assert.equal(w.current.unknown, true); w.destroy();
});

test('duplicate items cannot impersonate acknowledgements for an entire selected batch', async () => {
    const item = { ...ack({ ...draft(), origin_rule_id: '9', revision: 2 }, 0), rule_id: '9' };
    const w = workspace(async () => ({ ok: true, data: { receipt_json: '{}', items: [item,item] } }));
    w.acceptOpened(opened(draft())); w.acceptOpened(opened(draft('b'))); w.selected = ['a','b']; await w.saveSelected();
    assert.equal(w.receipt, null); assert.equal(w.buffers.b.unknown, true); w.destroy();
});

test('section refresh works under the installed Alpine reactive proxy', async () => {
    const { readFileSync } = await import('node:fs');
    const vendor = readFileSync(new URL('../../vendor/livewire/livewire/dist/livewire.esm.js', import.meta.url), 'utf8');
    const extract = vendor.slice(vendor.indexOf('    var require_shared_cjs ='), vendor.indexOf('    var module_exports ='));
    const { reactive } = Function('var __commonJS2 = (cb, mod) => function() { return mod || (0, cb[Object.getOwnPropertyNames(cb)[0]])((mod = {exports:{}}).exports, mod), mod.exports; };' + extract + '; return require_reactivity();')();
    const calls = [];
    const w = reactive(workspace(async (op) => { calls.push(op); return { ok: true, data: { draft: { ...draft(), origin_rule_id: '10' }, snapshot_json: 'opaque', latest_source: '// latest' } }; }));
    w.acceptOpened(opened({ ...draft(), origin_rule_id: '10' })); await w.captureLatest();
    assert.deepEqual(calls, ['capture-latest']); assert.equal(w.current.capture.snapshot_json, 'opaque'); w.destroy();
});

test('unopened selected draft retains its listed revision across pagination and search', async () => {
    let submitted;
    const w = workspace(async (op, input) => {
        if (op === 'draft-summaries') return { ok: true, data: { rows: [{ id: input.page === 1 ? 'a' : 'b', revision: input.page === 1 ? 7 : 3, kind: 'Advanced', label: null }], has_more: input.page === 1 } };
        if (op === 'save-selected') { submitted = input.selection; return { ok: true, data: { receipt_json: '{}', items: [{ ...ack({ ...draft(), revision: 8, origin_rule_id: '10' }, 0), rule_id: '10' }] } }; }
        return { ok: true, data: {} };
    });
    await w.refreshList(); w.selected = ['a']; w.page = 2; w.search = 'other'; await w.refreshList(); await w.saveSelected();
    assert.equal(submitted[0].expected_revision, 7); assert.equal(submitted[0].sequence, 0); w.destroy();
});

test('refreshing a checked unopened draft cannot silently select a newer shared revision', async () => {
    let revision = 1, submitted;
    const w = workspace(async (op, input) => {
        if (op === 'draft-summaries') return { ok: true, data: { rows: [{ ...draft(), revision }], has_more: false } };
        if (op === 'save-selected') {
            submitted = input.selection;
            return { ok: false, type: 'validation', errors: { 'drafts.a.revision': ['Shared draft changed'] } };
        }
        throw new Error(`Unexpected operation ${op}`);
    });
    await w.refreshList(); w.selected = ['a'];
    revision = 2; await w.refreshList();

    const result = await w.saveSelected();

    assert.deepEqual(submitted, [{ id: 'a', expected_revision: 1, sequence: 0 }]);
    assert.equal(result, false); assert.equal(w.receipt, null);
    assert.deepEqual(w.selected, ['a']); assert.equal(w.drafts[0].revision, 2);
    w.destroy();
});

test('an unopened batch member keeps its click-time revision while a queued refresh and reselection complete', async () => {
    const refreshing = deferred(); let count = 0, submitted;
    const w = workspace(async (op, input) => {
        if (op === 'draft-summaries') return ++count === 1 ? { ok: true, data: { rows: [draft()], has_more: false } } : refreshing.promise;
        if (op === 'save-selected') { submitted = input.selection; return { ok: false, type: 'validation', errors: { 'drafts.a.revision': ['Shared draft changed'] } }; }
        throw new Error(`Unexpected operation ${op}`);
    });
    await w.refreshList(); w.selected = ['a'];
    const refresh = w.refreshList(), saving = w.saveSelected();
    w.selected = [];
    refreshing.resolve({ ok: true, data: { rows: [{ ...draft(), revision: 2 }], has_more: false } });
    await refresh; w.selected = ['a'];

    await saving;

    assert.deepEqual(submitted, [{ id: 'a', expected_revision: 1, sequence: 0 }]);
    assert.deepEqual(w.selected, ['a']); assert.equal(w.receipt, null);
    w.destroy();
});

test('deselecting and reselecting an unopened draft explicitly chooses its refreshed revision under Alpine reactivity', async () => {
    const { readFileSync } = await import('node:fs');
    const vendor = readFileSync(new URL('../../vendor/livewire/livewire/dist/livewire.esm.js', import.meta.url), 'utf8');
    const extract = vendor.slice(vendor.indexOf('    var require_shared_cjs ='), vendor.indexOf('    var module_exports ='));
    const { reactive } = Function('var __commonJS2 = (cb, mod) => function() { return mod || (0, cb[Object.getOwnPropertyNames(cb)[0]])((mod = {exports:{}}).exports, mod), mod.exports; };' + extract + '; return require_reactivity();')();
    let revision = 1, submitted;
    const w = reactive(workspace(async (op, input) => {
        if (op === 'draft-summaries') return { ok: true, data: { rows: [{ ...draft(), revision }], has_more: false } };
        if (op === 'save-selected') { submitted = input.selection; return { ok: true, data: { receipt_json: '{"saved":true}', items: [{ ...ack({ ...draft(), revision: 3, origin_rule_id: '10' }, 0), rule_id: '10' }] } }; }
        return { ok: true, data: {} };
    }));
    await w.refreshList(); w.selected = w.selected.concat(['a']);
    revision = 2; await w.refreshList();
    assert.equal(w.selectedDrafts.a.revision, 1);

    w.selected = w.selected.filter(id => id !== 'a');
    w.selected = w.selected.concat(['a']);
    const saved = await w.saveSelected();

    assert.deepEqual(submitted, [{ id: 'a', expected_revision: 2, sequence: 0 }]);
    assert.equal(saved, true); assert.equal(w.receipt, '{"saved":true}');
    assert.deepEqual(w.selected, ['a']);
    w.destroy();
});

test('selected opened writing keeps its own acknowledged revision when list metadata changes', async () => {
    let submitted;
    const w = workspace(async (op, input) => {
        if (op === 'draft-summaries') return { ok: true, data: { rows: [{ ...draft(), revision: 9 }], has_more: false } };
        if (op === 'update-draft') return { ok: true, data: ack({ ...draft(), ...input.authoring, revision: 2 }, input.sequence) };
        if (op === 'save-selected') { submitted = input.selection; return { ok: false, type: 'validation', errors: { 'drafts.a.revision': ['Shared draft changed'] } }; }
        throw new Error(`Unexpected operation ${op}`);
    });
    w.acceptOpened(opened(draft())); w.selected = ['a']; append(w, ' Mine');
    await w.flush(); await w.refreshList();

    await w.saveSelected();

    assert.deepEqual(submitted, [{ id: 'a', expected_revision: 2, sequence: 1 }]);
    assert.equal(w.current.source, '// old Mine'); assert.equal(w.current.conflict, true);
    assert.deepEqual(w.selected, ['a']); assert.equal(w.receipt, null);
    w.destroy();
});

test('an unopened draft without an explicit selection revision is rejected before sending a batch', async () => {
    let requests = 0;
    const w = workspace(async () => { requests++; return { ok: true, data: {} }; });

    const result = await w.saveSelected(['a']);

    assert.equal(result, false); assert.equal(requests, 0);
    assert.equal(w.receipt, null); assert.match(w.error, /Select this draft again/);
    w.destroy();
});

test('nested W3 revision error activates the affected draft conflict path', async () => {
    const w = workspace(async () => ({ ok: false, type: 'validation', errors: { 'drafts.a.revision': ['Changed'] } }));
    w.acceptOpened(opened(draft())); w.selected = ['a']; await w.saveSelected();
    assert.equal(w.current.conflict, true); assert.equal(w.current.unknown, false); w.destroy();
});

for (const rejection of [{ ok: false, type: 'validation', errors: { 'drafts.a.source': ['Invalid submitted rule'] } }, { ok: false, type: 'failure', rolledBack: true, message: 'Rolled back' }]) {
    test(`later writing resumes draft autosave after ${rejection.type}`, async () => {
        const active = new Map(), pending = deferred(); let timer = 0;
        const w = ruleWritingWorkspace({}, { request: async () => pending.promise, setTimer: (callback, delay) => { active.set(++timer, delay); return timer; }, clearTimer: id => active.delete(id) });
        w.acceptOpened(opened(draft())); w.selected = ['a']; const saving = w.saveSelected();
        await Promise.resolve(); await Promise.resolve(); append(w, ' later'); assert.equal(active.size, 0);
        pending.resolve(rejection); await saving;
        assert.deepEqual([...active.values()].sort((a,b) => a-b), [700,3000]); assert.equal(w.current.unknown, false); w.destroy();
    });
}

test('a failed unchanged preflush does not become an automatic retry loop or mark other drafts conflicted', async () => {
    const active = new Map(); let timer = 0;
    const w = ruleWritingWorkspace({}, { request: async () => ({ ok: false, type: 'validation', errors: { draft_revision: ['Changed'] } }), setTimer: (callback, delay) => { active.set(++timer, delay); return timer; }, clearTimer: id => active.delete(id) });
    w.acceptOpened(opened(draft())); append(w, ' first'); w.acceptOpened(opened(draft('b'))); w.selected = ['a','b']; await w.saveSelected();
    assert.equal(w.buffers.a.conflict, true); assert.equal(w.buffers.b.conflict, false); assert.equal(active.size, 0); w.destroy();
});

for (const failure of [{ ok: false, type: 'validation', errors: { 'drafts.a.revision': ['Changed'] } }, { ok: false, type: 'unknown', message: 'Lost response' }]) {
    test(`a chooser is no longer actionable after ${failure.type}`, async () => {
        const calls = [];
        const w = workspace(async op => { calls.push(op); if (op === 'capture-latest') return { ok: true, data: { draft: { ...draft(), origin_rule_id: '10' }, snapshot_json: 'opaque', latest_source: '// latest' } }; return failure; });
        w.acceptOpened(opened({ ...draft(), origin_rule_id: '10' })); await w.captureLatest(); assert.ok(w.current.capture);
        await w.saveCurrent();
        assert.equal(w.current.capture, null); assert.equal(w.current.captureAttempt, null);
        await w.refreshSections(); assert.equal(calls.includes('refresh-sections'), false); w.destroy();
    });
}
