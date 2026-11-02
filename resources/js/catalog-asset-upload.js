/** Owns native Files and their stable entry positions; the existing foreground owns all scheduling. */
export function createCatalogAssetUpload(files, adapters, { onChange = () => {}, limits = {}, mayContinue = () => true } = {}) {
    const caps = { files: 2000, bytes: 524288000, imageBytes: 3145728, ...limits };
    const skipped = [], metadata = [];
    let queue = [], issued = null, progress = null, index = 0, needsStatus = false, unresolved = false, done = false, destroyed = false;
    for (const file of Array.from(files)) {
        const path = file.webkitRelativePath || file.name;
        if (!/\.(jpe?g|png|webp)$/iu.test(file.name) || (file.type && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) {
            skipped.push(path);
            continue;
        }
        if (!Number.isSafeInteger(file.size) || file.size < 0 || file.size > caps.imageBytes) throw new Error('Each selected image must be at most 3 MiB.');
        queue.push(file);
        metadata.push({ name: file.name, relative_path: path, bytes: file.size });
    }
    const bytes = metadata.reduce((total, file) => total + file.bytes, 0);
    if (!queue.length) throw new Error('Choose at least one JPEG, PNG or WebP image.');
    if (queue.length > caps.files || bytes > caps.bytes) throw new Error('Choose at most 2000 images and 500 MiB in one source.');
    const state = () => ({ total: metadata.length, bytes, skipped: [...skipped], completed: index, source_id: issued?.source_id ?? null,
        progress, needsStatus, unresolved, done });
    const changed = () => { if (!destroyed) onChange(state()); };
    function reconcile(result, entryId = null) {
        if (!result || result.source_id !== issued.source_id) throw new Error('Unconfirmed upload source.');
        if (result.recovery === 'unresolved') {
            unresolved = true;
        } else if (entryId !== null) {
            if (result.current_entry?.entry_id !== entryId) throw new Error('Unconfirmed upload entry.');
            if (result.current_entry.committed) index++;
        }
        progress = result;
        done = !!result.uploads_sealed;
        changed();
        return result;
    }
    return {
        state,
        async advance() {
            if (destroyed || done || !mayContinue()) return progress;
            if (needsStatus) throw new Error('Refresh upload status before explicitly continuing.');
            if (unresolved) throw new Error('Preserve this source and choose a fresh selection.');
            if (!issued) {
                issued = await adapters.issue(metadata, 'Browser image upload');
                if (!issued?.source_id || !issued.intake_token || issued.entry_ids?.length !== metadata.length) throw new Error('Unconfirmed intake identity.');
                if (destroyed) return null;
                changed();
                if (!mayContinue()) return progress;
            }
            try {
                if (!progress) {
                    const started = await adapters.begin(issued.intake_token);
                    if (destroyed) return null;
                    reconcile(started);
                    if (!mayContinue()) return progress;
                }
                if (index < queue.length) {
                    const cap = await adapters.entry(index);
                    if (destroyed) return null;
                    if (!mayContinue()) return progress;
                    const entryId = issued.entry_ids[index];
                    if (cap?.entry_id !== entryId) throw new Error('Unconfirmed intake entry capability.');
                    const result = await adapters.transfer(cap, queue[index]);
                    if (destroyed) return null;
                    return reconcile(result, entryId);
                }
                const result = await adapters.finish(progress.revision);
                if (destroyed) return null;
                return reconcile(result);
            } catch (error) {
                if (!destroyed && error.status !== 429) needsStatus = true;
                changed();
                throw error;
            }
        },
        async refresh() {
            if (destroyed || !issued) return null;
            const entryId = progress && index < issued.entry_ids.length ? issued.entry_ids[index] : null;
            const result = await adapters.status(entryId);
            if (destroyed) return null;
            // A lost Begin may not have synchronized its Source into Livewire. Explicit Continue reuses its issued identity.
            if (progress) reconcile(result, entryId);
            needsStatus = false;
            changed();
            return result;
        },
        destroy() { destroyed = true; queue = []; },
    };
}

/** Same-origin batch endpoint; a rejected throttle response is known not to have entered the operation. */
export async function transferCatalogAssetUpload(capability, file, { launchToken, csrf, fetcher = globalThis.fetch } = {}) {
    const body = new FormData();
    body.append('launch_token', launchToken);
    if (file) body.append('files[0]', file, file.name);
    const response = await fetcher(capability.url, { method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body });
    if (!response.ok) {
        const error = Object.assign(new Error('The upload response was not accepted.'), { status: response.status });
        if (response.status === 429) {
            const retry = response.headers.get('Retry-After');
            error.retryAfterMs = /^\d+$/.test(retry ?? '') ? Number(retry) * 1000 : Math.max(0, Date.parse(retry) - Date.now());
            if (!Number.isFinite(error.retryAfterMs)) error.retryAfterMs = 60000;
        }
        throw error;
    }
    return response.json();
}
