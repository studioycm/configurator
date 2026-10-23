import { browserProfile } from './admin-profile.js';

export function masterBatchColumns(value = {}) {
    return { tags: typeof value?.tags === 'boolean' ? value.tags : false, specification: typeof value?.specification === 'boolean' ? value.specification : false };
}

export function nameEntryDirection(event) {
    if (event.key !== 'Enter' || event.isComposing || event.keyCode === 229 || event.ctrlKey || event.altKey || event.metaKey || event.repeat || event.defaultPrevented) return 0;
    if (event.target.closest('[role="listbox"], [role="combobox"][aria-expanded="true"], [data-suggestion-popup]') || event.target.getAttribute('aria-expanded') === 'true') return 0;
    return event.shiftKey ? -1 : 1;
}

const inputPath = input => input.getAttribute(input.getAttributeNames().find(name => name.startsWith('wire:model')) ?? '');

const rows = value => value && typeof value === 'object' ? Object.values(value) : [];
const hasText = value => value != null && String(value).trim() !== '';
const masterHasDraft = row => ['label', 'semantic_id', 'description', 'tags'].some(key => (Array.isArray(row?.[key]) ? row[key] : [row?.[key]]).some(hasText));

export function attributeDraftIsDirty(state = {}) {
    if (rows(state.master_values).some(masterHasDraft)) return true;
    return rows(state.attributes).some(row =>
        hasText(row?.label) || hasText(row?.key) ||
        rows(row?.values).some(value => hasText(value?.value_id) || hasText(value?.value_ref) || hasText(value?.code)) ||
        rows(row?._master_composer).some(masterHasDraft) ||
        rows(row?._selected_value_ids).length > 0 || rows(row?._selected_pending).length > 0);
}

function installDraftGuard(hasDraft, message) {
    const unloadListener = event => { if (hasDraft()) { event.preventDefault(); event.returnValue = ''; } };
    const navigateListener = event => { if (!event.defaultPrevented && hasDraft() && !window.confirm(message)) event.preventDefault(); };
    window.addEventListener('beforeunload', unloadListener);
    document.addEventListener('livewire:navigate', navigateListener);
    return () => {
        window.removeEventListener('beforeunload', unloadListener);
        document.removeEventListener('livewire:navigate', navigateListener);
    };
}

export function catalogDraftGuard(config, isDirty) {
    let removeGuard;
    return {
        init() { removeGuard = installDraftGuard(() => this.hasDraft(), config.discardMessage); },
        hasDraft() { return isDirty(this.$wire.$get(config.path) ?? {}); },
        destroy() { removeGuard?.(); removeGuard = null; },
    };
}

