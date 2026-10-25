export async function collectConfiguratorEditors(workspace, find = id => window.Livewire.find(id)) {
    if (!workspace) throw new Error('Configurator workspace is unavailable');
    return Promise.all([...workspace.querySelectorAll('[data-preview-editor]')].map(element => {
        const id = element.closest('[wire\\:id]')?.getAttribute('wire:id');
        if (!id) throw new Error('Configurator editor is unavailable');
        return find(id).exportPreviewChanges();
    }));
}

export async function navigateConfiguratorEditor(workspace, target, find = id => window.Livewire.find(id), tick = () => window.Alpine.nextTick()) {
    if (!workspace || !['rule', 'value'].includes(target.type)) throw new Error('Conflict source is unavailable');
    const editor = target.type === 'rule' ? 'rules' : 'attributes';
    const tab = workspace.querySelector(`[data-preview-editor-tab=${editor}]`);
    if (!tab) throw new Error('Configurator tab is unavailable');
    const tabKey = tab.getAttribute('data-preview-editor-tab-key');
    const selector = `[data-preview-editor="${editor}"]`;
    const mounted = workspace.querySelector(selector);
    const ready = mounted ? Promise.resolve(mounted) : new Promise((resolve, reject) => {
        const finish = (element, error) => {
            clearTimeout(timeout);
            workspace.removeEventListener('configurator-preview-editor-ready', onReady);
            workspace.removeEventListener('configurator-preview-visibility', onTab);
            if (error) reject(error);
            else resolve(element);
        };
        const onReady = event => {
            const element = workspace.querySelector(selector);
            if (event.detail.editor === editor && element) finish(element);
        };
        const onTab = event => {
            if (event.detail.tab && event.detail.tab !== tabKey) finish(null);
        };
        const timeout = setTimeout(() => finish(null, new Error('Configurator editor did not become ready')), 10000);
        workspace.addEventListener('configurator-preview-editor-ready', onReady);
        workspace.addEventListener('configurator-preview-visibility', onTab);
    });
    workspace.dispatchEvent(new CustomEvent('configurator-select-editor-tab', { detail: { editor } }));
    const element = await ready;
    await tick();
    if (!element || !element.closest('[role="tabpanel"]')?.classList.contains('fi-active')) return;
    const id = element.closest('[wire\\:id]')?.getAttribute('wire:id');
    if (!id) throw new Error('Configurator editor is unavailable');
    element.dispatchEvent(new CustomEvent('configurator-preview-editor-opened'));
    const args = target.type === 'rule' ? [target.id] : [Number(target.attribute_id), Number(target.id)];
    if (target.type === 'value' && !args.every(value => Number.isSafeInteger(value) && value > 0)) throw new Error('Value is unavailable');
    if (!await find(id).openPreviewSource(...args)) throw new Error('Conflict source is unavailable');
    await tick();
    if (element.closest('[role="tabpanel"]')?.classList.contains('fi-active')) {
        element.querySelector(target.type === 'value' ? '[data-preview-value-editor]' : '[data-preview-editor-panel]')?.focus();
    }
}

