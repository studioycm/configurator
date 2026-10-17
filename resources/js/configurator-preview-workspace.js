export async function collectConfiguratorEditors(workspace, find = id => window.Livewire.find(id)) {
    if (!workspace) throw new Error('Configurator workspace is unavailable');
    return Promise.all([...workspace.querySelectorAll('[data-preview-editor]')].map(element => {
        const id = element.closest('[wire\\:id]')?.getAttribute('wire:id');
        if (!id) throw new Error('Configurator editor is unavailable');
        return find(id).exportPreviewChanges();
    }));
}

export function configuratorPreviewWorkspace(configuratorId) {
    return {
        collecting: false,
        collectionFailed: false,
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
