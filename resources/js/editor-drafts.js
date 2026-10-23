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

const valueFields = ['label_override', 'subtitle_mode', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default'];
const valueState = state => Object.fromEntries(valueFields.filter(field => Object.hasOwn(state ?? {}, field)).map(field => [field, state[field]]));
const valueChanged = (state, original) => JSON.stringify(valueState(state)) !== JSON.stringify(valueState(original));

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
            return valueChanged(this.$wire.optionEditorData, original);
        },
        hasOptionDraft() {
            return this.hasCurrentOptionDraft() || Object.values(this.$wire.optionEditorDrafts || {}).some(rows => Object.entries(rows).some(([id, state]) => valueChanged(state, this.$wire.previewOriginals?.[`value:${id}`] ?? state)));
        },
        hasPendingDrafts() {
            return this.hasDraft() || Object.keys(this.$wire.editorDrafts || {}).length > 0 || this.hasOptionDraft();
        },
    };
}
