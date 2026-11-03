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

test('Feedback owns a failed A and switching to valid D and back never leaks or erases the failure', async () => {
    const w=workspace(async()=>({ok:false,type:'validation',errors:{source:['Writing exceeds the 1 MiB draft limit.']}}));
    w.acceptOpened(opened(draft('a')));append(w,' failure');await w.flush();const failure=w.current.error;
    w.acceptOpened(opened(draft('d')));assert.equal(w.activeError,'');assert.equal(w.workspaceError,'');assert.equal(w.current.error,null);
    assert.equal(w.draftStatus(),'Draft saved');w.focusDraft('a');assert.equal(w.current.error,failure);assert.match(w.activeError,/1 MiB/);w.destroy();
});
test('late A failure while D is active retains A ownership and explicit D recovery notice', async()=>{
    const pending=deferred(),w=workspace(()=>pending.promise);w.acceptOpened(opened(draft('a')));append(w,' fail');const saving=w.flush();await Promise.resolve();await Promise.resolve();w.acceptOpened(opened(draft('d')));
    pending.resolve({ok:false,type:'validation',errors:{source:['A failed']}});await saving;assert.equal(w.activeError,'');assert.equal(w.workspaceError,'');assert.equal(w.buffers.a.error.operation,'update-draft');
    w.current.unknown=true;w.blocked.d=true;w.current.latest=draft('d','// recovered');w.loadShared();assert.match(w.activeNotice,/not a receipt/);assert.equal(w.current.unknown,false);assert.equal(w.buffers.a.error.message,'A failed');w.destroy();
});
test('real workspace authorization errors remain visible across draft switches',()=>{
    const w=workspace(async()=>({}));w.acceptOpened(opened(draft('a')));const error=Object.assign(new Error('Session expired'),{result:{type:'rejected',status:419}});w.problem(w.current,error,'update-draft');w.acceptOpened(opened(draft('d')));assert.equal(w.workspaceError,'Session expired');w.destroy();
});
test('explicit Value choice survives picker detach as one raw-source edit and undo',()=>{
    const w=workspace(async()=>({})),ticks=[];const source='// שלום 😀\r\n/when @A in #x |';w.acceptOpened(opened(draft('a',source)));
    w.references={fingerprint:'r1',attributes:[{key:'A',label:'A',values:[{semantic_id:'x',label:'X'},{semantic_id:'y',label:'Y'}]}]};
    const text=source.replaceAll('\r\n','\n'),element={value:text,isConnected:true,selectionStart:text.length,selectionEnd:text.length,setSelectionRange(a,b){this.selectionStart=a;this.selectionEnd=b;},focus(){}};
    let connected=true;Object.defineProperty(w,'$refs',{get:()=>connected?{source:element}:{}});w.$nextTick=fn=>ticks.push(fn);
    w.openChoices('values');const choice=w.context.choices.find(x=>x.insertText==='#y');connected=false;assert.equal(w.applyWritingAction(choice),true);ticks.splice(0).forEach(fn=>fn());
    assert.equal(w.current.source,source+'#y');assert.equal(element.value,text+'#y');assert.equal(w.current.sequence,1);w.history('undo');assert.equal(w.current.source,source);w.destroy();
});
test('stale choice and IME cannot insert into newer writing or a different draft',()=>{
    const w=workspace(async()=>({}));w.acceptOpened(opened(draft('a','/when ')));const element={value:'/when ',isConnected:true,selectionStart:6,selectionEnd:6,setSelectionRange(){},focus(){}};w.$refs={source:element};w.references={fingerprint:'1',attributes:[{key:'A',label:'A',values:[]}]};w.openChoices('attributes');const action=w.context.choices[0];
    append(w,'@new');assert.equal(w.applyWritingAction(action),false);assert.equal(w.current.source,'/when @new');w.acceptOpened(opened(draft('d')));assert.equal(w.applyWritingAction(action),false);w.composing={id:'d'};assert.equal(w.applyWritingAction(action),false);w.destroy();
});
test('advisory references failure stays separate from draft persistence and never changes caret receipt or revisions',async()=>{
    const read=deferred(),calls=[],w=workspace(async(op,input)=>{calls.push(op);if(op==='references')return read.promise;return {ok:true,data:ack({...draft(),...input.authoring,revision:2},input.sequence)};});
    w.acceptOpened(opened(draft()));w.current.caret=[1,1];w.receipt='committed';const refs=w.refreshReferences();append(w,' local');await w.flush();read.resolve({ok:false,type:'unknown',message:'Read unavailable'});await refs;
    assert.equal(w.current.unknown,false);assert.equal(w.current.revision,2);assert.equal(w.receipt,'committed');assert.deepEqual(w.current.caret,null);assert.equal(w.current.error,null);assert.match(w.referenceError,/Read unavailable/);assert.deepEqual(calls,['references','update-draft']);w.destroy();
});

