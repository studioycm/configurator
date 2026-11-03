const boundary = (text, offset) => Number.isInteger(offset) && offset >= 0 && offset <= text.length && !(offset > 0 && offset < text.length && /[\uD800-\uDBFF]/.test(text[offset - 1]) && /[\uDC00-\uDFFF]/.test(text[offset]));

export function projectWritingSource(rawSource) {
    const rawToEditor = new Map([[0, 0]]), editorToRaw = new Map([[0, 0]]);
    let text = '', raw = 0;
    while (raw < rawSource.length) {
        const point = rawSource.codePointAt(raw);
        const size = point > 0xFFFF ? 2 : 1;
        if (rawSource[raw] === '\r') {
            raw += rawSource[raw + 1] === '\n' ? 2 : 1;
            text += '\n';
        } else {
            text += rawSource.slice(raw, raw + size);
            raw += size;
        }
        rawToEditor.set(raw, text.length);
        editorToRaw.set(text.length, raw);
    }
    return { text, rawOffset: offset => editorToRaw.get(offset) ?? null, editorOffset: offset => rawToEditor.get(offset) ?? null };
}

export function applyWritingEditorChange(previousRawSource, nextEditorText, context = {}) {
    const projection = projectWritingSource(previousRawSource), old = projection.text;
    if (nextEditorText === old) return { ok: true, source: previousRawSource, patch: null };
    let start = context.start, end = context.end;
    const knownRange = boundary(old, start) && boundary(old, end) && start <= end;
    if (knownRange && start === end && /^delete.*Backward$/.test(context.inputType ?? '')) start -= old.length - nextEditorText.length;
    else if (knownRange && start === end && /^delete.*Forward$/.test(context.inputType ?? '')) end += old.length - nextEditorText.length;
    else if (!knownRange) {
        let prefix = 0, suffix = 0;
        while (prefix < Math.min(old.length, nextEditorText.length) && old[prefix] === nextEditorText[prefix]) prefix++;
        while (suffix < Math.min(old.length, nextEditorText.length) && old[old.length - 1 - suffix] === nextEditorText[nextEditorText.length - 1 - suffix]) suffix++;
        if (prefix + suffix > Math.min(old.length, nextEditorText.length)) return { ok: false, type: 'ambiguous_edit', pendingText: nextEditorText };
        while (!boundary(old, prefix) || !boundary(nextEditorText, prefix)) prefix--;
        while (!boundary(old, old.length - suffix) || !boundary(nextEditorText, nextEditorText.length - suffix)) suffix--;
        start = prefix; end = old.length - suffix;
    }
    const insertedLength = nextEditorText.length - old.length + end - start;
    const inserted = nextEditorText.slice(start, start + insertedLength);
    if (!boundary(old, start) || !boundary(old, end) || insertedLength < 0 || old.slice(0, start) + inserted + old.slice(end) !== nextEditorText) return { ok: false, type: 'ambiguous_edit', pendingText: nextEditorText };
    const rawStart = projection.rawOffset(start), rawEnd = projection.rawOffset(end);
    if (rawStart === null || rawEnd === null) return { ok: false, type: 'ambiguous_edit', pendingText: nextEditorText };
    const patch = { start: rawStart, removed: previousRawSource.slice(rawStart, rawEnd), inserted };
    return { ok: true, source: previousRawSource.slice(0, rawStart) + inserted + previousRawSource.slice(rawEnd), patch };
}

export function writingHistory(initialSource) {
    let source = initialSource, undo = [], redo = [];
    const change = (entry, reverse) => {
        const patch = entry.patch, removed = reverse ? patch.inserted : patch.removed, inserted = reverse ? patch.removed : patch.inserted;
        if (source.slice(patch.start, patch.start + removed.length) !== removed) throw new Error('Writing history no longer matches this buffer.');
        source = source.slice(0, patch.start) + inserted + source.slice(patch.start + removed.length);
        return { source, selection: reverse ? entry.before : entry.after };
    };
    return {
        get source() { return source; },
        canUndo: false,
        canRedo: false,
        edit(text, context = {}, after = null) {
            const result = applyWritingEditorChange(source, text, context);
            if (!result.ok || !result.patch) return result;
            source = result.source;
            undo.push({ patch: result.patch, before: [context.start ?? 0, context.end ?? 0], after });
            redo = [];
            while (undo.length > 200 || undo.reduce((sum, entry) => sum + 2 * (entry.patch.removed.length + entry.patch.inserted.length), 0) > 8388608) undo.shift();
            this.canUndo = undo.length > 0; this.canRedo = redo.length > 0;
            return result;
        },
        undo() { if (!undo.length) return null; const entry = undo.pop(); const result = change(entry, true); redo.push(entry); this.canUndo = undo.length > 0; this.canRedo = redo.length > 0; return result; },
        redo() { if (!redo.length) return null; const entry = redo.pop(); const result = change(entry, false); undo.push(entry); this.canUndo = undo.length > 0; this.canRedo = redo.length > 0; return result; },
    };
}