export function configuratorPreviewWorkspace(configuratorId) {
    return {
        phase: 'idle',
        legacyCollecting: false,
        collectionFailed: false,
        navigationFailed: false,
        generation: 0,
        active: false,
        pendingAutomatic: false,
        configuratorId,
        receipt: null,
        pendingEditors: [],
        collectedEditors: [],
        earlyAcknowledgments: [],
        acknowledgmentTimeoutMs: 10000,
        eventTarget: null,
        cleanups: [],
        deferredUnmounts: [],
        requestPromise: null,
        saveQueued: false,
        submitted: false,
        submittedCaptures: null,
        savePermissionDenied: false,
        outcomeInspectionFailed: false,
        ownUnmount: false,
        closeQueued: false,
        destroyed: false,
        acknowledgmentWaiter: null,
        get busy() { return this.phase !== 'idle' || this.legacyCollecting; },
        get collecting() { return this.phase === 'collecting' || this.legacyCollecting; },
        set collecting(value) { this.legacyCollecting = !!value; },
        init() {
            this.eventTarget ??= globalThis.window;
            const listen = (name, callback) => {
                this.eventTarget?.addEventListener(name, callback);
                this.cleanups.push(() => this.eventTarget?.removeEventListener(name, callback));
            };
            listen('configurator-preview-editor-reconciled', event => this.acknowledgeEditor(event.detail));
            listen('configurator-preview-save-request', event => {
                if (event.detail?.configuratorId === this.configuratorId && event.detail?.componentId === this.componentId() && this.active) this.save();
            });
            if (typeof this.$wire?.$intercept === 'function') {
                this.cleanups.push(this.$wire.$intercept('unmountAction', ({ action }) => {
                    if (this.ownUnmount) return;
                    action.defer();
                    this.deferredUnmounts.push(action);
                    if (!this.busy) this.release();
                }));
            }
            this.$nextTick(() => this.visibility({ configuratorId, active: !!this.$el?.closest('[role="tabpanel"]')?.classList.contains('fi-active') }));
        },
        destroy() {
            this.destroyed = true;
            ++this.generation;
            this.cleanups.splice(0).forEach(cleanup => cleanup?.());
            this.deferredUnmounts.splice(0).forEach(action => action.cancel?.());
            this.acknowledgmentWaiter?.reject(new Error('Configurator workspace was removed'));
        },
        componentId() {
            return this.$wire?.$id ?? this.$el?.closest('[wire\\:id]')?.getAttribute('wire:id');
        },
        workspace() {
            return this.$el?.closest('[data-configurator-workspace]');
        },
        editorIds() {
            return [...new Set([...(this.workspace()?.querySelectorAll('[data-preview-editor]') ?? [])].map(element => element.closest('[wire\\:id]')?.getAttribute('wire:id')).filter(Boolean))];
        },
        findEditor(id) {
            return globalThis.window?.Livewire?.find(id);
        },
        collect() {
            return collectConfiguratorEditors(this.workspace());
        },
        navigate(target) {
            return navigateConfiguratorEditor(this.workspace(), target);
        },
        async release() {
            this.phase = 'idle';
            if (this.destroyed) return;
            while (this.deferredUnmounts.length) {
                const action = this.deferredUnmounts.shift();
                this.phase = 'request';
                try {
                    action.fire();
                    await action.promise;
                } catch {
                    this.collectionFailed = true;
                } finally {
                    this.phase = 'idle';
                }
            }
            if (this.closeQueued) {
                this.closeQueued = false;
                await this.closeReview();
            }
            if (this.pendingAutomatic && this.active && !this.saveQueued) {
                this.pendingAutomatic = false;
                await this.rebuild(undefined, true);
            }
        },
        async request(callback) {
            if (this.busy || this.destroyed) return false;
            this.phase = 'request';
            const operation = Promise.resolve().then(callback);
            this.requestPromise = operation;
            try {
                return await operation;
            } catch {
                this.collectionFailed = true;
                return false;
            } finally {
                if (this.requestPromise === operation) this.requestPromise = null;
                await this.release();
            }
        },
        async collectedRequest(callback, navigation = false) {
            if (this.busy || this.destroyed) return;
            const generation = ++this.generation;
            this.phase = 'collecting';
            this.collectionFailed = false;
            this.navigationFailed = false;
            let collected = false;
            try {
                const captures = await this.collect();
                if (generation !== this.generation) return;
                collected = true;
                this.phase = 'request';
                await callback(captures);
            } catch {
                if (!navigation || !collected) this.collectionFailed = true;
                else {
                    this.navigationFailed = true;
                    await this.$wire.notifyNavigationFailure().catch(() => {});
                }
            } finally {
                await this.release();
            }
        },
        openSource(target) {
            return this.collectedRequest(() => this.navigate(target), true);
        },
        explainConflicts() {
            return this.request(async () => {
                await this.$wire.showConflictDetails();
                this.$nextTick(() => this.$el?.querySelector('[data-preview-conflicts]')?.focus());
            });
        },
        visibility(detail) {
            if (detail.configuratorId !== this.configuratorId || detail.active === this.active) return;
            this.active = detail.active;
            if (detail.active) {
                if (this.busy || this.saveQueued) this.pendingAutomatic = true;
                else this.rebuild(undefined, true);
            } else if (!this.submitted && !this.receipt) ++this.generation;
        },
        rebuild(source, automatic = false) {
            if (this.busy) {
                if (automatic) this.pendingAutomatic = true;
                return;
            }
            return this.collectedRequest(captures => {
                const mode = automatic ? (captures.some(capture => capture.rows.length) ? 'unsaved' : 'saved') : (source ?? this.$wire.previewSource);
                return this.$wire.rebuildPreview(captures, mode);
            });
        },
        review() {
            return this.collectedRequest(captures => this.$wire.reviewChanges(captures));
        },
        selectOption(attributeId, optionId) {
            return this.request(() => this.$wire.selectOption(attributeId, optionId));
        },
        inspect(attributeId, optionId = null) {
            return this.request(() => this.$wire.inspectState(attributeId, optionId));
        },
        clearInspection() {
            return this.request(() => this.$wire.clearInspection());
        },
        notices() {
            return this.request(() => this.$wire.mountAction('previewNotices'));
        },
        closeReview() {
            if (this.busy) {
                this.closeQueued = true;
                return;
            }
            return this.request(async () => {
                this.ownUnmount = true;
                try { await this.$wire.unmountAction(); }
                finally { this.ownUnmount = false; }
            });
        },
        async save() {
            if (this.saveQueued || this.receipt || (this.busy && !(this.phase === 'request' && this.requestPromise))) return;
            this.saveQueued = true;
            this.savePermissionDenied = false;
            this.outcomeInspectionFailed = false;
            const generation = this.generation;
            try {
                if (this.requestPromise) await this.requestPromise.catch(() => {});
                if (this.busy || generation !== this.generation || this.destroyed) return;
                this.phase = 'collecting';
                this.collectionFailed = false;
                this.navigationFailed = false;
                this.collectedEditors = this.editorIds();
                this.earlyAcknowledgments = [];
                const captures = await this.collect();
                if (generation !== this.generation || this.destroyed) return;
                this.phase = 'request';
                this.submitted = true;
                this.submittedCaptures = JSON.parse(JSON.stringify(captures));
                const result = await this.$wire.saveChanges(this.submittedCaptures, this.collectedEditors);
                if (['rejected', 'rolled_back'].includes(result?.status)) {
                    this.submitted = false;
                    this.submittedCaptures = null;
                    return;
                }
                if (result?.status !== 'committed' || !result.receipt?.operation_id) throw new Error('Save outcome is unavailable');
                this.receipt = result.receipt;
                this.pendingEditors = [...result.receipt.editor_ids];
                this.phase = 'reconciling';
                this.earlyAcknowledgments.splice(0).forEach(detail => this.acknowledgeEditor(detail));
                await this.waitForAcknowledgments();
                await this.completeReconciliation();
            } catch (error) {
                if (this.receipt) this.phase = 'reconcile_failed';
                else if ([401, 403].includes(error?.status)) {
                    this.submitted = false;
                    this.submittedCaptures = null;
                    this.savePermissionDenied = true;
                }
                else if (this.submitted) this.phase = 'outcome_unknown';
                else this.collectionFailed = true;
            } finally {
                this.saveQueued = false;
                if (!['reconcile_failed', 'outcome_unknown'].includes(this.phase)) {
                    this.submitted = false;
                    await this.release();
                }
            }
        },
        acknowledgeEditor(detail) {
            if (detail?.configuratorId !== this.configuratorId || typeof detail.operationId !== 'string') return;
            if (!this.receipt) {
                if (this.submitted && this.collectedEditors.includes(detail.componentId) && this.earlyAcknowledgments.length < 20) this.earlyAcknowledgments.push(detail);
                return;
            }
            if (detail.operationId !== this.receipt.operation_id || !this.pendingEditors.includes(detail.componentId)) return;
            this.pendingEditors = this.pendingEditors.filter(id => id !== detail.componentId);
            if (!this.pendingEditors.length) this.acknowledgmentWaiter?.resolve();
        },
        waitForAcknowledgments() {
            if (!this.pendingEditors.length) return Promise.resolve();
            let timeout;
            return new Promise((resolve, reject) => {
                this.acknowledgmentWaiter = { resolve, reject };
                timeout = setTimeout(() => reject(new Error('Editor acknowledgment is missing')), this.acknowledgmentTimeoutMs);
            }).finally(() => {
                clearTimeout(timeout);
                this.acknowledgmentWaiter = null;
            });
        },
        async completeReconciliation() {
            await this.$wire.finishPreviewSave(this.receipt.operation_id);
            const captures = await this.collect();
            await this.$wire.refreshEditorInventory(captures);
            this.receipt = null;
            this.pendingEditors = [];
            this.submitted = false;
            this.submittedCaptures = null;
        },
        async retryReconciliation() {
            if (this.phase !== 'reconcile_failed' || !this.receipt) return;
            this.phase = 'reconciling';
            try {
                const results = await Promise.allSettled(this.pendingEditors.map(async id => {
                    const editor = this.findEditor(id);
                    if (!editor) throw new Error('Collected editor is no longer mounted');
                    await editor.acceptPreviewSave(this.configuratorId, this.receipt.captures, this.receipt.newRules, this.receipt.operation_id);
                    this.acknowledgeEditor({ configuratorId: this.configuratorId, operationId: this.receipt.operation_id, componentId: id });
                }));
                if (results.some(result => result.status === 'rejected') || this.pendingEditors.length) throw new Error('Editor refresh failed');
                await this.completeReconciliation();
                await this.release();
            } catch {
                this.phase = 'reconcile_failed';
            }
        },
        async inspectUnknownOutcome() {
            if (!['outcome_unknown', 'outcome_review'].includes(this.phase) || !this.submittedCaptures || this.destroyed) return;
            this.phase = 'inspecting_outcome';
            this.outcomeInspectionFailed = false;
            try {
                const result = await this.$wire.inspectSaveOutcome(this.submittedCaptures);
                if (result?.status === 'committed' && result.receipt?.operation_id) {
                    this.receipt = result.receipt;
                    this.pendingEditors = [...result.receipt.editor_ids];
                    this.phase = 'reconcile_failed';
                    await this.retryReconciliation();
                    return;
                }
                if (result?.status !== 'review') throw new Error('Current saved state is unavailable');
                this.phase = 'outcome_review';
            } catch {
                this.phase = 'outcome_unknown';
                this.outcomeInspectionFailed = true;
            }
        },
        async finishUnknownReview() {
            if (this.phase !== 'outcome_review' || this.destroyed) return;
            this.phase = 'inspecting_outcome';
            try {
                await this.$wire.finishSaveOutcomeReview();
                const captures = await this.collect();
                await this.$wire.refreshEditorInventory(captures);
                this.submitted = false;
                this.submittedCaptures = null;
                await this.release();
            } catch {
                this.phase = 'outcome_unknown';
                this.outcomeInspectionFailed = true;
            }
        },
        refreshSavedPreview() {
            return this.request(() => this.$wire.refreshSavedPreview());
        },
    };
}