test('closed and reopened Choices cannot apply an old proposal even with unchanged source',()=>{
    const w=workspace(async()=>({}));w.acceptOpened(opened(draft('a','/when @A in ')));const element={value:'/when @A in ',isConnected:true,selectionStart:12,selectionEnd:12,setSelectionRange(){},focus(){}};w.$refs={source:element};w.references={fingerprint:'1',attributes:[{key:'A',label:'A',values:[{semantic_id:'x',label:'X'}]}]};
    w.openChoices('values');const old=w.context.choices[0];w.closeChoices(false);w.openChoices('values');assert.equal(w.applyWritingAction(old),false);assert.equal(w.current.source,'/when @A in ');w.destroy();
});
test('failure attempt identity comes from submitted source before a later local edit',async()=>{
    const pending=deferred(),w=workspace(()=>pending.promise);w.acceptOpened(opened(draft('a')));append(w,' first');const sequence=w.current.sequence,saving=w.flush();await Promise.resolve();await Promise.resolve();append(w,' later');pending.resolve({ok:false,type:'validation',errors:{source:['Earlier source rejected']}});await saving;
    assert.equal(w.current.error.attempt.sequence,sequence);assert.notEqual(w.current.sequence,sequence);assert.equal(w.feedbackIssues[0].severity,'previous-attempt');assert.equal(w.current.unknown,false);w.destroy();
});

test('display and pane resize change preferences without editing writing or sending requests',()=>{
    const calls=[],w=workspace(async(op)=>{calls.push(op);return {};});w.acceptOpened(opened(draft('a','// exact\r\nשלום')));w.current.caret=[2,2];const before=JSON.stringify({source:w.current.source,sequence:w.current.sequence,ack:w.current.ack,caret:w.current.caret});
    w.choicesOpen=true;w.setDisplay('writing',{theme:'dark',editor:{wrap:false,autoHeight:false},visible:{learning:false},lowerOrder:['learning','logic']});w.adjustPane(0,-100);
    assert(w.paneWeights.every(n=>n>=10));assert.equal(Math.round(w.paneWeights.reduce((a,b)=>a+b)),100);
    w.setDisplay('data',{visible:{conditions:false},order:['effects','rule']});w.closePane('feedback');
    assert.equal(JSON.stringify({source:w.current.source,sequence:w.current.sequence,ack:w.current.ack,caret:w.current.caret}),before);assert.deepEqual(calls,[]);w.destroy();
});
test('active pointer resizing releases its listeners and capture on destruction',()=>{
    const w=workspace(async()=>({}));w.acceptOpened(opened(draft()));const listeners=new Map();let captured=null;
    const handle={closest:()=>({getBoundingClientRect:()=>({width:1000})}),setPointerCapture:id=>{captured=id},hasPointerCapture:id=>captured===id,releasePointerCapture:()=>{captured=null},addEventListener:(name,fn)=>listeners.set(name,fn),removeEventListener:name=>listeners.delete(name)};
    w.startResize({button:0,pointerId:7,clientX:100,currentTarget:handle},0);assert.equal(listeners.size,3);listeners.get('pointermove')({clientX:180});assert.notDeepEqual(w.paneWeights,[60,40]);w.destroy();assert.equal(listeners.size,0);assert.equal(captured,null);
});
test('record visibility and order do not filter complete server JSON or YAML copy',async()=>{
    const descriptor=Object.getOwnPropertyDescriptor(globalThis,'navigator'),copied=[];
    Object.defineProperty(globalThis,'navigator',{configurable:true,value:{clipboard:{writeText:async text=>copied.push(text)}}});
    try {const w=workspace(async()=>({}));w.inspection={json:'COMPLETE JSON',yaml:'COMPLETE YAML',records:[{key:'conditions',label:'Conditions',rows:[{cells:[]}]}],draft_json:'DRAFT',capture_json:'CAPTURE'};w.setDisplay('data',{visible:{conditions:false},order:['source','rule']});assert(!w.inspectionSections.some(x=>x.key==='conditions'));await w.copy(w.inspection.json);await w.copy(w.inspection.yaml);assert.deepEqual(copied,['COMPLETE JSON','COMPLETE YAML']);w.destroy();}
    finally {if(descriptor)Object.defineProperty(globalThis,'navigator',descriptor);else delete globalThis.navigator;}
});
test('Insert text preserves literal delimiters quotes and line breaks as one undoable edit',async()=>{
    const {parseRuleWriting}=await import('../../resources/js/configurator-rule-writing-model.js');const w=workspace(async()=>({})),source='// שלום\r\n/set-hint @A ',text=source.replaceAll('\r\n','\n');w.acceptOpened(opened(draft('a',source)));
    const element={value:text,isConnected:true,selectionStart:text.length,selectionEnd:text.length,setSelectionRange(a,b){this.selectionStart=a;this.selectionEnd=b},focus(){}};w.$refs={source:element};w.openTextHelper();w.textValue='First "quote"\nclose ]] and \\ path';w.textMode='block';assert.equal(w.insertText(),true);const parsed=parseRuleWriting(w.current.source);assert.deepEqual(parsed.diagnostics,[]);assert.equal(parsed.statements[0].text,w.textValue);assert(w.current.source.startsWith(source));w.history('undo');assert.equal(w.current.source,source);w.destroy();
});

