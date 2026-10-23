import { browserProfile } from './admin-profile.js';

const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const presentations = ['all', 'catalog'];
const ratioKeys = ['notes', 'trace', 'both'];

export function normalizePreviewDisplay(value) {
    const state = { presentation: 'all', metadata: { all: { codes: true, ids: false }, catalog: { codes: false, ids: false } }, notes: true, trace: false, compact: false, attributeSize: 14, valueSize: 12, ratios: { notes: 2 / 3, trace: 2 / 3, both: 0.5 } };
    if (!object(value)) return state;
    if (presentations.includes(value.presentation)) state.presentation = value.presentation;
    for (const key of ['notes', 'trace', 'compact']) if (typeof value[key] === 'boolean') state[key] = value[key];
    for (const [key, sizes] of [['attributeSize', [12, 14, 16]], ['valueSize', [11, 12, 14]]]) if (sizes.includes(value[key])) state[key] = value[key];
    for (const presentation of presentations) for (const key of ['codes', 'ids']) if (typeof value.metadata?.[presentation]?.[key] === 'boolean') state.metadata[presentation][key] = value.metadata[presentation][key];
    for (const key of ratioKeys) if (Number.isFinite(value.ratios?.[key]) && value.ratios[key] > 0 && value.ratios[key] < 1) state.ratios[key] = value.ratios[key];
    return state;
}

export function previewDisplayProfileState(value) { return normalizePreviewDisplay(value); }

export function configuratorPreviewDisplay(config) {
    let profile, revealListener, returnFocus = null, destroyed = false;
    return {
        settings: normalizePreviewDisplay(null),
        get showCodes() { return this.settings.metadata[this.settings.presentation].codes; },
        get showIds() { return this.settings.metadata[this.settings.presentation].ids; },
        get visibleSideCount() { return Number(this.settings.notes) + Number(this.settings.trace); },
        get panelCombination() { return this.settings.notes && this.settings.trace ? 'both' : this.settings.trace ? 'trace' : 'notes'; },
        get splitRatio() { return this.settings.ratios[this.panelCombination]; },
        get displayStyle() { return { '--preview-attribute-size': `${this.settings.attributeSize}px`, '--preview-value-size': `${this.settings.valueSize}px` }; },
        init() {
            profile = browserProfile(config.user, config.scope ?? `page:${window.location.pathname}:preview`);
            this.settings = normalizePreviewDisplay(profile.read());
            revealListener = event => {
                if (String(event.detail?.componentId) !== String(config.componentId) || Number(event.detail?.configuratorId) !== Number(config.configuratorId)) return;
                this.patch({ trace: true });
                this.$nextTick(() => {
                    if (destroyed) return;
                    const heading = this.$el.querySelector('[data-preview-inspection-heading]');
                    heading?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
                    heading?.focus?.({ preventScroll: true });
                });
            };
            window.addEventListener('configurator-preview-inspection-reveal', revealListener);
        },
        patch(patch) {
            if (!object(patch)) return;
            const next = previewDisplayProfileState(this.settings);
            if (presentations.includes(patch.presentation)) next.presentation = patch.presentation;
            for (const key of ['notes', 'trace', 'compact']) if (typeof patch[key] === 'boolean') next[key] = patch[key];
            for (const [key, sizes] of [['attributeSize', [12, 14, 16]], ['valueSize', [11, 12, 14]]]) if (sizes.includes(patch[key])) next[key] = patch[key];
            for (const presentation of presentations) for (const key of ['codes', 'ids']) if (typeof patch.metadata?.[presentation]?.[key] === 'boolean') next.metadata[presentation][key] = patch.metadata[presentation][key];
            for (const key of ratioKeys) if (Number.isFinite(patch.ratios?.[key]) && patch.ratios[key] > 0 && patch.ratios[key] < 1) next.ratios[key] = patch.ratios[key];
            this.settings = next;
            profile?.save(previewDisplayProfileState(this.settings));
        },
        setPresentation(presentation) { if (presentations.includes(presentation)) this.patch({ presentation }); },
        patchMetadata(patch) { this.patch({ metadata: { [this.settings.presentation]: patch } }); },
        setSplitRatio(ratio) { this.patch({ ratios: { [this.panelCombination]: ratio } }); },
        resetDisplay() { this.settings = normalizePreviewDisplay(null); profile?.save(previewDisplayProfileState(this.settings)); },
        setTheme(theme) { if (['light', 'dark'].includes(theme)) window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme })); },
        rememberInspectionTrigger(element) { returnFocus = element; },
        closeDiagnostics() { this.patch({ trace: false }); if (returnFocus?.isConnected) returnFocus.focus({ preventScroll: true }); returnFocus = null; },
        destroy() { destroyed = true; window.removeEventListener('configurator-preview-inspection-reveal', revealListener); returnFocus = null; },
    };
}
