const record = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const own = (object, key) => Object.hasOwn(object, key);
const emptyState = () => ({version: 1, filters: {}, subGroupId: null, precedence: []});

export function createFilterEngine(snapshot) {
    if (snapshot?.schema !== 1) throw new Error('Unsupported catalog snapshot. Reload this page.');
    const fields = new Map(snapshot.fields.map(field => [field.key, field]));
    const presets = new Map(snapshot.presets.map(preset => [preset.id, preset]));
    const codes = new Map(snapshot.fields.map(field => [field.key, new Map(field.options.map(option => [option.value, option.code]))]));
    const matches = (row, constraints) => constraints.every(c => c.codes.includes(row[c.column + 1]));
    const constraint = (token, state) => {
        if (token.startsWith('filter:')) {
            const key = token.slice(7);
            return {column: fields.get(key).column, codes: [codes.get(key).get(state.filters[key])]};
        }
        const preset = presets.get(state.subGroupId);
        return {column: preset.column, codes: preset.codes};
    };

    function normalize(input = {}) {
        let adjusted = !record(input) || Boolean(input?.adjusted);
        let raw = record(input) ? input : {};
        const notices = [];
        if (raw.version !== undefined && raw.version !== 1 && raw.version !== '1') {
            raw = {};
            adjusted = true;
            notices.push('This catalog link used an unsupported version and was reset.');
        }
        const state = emptyState();
        if (raw.filters !== undefined && !record(raw.filters)) adjusted = true;
        if (record(raw.filters)) {
            const entries = Object.entries(raw.filters);
            if (entries.length > fields.size) adjusted = true;
            for (const [key, value] of entries.slice(0, fields.size + 1)) {
                if (typeof value === 'string' && codes.get(key)?.has(value)) state.filters[key] = value;
                else adjusted = true;
            }
        }
        if (raw.subGroupId !== undefined && raw.subGroupId !== null && raw.subGroupId !== '') {
            const id = typeof raw.subGroupId === 'string' ? raw.subGroupId : Number.isSafeInteger(raw.subGroupId) ? String(raw.subGroupId) : '';
            if (presets.has(id)) state.subGroupId = id;
            else adjusted = true;
        }
        const fallback = state.subGroupId === null ? [] : [`subgroup:${state.subGroupId}`];
        for (const field of fields.values()) if (own(state.filters, field.key)) fallback.push(`filter:${field.key}`);
        const order = raw.precedence;
        if (Array.isArray(order) && order.length === fallback.length && new Set(order).size === order.length && order.every(token => typeof token === 'string' && fallback.includes(token))) {
            state.precedence = [...order];
        } else {
            state.precedence = fallback;
            if (Object.keys(raw).length > 0 && fallback.length > 0) adjusted = true;
        }
        const preset = presets.get(state.subGroupId);
        if (preset?.values.length === 1 && own(state.filters, preset.key)) {
            delete state.filters[preset.key];
            state.precedence = state.precedence.filter(token => token !== `filter:${preset.key}`);
            notices.push(`The preset now controls ${fields.get(preset.key)?.label ?? preset.key}.`);
        }
        const accepted = [];
        const kept = [];
        for (const token of [...state.precedence].reverse()) {
            const proposed = [...accepted, constraint(token, state)];
            if (snapshot.rows.some(row => matches(row, proposed))) {
                accepted.push(proposed.at(-1));
                kept.push(token);
            } else if (token.startsWith('filter:')) {
                const key = token.slice(7);
                delete state.filters[key];
                notices.push(`Cleared ${fields.get(key).label} to keep your newer choice.`);
            } else {
                notices.push(`Cleared preset ${presets.get(state.subGroupId).label} to keep your newer choice.`);
                state.subGroupId = null;
            }
        }
        state.precedence = kept.reverse();
        // Stable field order gives one canonical URL and no duplicate no-op card requests.
        state.filters = Object.fromEntries([...fields.keys()].filter(key => own(state.filters, key)).map(key => [key, state.filters[key]]));
        if (adjusted) notices.push('Some catalog choices were adjusted to the current group settings.');
        return {state, notices, adjusted};
    }

    function change(current, action) {
        const raw = {version: 1, filters: {...current.filters}, subGroupId: current.subGroupId, precedence: [...current.precedence]};
        if (action.type === 'filter' && codes.get(action.key)?.has(action.value)) {
            const token = `filter:${action.key}`;
            raw.precedence = raw.precedence.filter(item => item !== token);
            if (raw.filters[action.key] === action.value) delete raw.filters[action.key];
            else { raw.filters[action.key] = action.value; raw.precedence.push(token); }
        } else if (action.type === 'preset' && (action.id === null || presets.has(action.id))) {
            if (raw.subGroupId === action.id) return {state: current, notices: [], adjusted: false, changed: false};
            raw.precedence = raw.precedence.filter(token => !token.startsWith('subgroup:'));
            raw.subGroupId = action.id;
            if (action.id !== null) raw.precedence.push(`subgroup:${action.id}`);
        } else if (action.type === 'clear' || action.type === 'reset') {
            raw.filters = {};
            if (action.type === 'reset') raw.subGroupId = null;
            raw.precedence = raw.subGroupId === null ? [] : [`subgroup:${raw.subGroupId}`];
        }
        const result = normalize(raw);
        return {...result, changed: JSON.stringify(result.state) !== JSON.stringify(current)};
    }

    function evaluate(state) {
        const preset = presets.get(state.subGroupId);
        const selected = Object.entries(state.filters).map(([key, value]) => ({key, column: fields.get(key).column, code: codes.get(key).get(value)}));
        const compatibility = Object.fromEntries([...fields.values()].map(field => [field.key, Array(snapshot.columns[field.column].values.length).fill(false)]));
        const ids = [];
        for (const row of snapshot.rows) {
            if (preset && !preset.codes.includes(row[preset.column + 1])) continue;
            let mismatches = 0;
            let mismatchKey;
            for (const choice of selected) {
                if (row[choice.column + 1] !== choice.code) {
                    mismatches++;
                    mismatchKey = choice.key;
                    if (mismatches > 1) break;
                }
            }
            if (mismatches > 1) continue;
            if (mismatches === 0) ids.push(row[0]);
            for (const field of fields.values()) {
                const code = row[field.column + 1];
                if (code >= 0 && (mismatches === 0 || field.key === mismatchKey)) compatibility[field.key][code] = true;
            }
        }
        return {total: ids.length, ids, compatibility, hiddenKey: preset?.values.length === 1 ? preset.key : null};
    }
    return {normalize, change, evaluate};
}