test('display before the first draft never caches an empty editor reference',()=>{
    const w=workspace(async()=>({})),ticks=[];let earlyLookups=0;Object.defineProperty(w,'$refs',{get(){earlyLookups++;return {};}});w.$nextTick=fn=>ticks.push(fn);
    w.setDisplay('writing',{theme:'dark'});w.resetDisplay();w.syncEditor();w.rememberEditor();ticks.splice(0).forEach(fn=>fn());assert.equal(earlyLookups,0);assert.equal(w.current,null);w.destroy();
});

test('selected unopened A failure stays labelled off-draft without making saved D fail',async()=>{
    const w=workspace(async()=>({ok:false,type:'validation',errors:{'drafts.a.source':['A source rejected']}}));w.acceptOpened(opened(draft('d','// valid D')));w.current.caret=[2,2];w.receipt='D receipt';w.listedDrafts.a={id:'a',revision:1,label:'Unopened A'};w.selected=['a'];const before=JSON.stringify({source:w.current.source,caret:w.current.caret,receipt:w.receipt});
    await w.saveSelected();assert.equal(w.workspaceError,'');assert.equal(w.activeError,'');assert.equal(JSON.stringify({source:w.current.source,caret:w.current.caret,receipt:w.receipt}),before);
    const failure=w.offDraftFailures.find(item=>item.draftId==='a');assert.equal(failure.label,'Unopened A');assert.equal(failure.operation,'save-selected');assert.equal(failure.message,'A source rejected');
    w.acceptOpened(opened(draft('a')));assert.equal(w.activeError,'A source rejected');w.focusDraft('d');assert.equal(w.activeError,'');w.destroy();
});
test('unknown unopened selection retains blocked A when its shared draft is opened',async()=>{
    const calls=[],w=workspace(async(op)=>{calls.push(op);throw Error('lost')});w.acceptOpened(opened(draft('d')));w.listedDrafts.a={id:'a',revision:1};w.selected=['a'];await w.saveSelected();assert.equal(w.blocked.a,true);assert.equal(w.current.unknown,false);assert.equal(w.workspaceError,'');
    w.acceptOpened(opened(draft('a')));assert.equal(w.current.unknown,true);assert.equal(w.blocked.a,true);assert.equal(w.current.error.operation,'save-selected');assert.equal(await w.saveSelected(),false);assert.deepEqual(calls,['save-selected']);
    w.current.latest={...draft('a'),revision:2};w.loadShared();assert.equal(w.current.unknown,false);assert.equal(w.blocked.a,undefined);assert.equal(w.current.error,null);assert.match(w.activeNotice,/not a receipt/);assert.deepEqual(calls,['save-selected']);w.destroy();
});
test('caret-only sourceSelection invalidates an old choice while same-caret interaction stays valid',()=>{
    const w=workspace(async()=>({})),source='/when @A in ';w.acceptOpened(opened(draft('a',source)));const element={value:source,isConnected:true,selectionStart:source.length,selectionEnd:source.length,setSelectionRange(a,b){this.selectionStart=a;this.selectionEnd=b},focus(){}};w.$refs={source:element};w.references={fingerprint:'1',attributes:[{key:'A',label:'A',values:[{semantic_id:'x',label:'X'}]}]};
    w.openChoices('values');const old=w.context.choices[0];element.selectionStart=0;element.selectionEnd=0;w.sourceSelection({target:element});assert.equal(w.applyWritingAction(old),false);assert.equal(w.current.source,source);
    assert.equal(w.current.sequence,0);assert.equal(w.current.history.canUndo,false);
    element.selectionStart=source.length;element.selectionEnd=source.length;w.sourceSelection({target:element});w.openChoices('values');const current=w.context.choices[0];w.sourceSelection({target:element});assert.equal(w.applyWritingAction(current),true);assert.equal(w.current.source,source+'#x');assert.equal(w.current.sequence,1);w.history('undo');assert.equal(w.current.source,source);w.destroy();
});
test('first same-caret pointer interaction accepts an already rendered next-step action',()=>{
    const w=workspace(async()=>({})),source='/when ';w.acceptOpened(opened(draft('a',source)));
    const element={value:source,isConnected:true,selectionStart:source.length,selectionEnd:source.length,setSelectionRange(a,b){this.selectionStart=a;this.selectionEnd=b},focus(){}};w.$refs={source:element};
    const action=w.context.nextGroups.find(group=>group.category==='Group').actions[0];

    w.rememberEditor();assert.equal(w.applyWritingAction(action),true);

    assert.equal(w.current.source,'/when all {\n  \n}');assert.equal(w.current.sequence,1);w.history('undo');assert.equal(w.current.source,source);w.destroy();
});
test('Mapping toolbar follows grammar: Pair first, Case after Pair and Other only after a case',()=>{
    const w=workspace(async()=>({}));w.acceptOpened(opened({...draft('a',''),kind:'Mapping'}));let items=w.helperItems('mapping');assert.deepEqual(items.map(item=>item.text.trim()),['/pair']);
    w.acceptOpened(opened({...draft('a','/pair @A -> @B\n'),kind:'Mapping'}),true);items=w.helperItems('mapping');assert.deepEqual(items.map(item=>item.text.trim()),['/case']);
    w.acceptOpened(opened({...draft('a','/pair @A -> @B\n/case #x -> #y'),kind:'Mapping'}),true);items=w.helperItems('mapping');assert(!items.some(item=>item.text.trim()==='/pair'));assert(items.some(item=>item.text.trim()==='/other hide'));assert(items.some(item=>item.text.trim()==='/other disable'));w.destroy();
});
test('closing a condition group adds one structural newline after a predicate and supports blank lines and Undo',async()=>{
    const {parseRuleWriting}=await import('../../resources/js/configurator-rule-writing-model.js');
    for(const source of ['/when all {\n @A = #x','/when all {\r\n @A = #x','/when all {\r @A = #x','/when all {\n @A = #x\n  ']) {
        const w=workspace(async()=>({})),text=source.replace(/\r\n|\r/g,'\n');w.acceptOpened(opened(draft('a',source)));const element={value:text,isConnected:true,selectionStart:text.length,selectionEnd:text.length,setSelectionRange(a,b){this.selectionStart=a;this.selectionEnd=b},focus(){}};w.$refs={source:element};w.rememberEditor();assert.equal(w.closeConditionGroup(),true);assert.deepEqual(parseRuleWriting(w.current.source).diagnostics,[]);assert(w.current.source.startsWith(source));assert.equal(w.current.sequence,1);
        assert.equal(w.current.source,source+(source.endsWith('  ')?'}':'\n}'));w.history('undo');assert.equal(w.current.source,source);w.destroy();
    }
});