export function masterValueBatch(config) {
    let profile, unsubscribe, unsubscribeAction, pending = null, intentSequence = 0, destroyed = false, removeDraftGuard;
    return {
        showTags: false,
        showSpecification: false,
        init() {
            profile = browserProfile(config.user, `catalog-create:${config.context}:master-columns`);
            const saved = masterBatchColumns(profile.read());
            this.showTags = saved.tags;
            this.showSpecification = saved.specification;
            const capture = () => { this.syncColumns(); profile.save({ tags: this.showTags, specification: this.showSpecification }); };
            this.$watch('showTags', capture);
            this.$watch('showSpecification', capture);
            unsubscribeAction = window.Livewire.interceptAction(({ action, onSuccess, onFinish }) => {
                const intent = pending;
                if (!intent || action.component.id !== this.$wire.$id || action.name !== '$commit' || action.origin?.directive?.expression !== intent.sourcePath || this.rowReference(action.origin?.el) !== intent.sourceRef) return;
                const token = intent.token;
                intent.actionObserved = true;
                let succeeded = false;
                onSuccess(() => {
                    if (pending?.token !== token || !pending.morphed) return;
                    succeeded = true;
                    // Native action success follows morph; finish runs before this microtask.
                    queueMicrotask(() => this.advance(token));
                });
                onFinish(() => { if (!succeeded && pending?.token === token) pending = null; });
            });
            unsubscribe = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                if (message.component.id !== this.$wire.$id) return;
                const intent = pending;
                const matchesOrigin = actions => [...(actions ?? [])].some(action =>
                    (action.name === '$commit' && action.origin?.directive?.expression === intent?.sourcePath && this.rowReference(action.origin?.el) === intent?.sourceRef) || matchesOrigin(action.squashedActions));
                const token = intent?.actionObserved && matchesOrigin(message.actions) ? intent.token : null;
                onSuccess(({ onMorphed }) => onMorphed(() => {
                    if (destroyed) return;
                    if (token !== null && pending?.token === token) pending.morphed = true;
                    this.syncColumns();
                    this.revealErrors();
                }));
            });
            if (!this.$el.closest?.('[data-catalog-draft-guard]')) {
                removeDraftGuard = installDraftGuard(() => this.hasDraft(), config.discardMessage);
            }
            this.$nextTick(() => { this.syncColumns(); this.revealErrors(); });
        },
        syncColumns() { this.$el.dataset.showTags = String(this.showTags); this.$el.dataset.showSpecification = String(this.showSpecification); },
        resetColumns() { this.showTags = false; this.showSpecification = false; },
        hasDraft() {
            return rows(this.$wire.$get(config.path)).some(masterHasDraft);
        },
        rowReference(input) {
            if (!input) return null;
            const path = inputPath(input);
            if (typeof path !== 'string' || !path.includes('.')) return null;
            const ref = this.$wire.$get(path.slice(0, path.lastIndexOf('.')) + '.ref');
            return typeof ref === 'string' && ref !== '' ? ref : null;
        },
        inputFor(field, rowRef = null) {
            return [...this.$el.querySelectorAll('input')].find(input => inputPath(input) === field && (!rowRef || this.rowReference(input) === rowRef));
        },
        focus(field, rowRef = null) { this.inputFor(field, rowRef)?.focus(); },
        advance(token) {
            if (destroyed || pending?.token !== token) return;
            const intent = pending;
            pending = null;
            if (!intent.sourceRef || !this.inputFor(intent.sourcePath, intent.sourceRef)) return;
            if (intent.append) this.addRow();
            else this.focus(intent.field, intent.rowRef);
        },
        addRow() { this.$el.querySelector('[data-master-batch-add]')?.click(); },
        appended(detail) {
            if (detail.path !== config.path || detail.livewireId !== this.$wire.$id) return;
            this.$nextTick(() => this.focus(detail.field, detail.rowRef));
        },
        keydown(event) {
            if (!event.target.closest('[data-batch-field="label"]')) return;
            const direction = nameEntryDirection(event);
            if (!direction) return;
            event.preventDefault();
            const inputs = [...this.$el.querySelectorAll('[data-batch-field="label"] input')];
            const index = inputs.indexOf(event.target);
            if (index < 0) return;
            const next = inputs[index + direction];
            const target = next ? { field: inputPath(next), rowRef: this.rowReference(next) } : direction === 1 ? { append: true } : null;
            if (!target || !this.rowReference(event.target) || (next && !target.rowRef)) return;
            const token = ++intentSequence;
            const intent = { ...target, token, sourcePath: inputPath(event.target), sourceRef: this.rowReference(event.target), actionObserved: false, morphed: false };
            pending = intent;
            event.target.blur();
            // Native .live.blur schedules its model action in the blur microtask.
            queueMicrotask(() => { if (pending?.token === token && !pending.actionObserved) this.advance(token); });
        },
        revealErrors() {
            const fields = [...this.$el.querySelectorAll('[data-batch-field]')];
            let first;
            for (const field of fields) {
                if (!field.querySelector('[data-validation-error]')) continue;
                if (field.dataset.batchField === 'tags') this.showTags = true;
                if (field.dataset.batchField === 'description') this.showSpecification = true;
                first ??= field;
            }
            if (first) this.$nextTick(() => first.querySelector('input, textarea, select')?.focus());
        },
        destroy() {
            destroyed = true;
            pending = null;
            unsubscribe?.();
            unsubscribeAction?.();
            removeDraftGuard?.();
            removeDraftGuard = null;
        },
    };
}
