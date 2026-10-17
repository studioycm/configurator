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
        collecting: false,
        collectionFailed: false,
        navigationFailed: false,
        generation: 0,
        active: false,
        pendingAutomatic: false,
        configuratorId,
        init() {
            this.$nextTick(() => {
                this.visibility({ configuratorId, active: !!this.$el.closest('[role="tabpanel"]')?.classList.contains('fi-active') });
            });
        },
        collect() {
            return collectConfiguratorEditors(this.$el.closest('[data-configurator-workspace]'));
        },
        navigate(target) {
            return navigateConfiguratorEditor(this.$el.closest('[data-configurator-workspace]'), target);
        },
        async openSource(target) {
            if (this.collecting) return;
            const generation = ++this.generation;
            this.collecting = true;
            this.collectionFailed = false;
            this.navigationFailed = false;
            let collected = false;
            try {
                await this.collect();
                if (generation !== this.generation) return;
                collected = true;
                await this.navigate(target);
            } catch {
                if (!collected) this.collectionFailed = true;
                else {
                    this.navigationFailed = true;
                    await this.$wire.notifyNavigationFailure().catch(() => {});
                }
            } finally {
                this.collecting = false;
                if (this.pendingAutomatic && this.active) {
                    this.pendingAutomatic = false;
                    await this.rebuild(undefined, true);
                }
            }
        },
        async explainConflicts() {
            await this.$wire.showConflictDetails();
            this.$nextTick(() => this.$el.querySelector('[data-preview-conflicts]')?.focus());
        },
        visibility(detail) {
            if (detail.configuratorId !== this.configuratorId || detail.active === this.active) return;
            this.active = detail.active;
            if (detail.active) {
                if (this.collecting) this.pendingAutomatic = true;
                else this.rebuild(undefined, true);
            } else ++this.generation;
        },
        async rebuild(source, automatic = false) {
            if (this.collecting) return;
            const generation = ++this.generation;
            this.collecting = true;
            this.collectionFailed = false;
            try {
                const captures = await this.collect();
                if (generation !== this.generation) return;
                const mode = automatic ? (captures.some(capture => capture.rows.length) ? 'unsaved' : 'saved') : (source ?? this.$wire.previewSource);
                await this.$wire.rebuildPreview(captures, mode);
            } catch {
                this.collectionFailed = true;
            } finally {
                this.collecting = false;
                if (this.pendingAutomatic && this.active) {
                    this.pendingAutomatic = false;
                    await this.rebuild(undefined, true);
                }
            }
        },
        async review() {
            if (this.collecting) return;
            const generation = ++this.generation;
            this.collecting = true;
            this.collectionFailed = false;
            try {
                const captures = await this.collect();
                if (generation !== this.generation) return;
                await this.$wire.reviewChanges(captures);
            } catch {
                this.collectionFailed = true;
            } finally {
                this.collecting = false;
                if (this.pendingAutomatic && this.active) {
                    this.pendingAutomatic = false;
                    await this.rebuild(undefined, true);
                }
            }
        },
    };
}
