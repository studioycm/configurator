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
const fields = {
    overview: ['name', 'description', 'context_schema', 'hidden_context_options'],
    attribute: ['label_override', 'input_type', 'help_text', 'default_configurator_option_id'],
    value: valueFields,
    rule: ['id', 'kind', 'priority', 'label', 'is_active', 'driver_configurator_attribute_id', 'target_configurator_attribute_id', 'conditions', 'condition_blocks', 'effects', 'sets'],
};
const childFields = ['id', 'type', 'data', 'operator', 'conditions', 'source_kind', 'source_configurator_attribute_id', 'property_key', 'context_dimension', 'operand', 'operand_text', 'operand_list', 'option_ids', 'kind', 'target_configurator_attribute_id', 'target_scope', 'display_value', 'label', 'source_option_ids', 'target_option_ids', 'disallowed_target_behavior', 'sort_order'];
const has = (value, key) => Object.hasOwn(value ?? {}, key);
const pick = (value, keys) => Object.fromEntries(keys.filter(key => has(value, key)).map(key => [key, value[key]]));
const blank = value => value == null || (typeof value === 'string' && /^[\x00\x09-\x0B\x0D\x20]*$/.test(value)) || (Array.isArray(value) && value.length === 0);

function rawRuleChildren(value) {
    if (Array.isArray(value)) return value.map(rawRuleChildren);
    if (value === null || typeof value !== 'object') return value;
    return Object.fromEntries(Object.entries(value)
        .filter(([key]) => childFields.includes(key) || /^(0|[1-9][0-9]*)$/.test(key))
        .map(([key, child]) => [key, rawRuleChildren(key === 'conditions' && child && typeof child === 'object' ? Object.values(child) : child)]));
}

function predicateToDraft(condition) {
    const { operand, ...rest } = condition;
    return { ...rest, operand_text: typeof operand === 'string' ? operand : null, operand_list: operand && typeof operand === 'object' ? operand : [] };
}

function canonicalValue(value, field = '', literal = false) {
    literal ||= ['operand', 'operand_text', 'operand_list', 'context_schema', 'hidden_context_options'].includes(field);
    if (Array.isArray(value)) {
        const ids = !literal && ['option_ids', 'source_option_ids', 'target_option_ids'].includes(field);
        return value.map((child, key) => canonicalValue(child, ids ? 'id' : String(key), literal));
    }
    if (value !== null && typeof value === 'object') {
        return Object.fromEntries(Object.keys(value).sort().map(key => [key, canonicalValue(value[key], key, literal)]));
    }
    if (!literal && (field === 'id' || field.endsWith('_id') || ['priority', 'sort_order'].includes(field))
        && ((typeof value === 'number' && Number.isInteger(value)) || (typeof value === 'string' && /^[0-9]+$/.test(value)))) {
        return String(value).replace(/^0+/, '') || '0';
    }
    if (!literal && ['is_active', 'hidden_by_default', 'disabled_by_default'].includes(field) && [true, false, 0, 1, '0', '1'].includes(value)) {
        return value === true || value === 1 || value === '1';
    }
    return value;
}

export function editorComparisonState(type, input = {}) {
    let state = pick(input, fields[type] ?? []);
    if (type === 'rule') {
        if (has(state, 'conditions') && state.conditions && typeof state.conditions === 'object' && !has(state, 'condition_blocks')) {
            state.condition_blocks = Object.values(state.conditions).map(condition => has(condition, 'conditions')
                ? { type: 'group', data: { ...condition, conditions: Object.values(condition.conditions).map(predicateToDraft) } }
                : { type: 'predicate', data: predicateToDraft(condition) });
            delete state.conditions;
        }
        for (const field of ['condition_blocks', 'conditions', 'effects', 'sets']) {
            if (state[field] && typeof state[field] === 'object') state[field] = rawRuleChildren(Object.values(state[field]));
        }
        if (Array.isArray(state.sets)) state.sets = state.sets.map(set => {
            const { sort_order, ...rest } = set;
            return { ...rest, ...(rest.label === '' ? { label: null } : {}), ...(!has(rest, 'disallowed_target_behavior') ? { disallowed_target_behavior: 'Disable' } : {}) };
        });
        if (Array.isArray(state.effects)) state.effects = state.effects.map(effect => effect.display_value === '' ? { ...effect, display_value: null } : effect);
    } else {
        if (type === 'value' && (has(state, 'subtitle_mode') || has(state, 'display_value_override'))) {
            const mode = has(state, 'subtitle_mode') ? state.subtitle_mode : (blank(state.display_value_override) ? 'none' : 'custom');
            if (!['none', 'custom', 'master'].includes(mode) || (mode === 'custom' && (typeof state.display_value_override !== 'string' || blank(state.display_value_override)))) {
                return canonicalValue(state);
            }
            state = { ...state, subtitle_mode: mode, display_value_override: mode === 'custom' ? state.display_value_override : null };
        }
        for (const field of ['label_override', 'help_text', 'hint', 'description']) {
            if (has(state, field) && blank(state[field])) state[field] = null;
        }
        if (state.context_schema) {
            state.context_schema = { ...state.context_schema };
            for (const dimension of ['territory', 'application']) {
                if (state.context_schema[dimension] && typeof state.context_schema[dimension] === 'object') state.context_schema[dimension] = Object.values(state.context_schema[dimension]);
            }
        }
    }
    return canonicalValue(state);
}

export const sameEditorMeaning = (type, left, right) => JSON.stringify(editorComparisonState(type, left)) === JSON.stringify(editorComparisonState(type, right));
const valueChanged = (state, original) => !sameEditorMeaning('value', state, original);

export function configuratorDrafts(workspace) {
    return {
        hasDraft() {
            const key = workspace === 'rules'
                ? (this.$wire.editorKind == null ? null : (this.$wire.selectedRuleId == null ? `new:${this.$wire.editorKind}` : `rule:${this.$wire.selectedRuleId}`))
                : (this.$wire.selectedAttributeId == null ? null : `attribute:${this.$wire.selectedAttributeId}`);
            if (key === null) return false;
            const original = this.$wire.previewOriginals?.[key] ?? this.$wire.editorData;
            return !sameEditorMeaning(workspace === 'rules' ? 'rule' : 'attribute', this.$wire.editorData, original);
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
