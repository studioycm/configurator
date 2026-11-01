const imageDraftIdentity = wire => JSON.stringify([wire.data, wire.catalogMainImageCreationSession,
    wire.catalogMainImageOriginal, wire.batchEditorRecordOriginal, wire.batchEditorGroupOriginal,
    wire.catalogMainImageSourceProgress?.source_id, wire.catalogMainImageSourceProgress?.scan_version,
    wire.catalogMainImageUploadBinding?.nonce, wire.catalogMainImageRecoverySourceId]);

/** Component-scoped, two-request Save/Create. Preparation is not commit proof. */
export function createCatalogImageSaveController({ wire, methods, onState = () => {}, onLock = () => {}, canRun = () => true, shouldPrepare = () => {
    const data = wire.data ?? {};
    return (data.image_intent?.operation ?? "keep") !== "keep" || Object.keys(data.image_upload ?? {}).length > 0 || !!wire.catalogMainImageUploadBinding;
}, draftIdentity = () => imageDraftIdentity(wire) }) {
    let saving = false;
    let unknown = false;
    let message = '';
    let replay = null;
    let destroyed = false;
    let attempt = null;
    const state = () => ({ saving, unknown, message });
    const changed = () => { if (!destroyed) onState(state()); };
    const remove = methods.map(method => wire.intercept(method, ({ action }) => {
        if (replay && action.name === replay.method && JSON.stringify(action.params) === JSON.stringify(replay.params)) {
            replay = null;
            return;
        }
        if (!shouldPrepare() && !saving && !unknown) return;
        const params = structuredClone(action.params ?? []);
        action.cancel();
        if (!saving && !unknown && !destroyed) void run(method, params);
    }));

    // Livewire resolves action promises after syncing state and processing effects.
    // Reject an obsolete image response at its component-scoped pre-sync boundary.
    const imageMethods = ['prepareImageSave', 'catalogMainImageChooseSource', 'catalogMainImageSourceStatus',
        'catalogMainImageAdvanceSource', 'catalogMainImageStageUpload', 'catalogMainImageSelectCandidate',
        'catalogMainImageRemove', 'catalogMainImageRevert', '_startUpload', '_finishUpload'];
    const removeMessages = [...imageMethods, ...methods].map(method => wire.interceptMessage(method, ({ message: livewireMessage, cancel, onSuccess }) => {
        const pending = attempt;
        if (!pending && !imageMethods.includes(method)) return;
        if (['_startUpload', '_finishUpload'].includes(method)
            && !livewireMessage.getActions().some(action => /^data\.image_upload\./.test(action.params?.[0] ?? ''))) return;
        const identity = draftIdentity();
        onSuccess(() => {
            if (destroyed || (pending && pending !== attempt) || identity !== draftIdentity()) cancel();
        });
    }));

    async function run(method, params = []) {
        if (saving || unknown || destroyed || !methods.includes(method)) return state();
        if (!canRun()) {
            message = 'Wait for the image upload or scan to stop before saving.';
            changed();
            return state();
        }
        attempt = {};
        saving = true;
        message = 'Preparing the image draft…';
        onLock(true);
        changed();
        let finalRequest = false;
        try {
            const preparation = await wire.prepareImageSave();
            message = preparation?.message ?? 'The draft could not be prepared. Review the errors and try again.';
            if (destroyed || preparation?.outcome !== 'ready' || typeof preparation.receipt !== 'string' || !preparation.receipt) return state();
            message = 'Saving…';
            changed();
            const preserved = structuredClone(params);
            replay = { method, params: preserved };
            finalRequest = true;
            const result = await wire.$call(method, ...preserved);
            if (result?.outcome === 'pending_commit') {
                unknown = true;
                message = 'The Save has not been confirmed. Check its status before continuing.';
            } else {
                message = '';
            }
        } catch (error) {
            if (finalRequest && [401, 403, 404, 419, 422].includes(Number(error?.status))) {
                message = 'The draft was not saved. Review the errors or your session before trying again.';
            } else if (finalRequest) {
                unknown = true;
                message = 'The Save response is unknown. Check the saved record before retrying.';
            } else {
                message = 'Preparation did not complete. Your draft is retained; review the errors or try preparation again.';
            }
        } finally {
            replay = null;
            attempt = null;
            saving = false;
            onLock(false);
            changed();
        }
        return state();
    }

    async function reconcile() {
        if (!unknown || saving || destroyed) return state();
        saving = true;
        changed();
        try {
            const result = await wire.reconcileImageSave();
            message = result?.message ?? 'Check the saved record before retrying.';
            // Native Save includes ordinary fields; an image goal check cannot confirm it.
        } catch {
            message = 'Save status is still unknown. Your draft is retained.';
        } finally {
            saving = false;
            changed();
        }
        return state();
    }

    return {
        state, run, reconcile,
        resetUnknown() {
            if (saving || methods.includes('create')) return;
            unknown = false;
            message = '';
            changed();
        },
        destroy() {
            destroyed = true;
            remove.forEach(cleanup => cleanup?.());
            removeMessages.forEach(cleanup => cleanup?.());
            onLock(false);
        },
    };
}

