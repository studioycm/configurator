export function interpolate(template, values) {
    return template.replace(/:([a-zA-Z_][a-zA-Z_0-9]*)/g, (token, key) =>
        Object.hasOwn(values, key) ? String(values[key]) : token);
}

export function requireLabels(labels, keys) {
    if (!labels || typeof labels !== 'object' || Array.isArray(labels)
        || keys.some(key => !Object.hasOwn(labels, key) || typeof labels[key] !== 'string' || labels[key].trim() === '')) {
        throw new Error('Missing or invalid UI labels.');
    }
    return labels;
}