test('a close-group Choice shares the structural newline edit and exact raw-source Undo',async()=>{
    const {parseRuleWriting}=await import('../../resources/js/configurator-rule-writing-model.js');
    const source='// שלום 😀\r\n/when all {\r\n @A = #x',w=workspace(async()=>({})),text=source.replaceAll('\r\n','\n');w.acceptOpened(opened(draft('a',source)));
    const element={value:text,isConnected:true,selectionStart:text.length,selectionEnd:text.length,setSelectionRange(a,b){this.selectionStart=a;this.selectionEnd=b},focus(){}};w.$refs={source:element};w.openChoices('commands');

    assert.equal(w.applyWritingAction(w.context.choices.find(action=>action.insertText==='}')),true);

    assert.equal(w.current.source,source+'\n}');assert.deepEqual(parseRuleWriting(w.current.source).diagnostics,[]);assert.equal(w.current.sequence,1);w.history('undo');assert.equal(w.current.source,source);w.destroy();
});

test('the newest References refresh wins over an older reply without changing authoring or saved state',async()=>{
    const older=deferred(),newer=deferred(),calls=[],w=workspace(async(op,input)=>{calls.push([op,input]);return calls.length===1?older.promise:newer.promise;});
    w.acceptOpened(opened({...draft('a','// שלום 😀\r\n/when @A in #x |'),revision:7}));w.current.caret=[8,12];w.current.unknown=true;w.current.conflict=true;w.blocked.a=true;w.receipt='existing saved receipt';w.references={fingerprint:'original'};
    const before=JSON.stringify({buffer:w.current,receipt:w.receipt,blocked:w.blocked}),first=w.refreshReferences(),second=w.refreshReferences();

    newer.resolve({ok:true,data:{fingerprint:'newest',attributes:[]}});assert.equal(await second,true);
    older.resolve({ok:true,data:{fingerprint:'older',attributes:[]}});assert.equal(await first,false);

    assert.deepEqual(w.references,{fingerprint:'newest',attributes:[]});assert.equal(w.referenceError,'');assert.equal(w.referencesLoading,false);
    assert.equal(JSON.stringify({buffer:w.current,receipt:w.receipt,blocked:w.blocked}),before);assert.deepEqual(calls,[['references',{}],['references',{}]]);w.destroy();
});