export function catalogImageEditor(config) {
    let controller;
    let frozenContainer;
    let previousInert = false;
    let localDraftVersion = 0;
    let uploadedSourcePending = false;
    const cleanups = [];
    return {
        saving: false,
        unknown: false,
        message: '',
        scanning: false,
        uploading: false,
        stopScan: false,
        destroyed: false,
        init() {
            controller = createCatalogImageSaveController({
                wire: this.$wire,
                methods: config.methods,
                draftIdentity: () => JSON.stringify([imageDraftIdentity(this.$wire), localDraftVersion]),
                canRun: () => !this.uploading && !this.scanning,
                onState: state => Object.assign(this, state),
                onLock: locked => {
                    if (locked) {
                        frozenContainer = this.$el.closest('.catalog-images-workspace') ?? this.$el.closest('.catalog-workspace-split') ?? this.$el.closest('.fi-modal') ?? this.$el.closest('[data-editor-record]') ?? this.$el.closest('form') ?? this.$el;
                        previousInert = frozenContainer.inert;
                        frozenContainer.inert = true;
                        frozenContainer.setAttribute('aria-busy', 'true');
                    } else if (frozenContainer) {
                        frozenContainer.inert = previousInert;
                        frozenContainer.removeAttribute('aria-busy');
                        frozenContainer = null;
                    }
                },
            });
            const listen = (target, type, callback, options) => {
                if (!target?.addEventListener) return;
                target.addEventListener(type, callback, options);
                cleanups.push(() => target.removeEventListener(type, callback, options));
            };
            const form = this.$el.closest('form');
            listen(form, 'input', () => localDraftVersion++, true);
            listen(form, 'change', () => localDraftVersion++, true);
            const markTransferDraft = active => {
                this.uploading = active;
                if (active) {
                    this.$el.setAttribute('data-draft-dirty', 'true');
                    this.$el.setAttribute('data-editor-record', this.$el.closest('[data-editor-record]')?.getAttribute('data-editor-record') ?? 'Image upload');
                } else {
                    this.$el.removeAttribute('data-draft-dirty');
                    this.$el.removeAttribute('data-editor-record');
                }
            };
            listen(form, 'form-processing-started', () => markTransferDraft(true));
            const continueUpload = () => {
                if (!uploadedSourcePending || this.uploading || this.destroyed) return;
                uploadedSourcePending = false;
                void this.scan();
            };
            listen(form, 'form-processing-finished', () => { markTransferDraft(false); continueUpload(); });
            listen(this.$wire.$el, 'catalog-image-source-started', event => {
                if (event.detail?.hostId !== this.$wire.$id) return;
                uploadedSourcePending = true;
                continueUpload();
            });
            const workspace = this.$el.closest('.catalog-images-workspace') ?? this.$el.closest('.catalog-workspace-split') ?? this.$el.closest('.fi-modal') ?? this.$wire.$el;
            const preventTransferNavigation = event => {
                if ((!this.uploading && !this.scanning) || this.$el.contains(event.target)) return;
                if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                this.message = 'Wait for the image upload or scan to finish, or stop it before leaving.';
            };
            listen(workspace, 'click', preventTransferNavigation, true);
            listen(workspace, 'keydown', preventTransferNavigation, true);
            listen(this.$wire.$el, 'catalog-image-draft-discarded', event => {
                if (event.detail?.hostId === this.$wire.$id) controller.resetUnknown();
            });
            listen(globalThis.window, 'beforeunload', event => {
                const data = this.$wire.data ?? {};
                if (this.saving || this.unknown || this.uploading || this.scanning || this.$wire.catalogMainImageUploadBinding
                    || (data.image_intent?.operation ?? 'keep') !== 'keep' || Object.keys(data.image_upload ?? {}).length) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },
        async chooseSource(choice) {
            if (this.saving || this.scanning || this.uploading) return;
            try { await this.$wire.catalogMainImageChooseSource(choice); }
            catch { this.message = 'The image source could not be opened. Check your access and try again.'; }
        },
        async stageUpload() {
            if (this.saving || this.scanning || this.uploading) return;
            try {
                const result = await this.$wire.catalogMainImageStageUpload();
                this.message = result?.message ?? '';
                if (result?.stage === 'scanning') await this.scan();
            } catch {
                this.message = 'The uploaded image was not staged. Check the upload or its source status before continuing.';
            }
        },
        async scan() {
            if (this.saving || this.scanning || this.uploading) return;
            this.scanning = true;
            this.stopScan = false;
            try {
                let progress = await this.$wire.catalogMainImageSourceStatus();
                while (!this.stopScan && !this.destroyed && (progress.stage === 'scanning'
                    || (progress.stage === 'uploading' && progress.file_count > 0 && progress.unsettled_uploads === 0))) {
                    progress = await this.$wire.catalogMainImageAdvanceSource();
                    this.message = progress?.message ?? '';
                    if (progress.outcome === 'busy' || progress.outcome === 'paused' || progress.next_action === 'operator_recovery') break;
                }
            } catch {
                this.message = 'Source progress is unknown. Check its status before continuing.';
            } finally {
                this.scanning = false;
            }
        },
        reconcile() { return controller.reconcile(); },
        destroy() {
            this.destroyed = true;
            this.stopScan = true;
            cleanups.splice(0).forEach(cleanup => cleanup());
            controller?.destroy();
        },
    };
}
