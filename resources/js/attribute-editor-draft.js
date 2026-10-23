const masterFields = ['ref', 'label', 'semantic_id', 'tags', 'description'];
// Match PHP trim() in CreateMasterValues::isPopulated, preserving Unicode spaces.
const populated = row => ['label', 'semantic_id', 'tags', 'description'].some(field =>
    (Array.isArray(row[field]) ? row[field] : [row[field]]).some(value => value != null && (typeof value !== 'string' || /[^ \t\n\r\0\v]/.test(value))));
const projectRows = (rows, fields, onlyPopulated = false) => Object.entries(rows ?? {})
    .filter(([, row]) => !onlyPopulated || populated(row))
    .map(([key, row]) => [key, Object.fromEntries(fields.map(field => [field, row[field] ?? (field === 'tags' ? [] : null)]))]);

export function attributeEditorDraft(state = {}) {
    return {
        label: state.label ?? null,
        key: state.key ?? null,
        pending_options: projectRows(state.pending_options, ['ref', 'value_id', 'value_ref', 'code']),
        master_values: projectRows(state.master_values, masterFields, true),
        _master_composer: projectRows(state._master_composer, masterFields, true),
        _selected_value_ids: state._selected_value_ids ?? [],
        _selected_pending: state._selected_pending ?? [],
    };
}
