import { parseRuleWriting, ruleWritingByteOffset, ruleWritingEditorOffset } from './configurator-rule-writing-model.js';
import { projectWritingSource, writingHistory } from './configurator-rule-writing-editor.js';
const content = b => ({ source: b.source, label: b.label, description: b.description });
const same = (a, b) => a?.source === b?.source && a?.label === b?.label && a?.description === b?.description;
const safe = n => Number.isSafeInteger(n) && n >= 0;
const uuid = () => {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = bytes[6] & 15 | 64; bytes[8] = bytes[8] & 63 | 128;
    return [...bytes].map((v, i) => ([4, 6, 8, 10].includes(i) ? '-' : '') + v.toString(16).padStart(2, '0')).join('');
};
class RequestFailure extends Error {
    constructor(result) { super(Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'The request could not be completed.'); this.result = result; }
}

export function ruleWritingWorkspace(config = {}, dependencies = {}) {
    let destroyed = false, tail = Promise.resolve(), pending = 0, removeGuard, captureSequence = 0;
    const timers = new Map(), setTimer = dependencies.setTimer ?? setTimeout, clearTimer = dependencies.clearTimer ?? clearTimeout;
    const request = dependencies.request ?? (async (operation, input) => {
        const response = await fetch(config.operationUrl.replace('__operation__', operation), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf }, body: JSON.stringify(input) });
        let result;
        try { result = await response.json(); } catch { throw new RequestFailure({ type: 'unknown', message: 'The response could not be read. Inspect the current records before retrying.' }); }
        if (result?.ok === undefined) result = { ok: false, type: [400, 401, 403, 404, 413, 415, 419, 422].includes(response.status) ? 'rejected' : 'unknown', message: result.message, status: response.status };
        return result;
    });
    const stopTimer = id => { const entry = timers.get(id); if (entry) { clearTimer(entry.debounce); clearTimer(entry.maximum); timers.delete(id); } };
    return {
        buffers: {}, currentId: null, selectedDrafts: {}, blocked: {}, drafts: [], listedDrafts: {}, rules: [], page: 1, hasMore: false, search: '', rulePage: 1, ruleMore: false, ruleSearch: '', picker: false,
        tab: 'writing', dataView: 'records', inspection: null, inspectedId: null, receipt: null, notice: '', error: '', failedRuleId: null, busy: false, composing: null, editContext: null, historyHandled: null,
        get selected() { return Object.keys(this.selectedDrafts); },
        set selected(ids) {
            const selection = {};
            for (const id of ids) selection[id] = this.selectedDrafts[id] ?? { id, revision: this.buffers[id]?.revision ?? this.listedDrafts[id]?.revision };
            this.selectedDrafts = selection;
        },
        get current() { return this.buffers[this.currentId] ?? null; },
        get pendingCount() { return Object.values(this.buffers).filter(b => this.dirty(b)).length; },
        t(key, fallback) { return config.labels?.[key] ?? fallback; },
        dirty(b) { return Boolean(b && (b.pendingText !== null || !same(b, b.ack) || b.sequence !== b.ack?.sequence)); },
        hasUnsent() { return Boolean(this.composing || Object.values(this.buffers).some(b => this.dirty(b) || b.unknown)); },
        async init() {
            if (typeof window !== 'undefined') {
                const unload = event => { if (this.hasUnsent()) { event.preventDefault(); event.returnValue = ''; } };
                const navigate = event => { if (!event.defaultPrevented && this.hasUnsent() && !window.confirm(this.t('leave', 'Leave with unsent writing? Copy it before leaving.'))) event.preventDefault(); };
                window.addEventListener('beforeunload', unload); document.addEventListener('livewire:navigate', navigate);
                removeGuard = () => { window.removeEventListener('beforeunload', unload); document.removeEventListener('livewire:navigate', navigate); };
            }
            await this.refreshList();
            if (config.requestedRule && !destroyed) await this.openRule(config.requestedRule);
        },
        destroy() { destroyed = true; removeGuard?.(); for (const id of timers.keys()) stopTimer(id); },
        async send(operation, input) {
            if (destroyed) throw new Error('Writing workspace closed.');
            let result;
            try { result = await request(operation, input); } catch (error) { throw error instanceof RequestFailure ? error : new RequestFailure({ type: 'unknown', message: this.t('outcome_unknown', 'The outcome is unknown. Keep your writing and inspect current records.') }); }
            if (destroyed) throw new Error('Writing workspace closed.');
            if (!result?.ok) throw new RequestFailure(result ?? { type: 'unknown' });
            return result.data;
        },
        enqueue(work, buffer = null, operation = '') {
            pending++; this.busy = true;
            const next = tail.then(async () => { if (destroyed) return false; try { return await work(); } catch (error) { return this.problem(buffer, error, operation); } finally { pending--; if (!destroyed) this.busy = pending > 0; } });
            tail = next.catch(() => {}); return next;
        },
        problem(b, error, operation) {
            if (destroyed) return false;
            const result = error.result ?? { type: 'unknown' };
            this.error = error.message;
            if (operation === 'open-rule' && result.errors?.rule) this.failedRuleId = error.ruleId ?? null;
            if (b && (!error.draftId || error.draftId === b.id)) {
                b.error = { ...result, message: error.message, operation };
                if (operation === 'refresh-sections') { b.capture = null; b.captureAttempt = null; }
                if (result.errors?.draft_revision || result.errors?.[`drafts.${b.id}.revision`]) { b.conflict = true; b.capture = null; b.captureAttempt = null; stopTimer(b.id); }
                if (result.type === 'unknown' || result.type === 'failure' && !result.rolledBack) { b.unknown = true; b.capture = null; b.captureAttempt = null; this.blocked[b.id] = true; stopTimer(b.id); }
            }
            return false;
        },
        acceptOpened(result, replace = false, focus = true) {
            if (destroyed) return null;
            const d = result.draft, previous = this.buffers[d.id];
            if (previous && !replace) {
                if (previous.revision !== d.revision) { previous.latest = d; previous.conflict = true; }
            } else {
                this.buffers[d.id] = { ...d, sequence: result.acknowledgement?.sequence ?? 0, ack: { ...content(d), sequence: result.acknowledgement?.sequence ?? 0 }, history: writingHistory(d.source), diagnostics: parseRuleWriting(d.source).diagnostics, error: null, unknown: false, conflict: false, latest: null, capture: null, refreshing: false, paused: false, pendingText: null, caret: null };
                delete this.blocked[d.id];
            }
            if (focus) this.focusDraft(d.id);
            return this.buffers[d.id];
        },
        focusDraft(id) {
            const previous = this.current, sourceElement = previous ? this.$refs?.source : null;
            if (previous) { previous.capture = null; previous.captureAttempt = null; }
            this.currentId = id; this.tab = 'writing';
            this.$nextTick?.(() => { if (this.currentId === id) this.syncEditor(null, sourceElement); });
        },
        syncEditor(selection = null, sourceElement = null) {
            const element = sourceElement ?? this.$refs?.source, b = this.current;
            if (!element || !b || sourceElement && !sourceElement.isConnected) return;
            const text = b.pendingText ?? projectWritingSource(b.source).text;
            if (element.value !== text) element.value = text;
            const range = selection ?? b.caret;
            if (range) element.setSelectionRange(...range);
        },
        changeSource(text, context = {}, caret = null) {
            const b = this.current;
            if (!b || b.refreshing) return false;
            b.capture = null;
            const result = b.history.edit(text, context, caret);
            if (!result.ok) { b.pendingText = text; b.error = { type: 'ambiguous_edit', message: this.t('ambiguous_edit', 'The edit location is ambiguous. Copy the pending text or undo this edit.') }; stopTimer(b.id); return false; }
            b.pendingText = null; b.caret = caret;
            if (result.patch) { b.source = result.source; this.changed(b); }
            return true;
        },
        changeMetadata(key, value) {
            const b = this.current;
            if (!b || b.refreshing || !['label', 'description'].includes(key) || b[key] === value) return;
            b[key] = value; this.changed(b);
        },
        changed(b) { b.sequence++; b.capture = null; b.captureAttempt = null; b.diagnostics = parseRuleWriting(b.source).diagnostics; if (!b.conflict && !b.unknown) b.error = null; this.schedule(b); },
        beforeInput(event) {
            this.historyHandled = null;
            if (event.inputType === 'historyUndo' || event.inputType === 'historyRedo') {
                if (event.cancelable) { event.preventDefault(); this.history(event.inputType === 'historyUndo' ? 'undo' : 'redo'); this.historyHandled = event.inputType; }
                return;
            }
            this.editContext = { start: event.target.selectionStart, end: event.target.selectionEnd, inputType: event.inputType, data: event.data };
        },
        input(event) {
            if (event.inputType === 'historyUndo' || event.inputType === 'historyRedo') {
                if (this.historyHandled !== event.inputType) this.history(event.inputType === 'historyUndo' ? 'undo' : 'redo');
                this.historyHandled = null; this.syncEditor(); return;
            }
            this.historyHandled = null;
            if (this.composing || event.isComposing) return;
            this.changeSource(event.target.value, this.editContext ?? {}, [event.target.selectionStart, event.target.selectionEnd]); this.editContext = null;
        },
        compositionStart(event) { this.composing = { id: this.currentId, start: event.target.selectionStart, end: event.target.selectionEnd, inputType: 'insertCompositionText' }; stopTimer(this.currentId); },
        compositionEnd(event) { const context = this.composing; this.composing = null; if (context?.id === this.currentId) this.changeSource(event.target.value, context, [event.target.selectionStart, event.target.selectionEnd]); this.editContext = null; },
        keydown(event) { if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'z' && !event.isComposing) { event.preventDefault(); this.history(event.shiftKey ? 'redo' : 'undo'); } },
        history(direction) {
            const b = this.current; if (!b || b.refreshing || this.composing) return;
            if (b.pendingText !== null) { b.pendingText = null; b.error = null; this.syncEditor(); return; }
            const result = b.history[direction](); if (!result) return;
            b.source = result.source; b.caret = result.selection; this.changed(b); this.syncEditor(result.selection);
        },
        schedule(b) {
            if (destroyed || b.paused || b.conflict || b.unknown || b.pendingText !== null || this.composing?.id === b.id) return;
            const entry = timers.get(b.id) ?? { maximum: setTimer(() => this.flush(b.id), 3000) };
            clearTimer(entry.debounce); entry.debounce = setTimer(() => this.flush(b.id), 700);
            entry.maximum?.unref?.(); entry.debounce?.unref?.(); timers.set(b.id, entry);
        },
        snapshot(b) { return { ...content(b), sequence: b.sequence }; },
        async persist(b, snapshot = this.snapshot(b)) {
            if (b.conflict || b.unknown || b.pendingText !== null || ruleWritingByteOffset(snapshot.source, 0) === null) throw new RequestFailure({ type: 'validation', message: 'Resolve this buffer before saving its draft.' });
            if (same(snapshot, b.ack) && snapshot.sequence === b.ack.sequence) return b.revision;
            try {
                const result = await this.send('update-draft', { id: b.id, expected_revision: b.revision, authoring: content(snapshot), sequence: snapshot.sequence });
                this.acceptAck(b, result, snapshot); return b.revision;
            } catch (error) { error.draftId = b.id; throw error; }
        },
        acceptAck(b, result, snapshot) {
            const ack = result.acknowledgement;
            if (result.draft?.id !== b.id || !ack || result.draft.revision !== ack.revision || !same(result.draft, ack) || ack.sequence !== snapshot.sequence || !safe(ack.revision) || ack.revision < 1 || !same(ack, snapshot)) throw new RequestFailure({ type: 'unknown', message: 'The acknowledgement does not match the submitted writing.' });
            if (b.capture && (ack.revision !== b.revision || ack.sequence !== b.ack.sequence)) { b.capture = null; b.captureAttempt = null; }
            b.revision = ack.revision; b.ack = { ...content(ack), sequence: ack.sequence }; b.origin_rule_id = result.draft.origin_rule_id; b.error = null;
        },
        flush(id = this.currentId) {
            const b = this.buffers[id]; stopTimer(id);
            if (!b || b.paused || b.conflict || b.unknown || this.composing?.id === id) return Promise.resolve(false);
            return this.enqueue(async () => { await this.persist(b); if (this.dirty(b)) this.schedule(b); return true; }, b, 'update-draft');
        },
        refreshList() { return this.enqueue(async () => { const data = await this.send('draft-summaries', { page: this.page, search: this.search }); this.drafts = data.rows; for (const row of data.rows) this.listedDrafts[row.id] = { ...row }; this.hasMore = data.has_more; return true; }); },
        openDraft(id) {
            if (this.composing) return Promise.resolve(false);
            if (this.buffers[id]) { this.focusDraft(id); return Promise.resolve(true); }
            return this.enqueue(async () => { this.acceptOpened(await this.send('open-draft', { id })); return true; }, null, 'open-draft');
        },
        createDraft(kind, authoring = {}) {
            if (this.composing) return Promise.resolve(false);
            const id = (dependencies.uuid ?? uuid)();
            return this.enqueue(async () => { this.acceptOpened(await this.send('create-draft', { id, authoring: { kind, source: '', label: null, description: null, ...authoring }, sequence: 0 })); return true; }, null, 'create-draft');
        },
        duplicate() { const b = this.current; if (!b || b.pendingText !== null) return Promise.resolve(false); return this.createDraft(b.kind, content(b)); },
        openRule(ruleId) {
            if (this.composing) return Promise.resolve(false);
            const newId = (dependencies.uuid ?? uuid)();
            return this.enqueue(async () => { try { this.acceptOpened(await this.send('open-rule', { rule_id: String(ruleId), new_draft_id: newId })); this.failedRuleId = null; this.picker = false; return true; } catch (error) { error.ruleId = String(ruleId); throw error; } }, null, 'open-rule');
        },
        showRules() { this.picker = true; return this.enqueue(async () => { const data = await this.send('saved-rule-summaries', { page: this.rulePage, search: this.ruleSearch }); this.rules = data.rows; this.ruleMore = data.has_more; }); },
        saveCurrent() { return this.saveSelected([this.currentId]); },
        saveSelected(ids = [...this.selected]) {
            if (!ids.length || ids.some(id => !id || this.blocked[id] || this.buffers[id]?.unknown || this.buffers[id]?.pendingText !== null && this.buffers[id]?.pendingText !== undefined || this.composing?.id === id)) return Promise.resolve(false);
            const snapshots = new Map(ids.map(id => [id, this.buffers[id] ? this.snapshot(this.buffers[id]) : null]));
            const selectedRevisions = new Map(ids.map(id => [id, this.selectedDrafts[id]?.revision]));
            for (const id of ids) { stopTimer(id); if (this.buffers[id]) this.buffers[id].paused = true; }
            return this.enqueue(async () => {
                let result;
                try {
                    const selection = [];
                    for (const id of ids) {
                        const b = this.buffers[id], revision = selectedRevisions.get(id);
                        if (!b && (!safe(revision) || revision < 1)) throw new RequestFailure({ type: 'validation', message: 'Select this draft again before saving.' });
                        if (b) await this.persist(b, snapshots.get(id));
                        selection.push({ id, expected_revision: b?.revision ?? revision, sequence: snapshots.get(id)?.sequence ?? 0 });
                    }
                    result = await this.send('save-selected', { selection });
                    if (typeof result.receipt_json !== 'string' || result.items?.length !== ids.length || new Set(result.items.map(item => item.draft?.id)).size !== ids.length || result.items.some(item => !ids.includes(item.draft?.id))) throw new RequestFailure({ type: 'unknown', message: 'Incomplete save acknowledgement.' });
                    for (const item of result.items) {
                        const snapshot = snapshots.get(item.draft.id);
                        if (snapshot && (!same(item.acknowledgement, snapshot) || item.acknowledgement.sequence !== snapshot.sequence)) throw new RequestFailure({ type: 'unknown', message: 'Save acknowledgement does not match this writing.' });
                    }
                    this.receipt = result.receipt_json;
                    for (const item of result.items) {
                        const b = this.buffers[item.draft.id] ?? this.acceptOpened(item, false, false);
                        this.acceptAck(b, item, snapshots.get(b.id) ?? { ...content(item.acknowledgement), sequence: 0 });
                    }
                    this.notice = this.t('saved_rules', 'Selected rules saved.'); this.error = '';
                    if (typeof window !== 'undefined') window.Livewire?.dispatch('configurator-updated');
                    try { const id = ids.includes(this.currentId) ? this.currentId : ids[0]; this.inspection = await this.send('inspect-draft', { id }); this.inspectedId = id; } catch { this.notice = this.t('saved_refresh_failed', 'Rules saved. Inspection refresh failed; refresh records separately.'); }
                    return true;
                } catch (error) {
                    for (const id of ids) { this.problem(this.buffers[id], error, 'save-selected'); if ((!error.draftId || error.draftId === id) && (error.result?.type === 'unknown' || error.result?.type === 'failure' && !error.result.rolledBack)) this.blocked[id] = true; }
                    return false;
                } finally {
                    for (const id of ids) { const b = this.buffers[id]; if (b) { b.paused = false; const submitted = snapshots.get(id); const later = submitted && (b.sequence !== submitted.sequence || !same(b, submitted)); if (this.dirty(b) && (!b.error || later)) this.schedule(b); } }
                }
            });
        },
        inspect(id = this.currentId) { if (!id) return Promise.resolve(false); return this.enqueue(async () => { this.inspection = await this.send('inspect-draft', { id }); this.inspectedId = id; return true; }, null, 'inspect-draft'); },
        showData() { this.tab = 'data'; if (this.currentId && this.inspectedId !== this.currentId) return this.inspect(); },
        compareShared() { const b = this.current; if (!b) return; return this.enqueue(async () => { b.latest = (await this.send('open-draft', { id: b.id })).draft; return true; }, b, 'open-draft'); },
        loadShared() { const b = this.current; if (!b?.latest || !(dependencies.confirm ?? globalThis.confirm)(this.t('replace_local', 'Replace local writing with the displayed shared draft? Copy your writing first.'))) return; stopTimer(b.id); this.acceptOpened({ draft: b.latest }, true); this.notice = this.t('loaded_shared', 'Current shared draft loaded. This is not a receipt for a previous uncertain save.'); },
        captureLatest() {
            const b = this.current; if (!b?.origin_rule_id || b.unknown || b.conflict || this.composing) return Promise.resolve(false);
            const attempt = ++captureSequence, snapshot = this.snapshot(b); b.captureAttempt = attempt; b.capture = null; stopTimer(b.id);
            return this.enqueue(async () => {
                await this.persist(b, snapshot);
                if (this.dirty(b) || b.captureAttempt !== attempt) { this.notice = 'Writing changed. Save the current draft and refresh again.'; return false; }
                const result = await this.send('capture-latest', { id: b.id, expected_revision: b.revision });
                if (b.captureAttempt !== attempt || this.dirty(b) || b.sequence !== snapshot.sequence || !same(b, snapshot) || result.draft.revision !== b.revision) return false;
                b.capture = { ...result, sequence: b.sequence, revision: b.revision, mine: content(b), sections: ['label', 'conditions', b.kind === 'Mapping' ? 'mapping' : 'effects'] }; return true;
            }, b, 'capture-latest');
        },
        cancelCapture() { if (this.current) { this.current.capture = null; this.current.captureAttempt = null; } },
        refreshSections() {
            const b = this.current, capture = b?.capture;
            if (!capture || b.conflict || b.unknown || this.blocked[b.id] || this.dirty(b) || b.sequence !== capture.sequence || !same(b, capture.mine)) return Promise.resolve(false);
            b.refreshing = true;
            return this.enqueue(async () => {
                try {
                    if (b.capture !== capture || b.conflict || b.unknown || this.blocked[b.id] || this.dirty(b) || b.revision !== capture.revision) return false;
                    const result = await this.send('refresh-sections', { id: b.id, expected_revision: capture.revision, snapshot_json: capture.snapshot_json, sections: [...capture.sections], sequence: b.sequence + 1 });
                    this.acceptOpened(result, true, this.currentId === b.id); this.notice = this.t('draft_refreshed', 'Draft sections updated. Save the rule separately when ready.'); return true;
                } finally { b.refreshing = false; }
            }, b, 'refresh-sections');
        },
        diagnosticLine(item) { const offset = ruleWritingEditorOffset(this.current?.source ?? '', item.start ?? 0); return offset === null ? '?' : projectWritingSource(this.current.source.slice(0, offset)).text.split('\n').length; },
        goToDiagnostic(item) { const b = this.current, raw = ruleWritingEditorOffset(b.source, item.start ?? 0); if (raw === null) return; const offset = projectWritingSource(b.source).editorOffset(raw); if (offset !== null) { this.$refs?.source?.focus(); this.$refs?.source?.setSelectionRange(offset, offset); } },
        ruleEditUrl(id = this.current?.origin_rule_id) { if (!id || !config.backUrl) return null; const url = new URL(config.backUrl, globalThis.location?.href ?? 'http://localhost'); url.searchParams.set('rule', id); return url.href; },
        async copy(text) { try { await navigator.clipboard.writeText(text); this.notice = this.t('copied', 'Copied.'); } catch { this.error = this.t('copy_unavailable', 'Clipboard is unavailable. Select the source text and copy it manually.'); } },
        draftStatus(b = this.current) { if (!b) return ''; if (b.unknown) return this.t('unknown', 'Outcome unknown'); if (b.conflict) return this.t('conflict', 'Shared draft changed'); if (b.error || b.pendingText !== null) return this.t('retained', 'Local writing retained'); return this.dirty(b) ? this.t('draft_pending', 'Draft not yet saved') : this.t('draft_saved', 'Draft saved'); },
    };
}