export function readDiscovery(input) {
    const url = new URL(input);
    const raw = {filters: {}, precedence: []};
    const seen = new Set();
    const positions = new Map();
    let examined = 0;
    let invalidOrder = false;
    for (const [key, value] of url.searchParams) {
        if (key !== 'd' && !key.startsWith('d[')) continue;
        if (++examined > 100) { raw.adjusted = true; break; }
        if (seen.has(key) && key !== 'd[precedence][]') { raw.adjusted = true; continue; }
        seen.add(key);
        if (key === 'd[version]') raw.version = value;
        else if (key === 'd[subGroupId]') raw.subGroupId = value || null;
        else if (/^d\[filters\]\[[A-Za-z][A-Za-z0-9_]*\]$/.test(key)) raw.filters[key.slice(11, -1)] = value;
        else if (/^d\[precedence\]\[(\d{1,2})?\]$/.test(key)) {
            const index = key.slice(14, -1);
            positions.set(index === '' ? positions.size : Number(index), value);
        } else raw.adjusted = true;
    }
    raw.precedence = [...positions].sort((a,b)=>a[0]-b[0]).map(([i,value],offset)=> { if (i !== offset) { raw.adjusted = true; invalidOrder = true; } return value; });
    if (invalidOrder) raw.precedence = [];
    return raw;
}

export function discoveryUrl(input, state) {
    const url = new URL(input);
    for (const key of [...url.searchParams.keys()]) {
        if (key === 'd' || key.startsWith('d[') || key === 'page' || key === 'perPage') url.searchParams.delete(key);
    }
    url.searchParams.set('d[version]', '1');
    for (const [key, value] of Object.entries(state.filters)) url.searchParams.set(`d[filters][${key}]`, value);
    if (state.subGroupId !== null) url.searchParams.set('d[subGroupId]', state.subGroupId);
    state.precedence.forEach((token,index)=>url.searchParams.set(`d[precedence][${index}]`,token));
    return url;
}
