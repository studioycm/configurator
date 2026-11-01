import { interceptCatalogImageMessage } from './catalog-image-editor.js';

export const workspaceImageContext = wire => ({ session: wire.imageEditorSession, target: wire.imageTarget,
    record: wire.imageRecordId, baseline: wire.catalogMainImageOriginal });
const draftMeaning = wire => JSON.stringify([workspaceImageContext(wire), wire.data,
    wire.catalogMainImageUploadBinding?.nonce, wire.catalogMainImageSourceProgress?.source_id,
    wire.catalogMainImageSourceProgress?.scan_version, wire.catalogMainImageRecoverySourceId]);

/** The workspace confirms only image state; native resource Save/Create keep their stricter controller. */
export function createWorkspaceImageController({ wire, onState = () => {}, onLock = () => {}, canRun = () => true }) {
    let saving = false, unknown = false, message = '', destroyed = false, attempt = null;
    const state = () => ({ saving, unknown, message });
    const changed = () => { if (!destroyed) onState(state()); };
    const methods = ['prepareWorkspaceImageSave', 'saveWorkspaceImage', 'reconcileWorkspaceImageSave', 'resolveWorkspaceImageTransition', 'catalogMainImageRevert'];
    const remove = methods.map(method => interceptCatalogImageMessage(wire, method, ({ cancel, onSuccess }) => {
        const active = attempt, meaning = draftMeaning(wire);
        onSuccess(() => { if (destroyed || active !== attempt || meaning !== draftMeaning(wire)) cancel(); });
    }));
    async function save(pendingToken = null) {
        if (saving || unknown || destroyed) return state();
        if (!canRun()) { message = 'Wait for the image upload or scan to finish, or stop it first.'; changed(); return state(); }
        attempt = {}; saving = true; message = 'Preparing image…'; onLock(true); changed();
        const expected = workspaceImageContext(wire), meaning = draftMeaning(wire);
        let finalRequest = false;
        try {
            const prepared = await wire.prepareWorkspaceImageSave(expected, pendingToken);
            if (destroyed || meaning !== draftMeaning(wire)) return state();
            if (prepared?.outcome !== 'ready' || !prepared.receipt) { message = prepared?.message ?? 'Preparation did not complete. Your draft is retained.'; return state(); }
            message = 'Saving image…'; changed(); finalRequest = true;
            const result = await wire.saveWorkspaceImage(expected, pendingToken);
            if (destroyed) return state();
            if (result?.outcome !== 'committed') { unknown = true; message = 'The Save response is unknown. Check the saved image before retrying.'; }
            else message = result.message ?? 'Image saved.';
        } catch {
            if (destroyed) return state();
            if (finalRequest) {
                unknown = true; message = 'The Save response is unknown. Check the saved image before retrying.';
            } else message = 'Preparation did not complete. Your draft is retained.';
        } finally { attempt = null; saving = false; onLock(false); changed(); }
        return state();
    }
    async function reconcile() {
        if (!unknown || saving || destroyed) return state();
        attempt = {}; saving = true; onLock(true); changed();
        try {
            const result = await wire.reconcileWorkspaceImageSave(workspaceImageContext(wire), wire.imagePendingTransition?.token ?? null);
            if (destroyed) return state();
            message = result?.message ?? 'The image state is still unknown. Your draft is retained.';
            if (['goal_matches', 'baseline_matches'].includes(result?.outcome)) unknown = false;
        } catch { if (!destroyed) message = 'The image state is still unknown. Your draft is retained.'; }
        finally { attempt = null; saving = false; onLock(false); changed(); }
        return state();
    }
    async function choose(choice) {
        if (saving || destroyed || !['stay', 'discard'].includes(choice)) return state();
        attempt = {}; saving = true; onLock(true); changed();
        try {
            if (wire.imagePendingTransition) await wire.resolveWorkspaceImageTransition(choice, wire.imagePendingTransition.token, workspaceImageContext(wire));
            else if (choice === 'discard') await wire.catalogMainImageRevert();
            if (!destroyed) { if (choice === 'discard') unknown = false; message = ''; }
        } catch { if (!destroyed) message = 'The draft changed. Review the current image before continuing.'; }
        finally { attempt = null; saving = false; onLock(false); changed(); }
        return state();
    }
    return { state, save, reconcile, choose, destroy() { destroyed = true; attempt = null; remove.forEach(fn => fn?.()); onLock(false); } };
}

export function catalogImagesEditor() {
    let controller, workspace, previousInert = false;
    const cleanups = [];
    return {
        saving: false, unknown: false, message: '',
        init() {
            const wire = this.$wire;
            workspace = this.$el.closest('.catalog-images-workspace');
            controller = createWorkspaceImageController({ wire, onState: state => Object.assign(this, state),
                canRun: () => {
                    const field = this.$el.querySelector('[data-catalog-image-editor]');
                    const native = field && globalThis.Alpine?.$data(field);
                    return !native?.uploading && !native?.scanning;
                },
                onLock: active => {
                    if (!workspace) return;
                    if (active) { previousInert = workspace.inert; workspace.inert = true; workspace.setAttribute('aria-busy', 'true'); }
                    else { workspace.inert = previousInert; workspace.removeAttribute('aria-busy'); }
                },
            });
            const dirty = () => this.saving || this.unknown || !!wire.catalogMainImageUploadBinding
                || (wire.data?.image_intent?.operation ?? 'keep') !== 'keep' || Object.keys(wire.data?.image_upload ?? {}).length > 0;
            const listen = (target, name, callback) => { target?.addEventListener(name, callback); cleanups.push(() => target?.removeEventListener(name, callback)); };
            listen(globalThis.document, 'livewire:navigate', event => {
                if (!dirty()) return;
                event.preventDefault(); this.message = 'Save or discard the image change before leaving Images.';
            });
            listen(globalThis.window, 'beforeunload', event => { if (dirty()) { event.preventDefault(); event.returnValue = ''; } });
            listen(this.$el, 'keydown', event => {
                if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') { event.preventDefault(); void controller.save(wire.imagePendingTransition?.token ?? null); }
            });
        },
        save(token = null) { return controller.save(token); },
        reconcile() { return controller.reconcile(); },
        choose(choice) { return controller.choose(choice); },
        destroy() { controller?.destroy(); cleanups.splice(0).forEach(fn => fn()); },
    };
}