test('References success and failure arriving after destroy cannot change state or replay requests',async()=>{
    for(const response of [{ok:true,data:{fingerprint:'late',attributes:[]}},{ok:false,type:'unknown',message:'Late failed read'}]) {
        const pending=deferred(),calls=[],w=workspace(async(op,input)=>{calls.push([op,input]);return pending.promise;});
        w.acceptOpened(opened({...draft('a','// raw\r\nשלום 😀'),revision:4}));w.current.caret=[1,3];w.current.unknown=true;w.current.conflict=true;w.blocked.a=true;w.receipt='saved before close';w.references={fingerprint:'before'};w.referenceError='Earlier advisory read';
        const state=()=>JSON.stringify({buffer:w.current,receipt:w.receipt,blocked:w.blocked,references:w.references,loading:w.referencesLoading,error:w.referenceError});
        const reading=w.refreshReferences();w.destroy();const before=state();

        pending.resolve(response);assert.equal(await reading,false);

        assert.equal(state(),before);assert.deepEqual(calls,[['references',{}]]);
    }
});

test('opening D clears Learning selected in A without changing source history receipts revisions or requests',async()=>{
    const calls=[],w=workspace(async(op)=>{calls.push(op);return {}});
    w.references={fingerprint:'r1',attributes:[{key:'A',label:'Source A',values:[{semantic_id:'x',label:'A-only Value'}]}]};
    w.acceptOpened(opened({...draft('d',''),kind:'Mapping'}));w.acceptOpened(opened(draft('a','/when @A in ')));w.current.caret=[12,12];w.receipt='saved receipt';
    w.learningAction=w.context.choices.find(action=>action.insertText==='#x');assert.equal(w.learningAction.label,'A-only Value · x');
    const authoring=()=>JSON.stringify({buffers:Object.values(w.buffers).map(b=>({id:b.id,source:b.source,caret:b.caret,sequence:b.sequence,revision:b.revision,ack:b.ack,history:[b.history.source,b.history.canUndo,b.history.canRedo],unknown:b.unknown,conflict:b.conflict})),receipt:w.receipt,blocked:w.blocked});
    const before=authoring();

    await w.openDraft('d');

    assert.equal(w.currentId,'d');assert.equal(w.learningAction,null);assert.equal(w.activeLearningAction,null);assert.equal(w.context.learning.title,'Write one complete instruction');assert.equal(authoring(),before);assert.deepEqual(calls,[]);w.destroy();
});

test('Learning retains applicable same-draft help and fences it after a caret source or reference change',async()=>{
    const calls=[],w=workspace(async(op)=>{calls.push(op);return {}}),source='/when @A in ';
    w.references={fingerprint:'r1',attributes:[{key:'A',label:'A',values:[{semantic_id:'x',label:'X'}]}]};w.acceptOpened(opened(draft('a',source)));
    const element={value:source,isConnected:true,selectionStart:source.length,selectionEnd:source.length,setSelectionRange(a,b){this.selectionStart=a;this.selectionEnd=b},focus(){}};w.$refs={source:element};w.rememberEditor();
    const action=w.context.choices.find(item=>item.insertText==='#x');w.learningAction=action;

    w.setDisplay('writing',{theme:'dark'});await w.openDraft('a');w.sourceSelection({target:element});assert.equal(w.activeLearningAction,action);
    element.selectionStart=0;element.selectionEnd=0;w.sourceSelection({target:element});assert.equal(w.activeLearningAction,null);
    element.selectionStart=source.length;element.selectionEnd=source.length;w.sourceSelection({target:element});assert.equal(w.activeLearningAction,action);
    w.references={...w.references,fingerprint:'r2'};assert.equal(w.activeLearningAction,null);w.references={...w.references,fingerprint:'r1'};
    append(w,'#x');assert.equal(w.activeLearningAction,null);assert.deepEqual(calls,[]);w.destroy();
});
