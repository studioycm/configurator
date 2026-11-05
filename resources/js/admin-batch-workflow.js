const stableState = value => JSON.stringify(value, function (_key, item) {
    return item && !Array.isArray(item) && typeof item === 'object'
        ? Object.fromEntries(Object.keys(item).sort().map(key => [key, item[key]])) : item;
});

export function workspaceSelectionScope() {
    let key = '', generation = 0, timer = null, disposed = false;
    return {
        selectionScope: null, selectionScopeBusy: false, selectionScopeFailed: false,
        refreshSelectionScope(ids, criteria) {
            const next = JSON.stringify([ids.map(String).sort(), criteria]);
            if (next === key || disposed) return;
            key = next; const operation = ++generation;
            clearTimeout(timer); this.selectionScope = null; this.selectionScopeFailed = false;
            if (!ids.length) { this.selectionScopeBusy = false; return; }
            this.selectionScopeBusy = true;
            timer = setTimeout(async () => {
                try {
                    const reply = await this.$wire.workspaceSelectionScope(ids);
                    if (disposed || operation !== generation) return;
                    if (!reply || !Number.isInteger(reply.total) || !Number.isInteger(reply.inside) || !Number.isInteger(reply.outside)
                        || reply.total !== ids.length || reply.inside < 0 || reply.outside < 0 || reply.inside + reply.outside !== reply.total) throw new Error('Unavailable selection scope');
                    this.selectionScope = reply;
                } catch {
                    if (!disposed && operation === generation) this.selectionScopeFailed = true;
                } finally {
                    if (!disposed && operation === generation) this.selectionScopeBusy = false;
                }
            }, 120);
        },
        destroy() { disposed = true; generation++; clearTimeout(timer); },
    };
}

export function batchPreviewGuard(config) {
    let revision = null;
    return {
        batchIsOutdated: false,
        init() {
            revision = this.$wire.batchPreview?.ui_revision;
            this.$watch('$wire.batchPreview.ui_revision', value => {
                revision = value;
                const prepared = this.$wire.batchPreview;
                this.batchIsOutdated = !!value && (stableState(this.$wire.mountedActions?.[config.index]?.data) !== stableState(prepared.form_state)
                    || stableState([...this.$wire.selectedTableRecords].map(String).sort()) !== stableState([...(prepared.selected_ids ?? [])].map(String).sort()));
            });
            this.$watch('$wire.batchPreviewStale', value => { if (value) this.batchIsOutdated = true; });
            this.$watch(() => JSON.stringify([this.$wire.mountedActions?.[config.index]?.data, [...this.$wire.selectedTableRecords].map(String).sort()]), () => {
                if (revision && revision === this.$wire.batchPreview?.ui_revision) this.batchIsOutdated = true;
            });
        },
        captureBatchEdit(event) {
            if (this.$wire.batchPreview?.token && event.target?.matches('input, textarea, select, [contenteditable="true"]')) this.batchIsOutdated = true;
        },
    };
}
