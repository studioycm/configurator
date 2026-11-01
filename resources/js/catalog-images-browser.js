import { browserProfile } from './admin-profile.js';
import { interceptCatalogImageMessage } from './catalog-image-editor.js';

export const imagesContext = wire => ({ target: wire.imageTarget, mode: wire.imageNavigationMode,
    record: wire.imageRecordId, revision: wire.imageBrowserRevision });
const browserMeaning = wire => JSON.stringify([imagesContext(wire), wire.imageSearch, wire.tableSort,
    wire.tableRecordsPerPage, wire.selectedTableRecords, wire.paginators]);

/** Cancel obsolete responses at the installed Livewire pre-sync boundary. */
export function imagesMessageGuard(wire, { generation = () => 0, alive = () => true, onMorphed = () => {} } = {}) {
    return interceptCatalogImageMessage(wire, ({ cancel, onSuccess }) => {
        const intent = generation(), meaning = browserMeaning(wire);
        onSuccess(({ onMorphed: afterMorph }) => {
            if (!alive() || intent !== generation() || meaning !== browserMeaning(wire)) {
                cancel();
                return;
            }
            afterMorph(onMorphed);
        });
    });
}

/** Chunks are bounded in encoded bytes and never split a Unicode code point. */
export function* imageCodeChunks(text, maximum = 32768) {
    let chunk = '', bytes = 0;
    const encoder = new TextEncoder();
    for (const point of text) {
        const size = encoder.encode(point).length;
        if (bytes + size > maximum) {
            yield chunk;
            chunk = ''; bytes = 0;
        }
        chunk += point; bytes += size;
    }
    if (chunk) yield chunk;
}

export function catalogImagesBrowser(config) {
    let destroyed = false, generation = 0, removeGuard, removeWatch, eventRoot, restoring = false;
    let profile, profileScope, restoration = 0;
    return {
        pasteText: '', transferring: false, clientError: '',
        async init() {
            const sync = () => {
                if (destroyed) return;
                // Native selection is entangled but its internal Set is initialized only once.
                const table = this.$el.querySelector('[data-catalog-images-native-table] [x-data]');
                const data = table && globalThis.Alpine?.$data(table);
                if (data?.selectedRecords instanceof Set) data.selectedRecords = new Set((this.$wire.selectedTableRecords ?? []).map(String));
                if (!restoring) {
                    const state = this.profileState();
                    if (state.scope === profileScope && new TextEncoder().encode(JSON.stringify(state)).length <= 32768) profile?.save(state);
                }
            };
            removeGuard = imagesMessageGuard(this.$wire, { generation: () => generation, alive: () => !destroyed, onMorphed: sync });
            this.onInspect = event => {
                // Installed Livewire dispatches server events on the component root after morph.
                if (!destroyed && event.detail.host === this.$wire.$id
                    && JSON.stringify(event.detail.context) === JSON.stringify(imagesContext(this.$wire))) this.$refs.inspectorHeading?.focus();
            };
            eventRoot = this.$wire.$el;
            eventRoot.addEventListener('catalog-images-inspected', this.onInspect);
            removeWatch = this.$wire.$watch('imageTarget', () => {
                generation++; this.pasteText = ''; this.clientError = '';
                void this.restoreProfile();
            });
            await this.restoreProfile();
        },
        profileState() {
            return { scope: `App\\Filament\\Pages\\CatalogImages:catalog-images:${this.$wire.imageTarget === 'product.main_image' ? 'product' : 'group'}:selection:v1`,
                search: JSON.parse(JSON.stringify(this.$wire.imageSearch)), sort: this.$wire.tableSort,
                perPage: Number(this.$wire.tableRecordsPerPage ?? 10),
                columns: (this.$wire.tableColumns ?? []).map(column => ({ name: column.name, isToggled: !!column.isToggled })) };
        },
        async restoreProfile() {
            if (destroyed) return;
            const current = this.profileState();
            const ticket = ++restoration;
            profileScope = current.scope;
            profile = browserProfile(config.user, `images:${current.scope}`);
            const saved = profile.read();
            if (!saved || saved.scope !== current.scope) { profile.save(current); restoring = false; return; }
            restoring = true;
            try { await this.$wire.restoreTableBrowserProfile(saved); }
            catch { if (!destroyed && ticket === restoration) this.clientError = 'Saved browsing options could not be restored. Current defaults are retained.'; }
            finally { if (ticket === restoration) restoring = false; }
        },
        async inspect(detail) {
            if (!['inspectProductImage', 'inspectGroupImage'].includes(detail?.method)) return;
            return this.runCommand(detail.method, [detail.record], detail.context);
        },
        async command(method, ...args) {
            return this.runCommand(method, args, imagesContext(this.$wire));
        },
        async runCommand(method, args, context) {
            if (destroyed || this.transferring) return;
            const ticket = ++generation;
            this.clientError = '';
            try { return await this.$wire.$call(method, ...args, context); }
            catch (error) {
                if (!destroyed && ticket === generation && error?.status !== 422) this.clientError = 'The view could not be updated. Check the current view and try again.';
            }
        },
        async transferCodes() {
            if (destroyed || this.transferring) return;
            const text = this.pasteText;
            const bytes = new TextEncoder().encode(text).length;
            if (!bytes || bytes > 524288) { this.clientError = 'Paste up to 512 KiB of complete product codes.'; return; }
            const ticket = ++generation;
            const wire = this.$wire;
            this.transferring = true; this.clientError = '';
            try {
                // Retain the native component proxy across morphs of the initiating button.
                const identity = await wire.beginExactCodePaste(imagesContext(wire));
                let offset = 0;
                for (const chunk of imageCodeChunks(text)) {
                    if (destroyed || ticket !== generation) return;
                    const result = await wire.appendExactCodePaste(identity, offset, chunk, imagesContext(wire));
                    offset += new TextEncoder().encode(chunk).length;
                    if (result?.bytes !== offset) throw new Error('Unexpected acknowledgement');
                }
                if (!destroyed && ticket === generation) await wire.finishExactCodePaste(identity, imagesContext(wire));
            } catch (error) {
                if (!destroyed && ticket === generation && error?.status !== 422) this.clientError = 'The code transfer stopped. Your pasted text is retained; try again.';
            } finally { if (!destroyed) this.transferring = false; }
        },
        destroy() {
            destroyed = true; generation++;
            removeGuard?.(); removeWatch?.();
            eventRoot?.removeEventListener('catalog-images-inspected', this.onInspect);
        },
    };
}
