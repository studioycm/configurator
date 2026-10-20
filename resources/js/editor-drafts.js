export function editorDrafts({ draftSelector = '[data-draft-dirty=true]' } = {}) {
    return {
        confirmDiscard: false,
        discardRecord: '',
        pendingTransition: null,
        pendingCloseId: null,
        allowTransition: false,
        hasDraft() { return Boolean(this.$el.querySelector(draftSelector)); },
        requestDiscard(event, target, closeId = null, record = null) {
            if (this.allowTransition) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            this.pendingTransition = target;
            this.pendingCloseId = closeId;
            this.discardRecord = record ?? this.$el.querySelector(draftSelector)?.dataset.editorRecord ?? '';
            this.confirmDiscard = true;
            this.$nextTick(() => {
                this.$refs.discardAlert?.scrollIntoView({ block: 'nearest' });
                this.$refs.discardAlert?.querySelector('button')?.focus({ preventScroll: true });
            });
        },
        keepEditing() {
            this.confirmDiscard = false;
            this.pendingTransition = null;
            this.pendingCloseId = null;
        },
        discardChanges() {
            const target = this.pendingTransition;
            const closeId = this.pendingCloseId;
            this.keepEditing();
            this.allowTransition = true;
            try {
                if (closeId) this.$dispatch('close-modal', { id: closeId });
                else target?.click();
            } finally {
                this.$nextTick(() => { this.allowTransition = false; });
            }
        },
    };
}

export function configuratorDrafts(workspace) {
    return {
        hasDraft() {
            const key = workspace === 'rules'
                ? (this.$wire.editorKind == null ? null : (this.$wire.selectedRuleId == null ? `new:${this.$wire.editorKind}` : `rule:${this.$wire.selectedRuleId}`))
                : (this.$wire.selectedAttributeId == null ? null : `attribute:${this.$wire.selectedAttributeId}`);
            if (key === null) return false;
            const original = this.$wire.previewOriginals?.[key] ?? this.$wire.editorData;
            return JSON.stringify(this.$wire.editorData) !== JSON.stringify(original);
        },
        hasCurrentOptionDraft() {
            if (this.$wire.selectedOptionId == null) return false;
            const original = this.$wire.previewOriginals?.[`value:${this.$wire.selectedOptionId}`] ?? this.$wire.optionEditorData;
            return JSON.stringify(this.$wire.optionEditorData) !== JSON.stringify(original);
        },
        hasOptionDraft() {
            return this.hasCurrentOptionDraft() || Object.values(this.$wire.optionEditorDrafts || {}).some(rows => Object.keys(rows).length > 0);
        },
        hasPendingDrafts() {
            return this.hasDraft() || Object.keys(this.$wire.editorDrafts || {}).length > 0 || this.hasOptionDraft();
        },
    };
}
