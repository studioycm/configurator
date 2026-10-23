import { attributeDraftIsDirty, catalogDraftGuard } from './master-value-batch.js';

const entries = value => value && typeof value === 'object' ? Object.values(value) : [];
const text = value => value != null && String(value).trim() !== '';
const composerHasDraft = value => entries(value).some(row => ['label', 'semantic_id', 'description', 'tags'].some(key => (Array.isArray(row?.[key]) ? row[key] : [row?.[key]]).some(text)));

export function configuratorCreationIsDirty(state = {}, kind = 'attributes') {
    if (kind === 'attributes') return attributeDraftIsDirty(state) || entries(state.existing_attribute_ids).length > 0 || entries(state.reviews).length > 0 || entries(state._review_stash).length > 0;
    return entries(state.existing_option_ids).length > 0 || entries(state.new_values).length > 0 || composerHasDraft(state.master_values) || composerHasDraft(state._master_composer) || entries(state._selected_value_ids).length > 0 || entries(state._selected_pending).length > 0;
}

export const configuratorCreationGuard = config => catalogDraftGuard(config, state => configuratorCreationIsDirty(state, config.kind));
