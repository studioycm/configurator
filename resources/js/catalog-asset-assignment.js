import { interceptCatalogImageMessage } from './catalog-image-editor.js';

const context = wire => ({ session: wire.launchSession, batch: wire.batchId, version: wire.previewVersion,
    source: wire.sourceId, source_version: wire.sourceVersion });
const meaning = wire => JSON.stringify([context(wire), wire.recipeData, wire.oneImageChoice, wire.candidateData]);
const methods = ['chooseReviewSource','advanceReviewSource','chooseOneImage','previewReview','buildReview',
    'refreshReviewStatus','inspectReviewItem','navigateReviewItem','setReviewIncluded','resolveReviewCandidate',
    'restoreRecipePreferences','clearReviewOverride','useCandidateSource','setReviewPageSize','backToRecords','previousCandidatePage','nextCandidatePage'];
const mutations = ['chooseReviewSource','advanceReviewSource','chooseOneImage','previewReview','buildReview','setReviewIncluded','resolveReviewCandidate','clearReviewOverride'];

/** The foreground owns scheduling; committed cursor recovery is always read-only. */
export function createAssetAssignmentController({ wire, onState = () => {} }) {
    let destroyed = false, running = false, unknown = false, stopped = false, generation = 0, message = '';
    const state = () => ({ running, unknown, message });
    const changed = () => { if (!destroyed) onState(state()); };
    const remove = interceptCatalogImageMessage(wire, ({ message: request, cancel, onSuccess }) => {
        if (!request.getActions().some(action => methods.includes(action.name))) return;
        const ticket = generation, original = meaning(wire);
        onSuccess(() => { if (destroyed || ticket !== generation || original !== meaning(wire)) cancel(); });
    });
    async function send(method, args = []) {
        const result = await wire.$call(method, ...args, context(wire));
        if (destroyed) return null;
        return result;
    }
    // Cursor endpoints take expected context before the expected revision.
    async function step(method, revision) {
        return wire.$call(method, context(wire), revision);
    }
    async function run(work, mutating = true) {
        if (destroyed || running || (unknown && mutating)) return;
        running = true; stopped = false; generation++; message = ''; changed();
        try { return await work(); }
        catch {
            if (!destroyed) {
                unknown ||= mutating;
                message = mutating ? 'The response was not confirmed. Refresh status before continuing; no request will be replayed.' : 'Status is unavailable. Try Refresh status when the connection returns.';
            }
        } finally { running = false; changed(); }
    }
    async function loop(kind) {
        let result;
        while (!destroyed && !stopped && !(globalThis.document?.hidden)) {
            const progress = kind === 'source' ? wire.sourceProgress : wire.reviewProgress;
            if (!progress || (kind === 'source' ? progress.stage === 'ready' : progress.stage !== 'building')) break;
            result = await step(kind === 'source' ? 'advanceReviewSource' : 'buildReview', progress.revision);
            if (destroyed) break;
            if (!result || typeof result.stage !== 'string') throw new Error('Unconfirmed progress');
            message = result.message ?? '';
            if (result.outcome === 'busy' || (kind === 'source' && ['failed','expired','blocked'].includes(result.stage))) break;
            changed();
        }
        return result;
    }
    return {
        state,
        async command(method, ...args) {
            if (!methods.includes(method) || ['advanceReviewSource','buildReview','refreshReviewStatus'].includes(method)) return;
            return run(async () => {
                const result = await send(method, args);
                if (!destroyed && mutations.includes(method) && !result) throw new Error('Unconfirmed change');
                if (!destroyed) message = result?.message ?? '';
                return result;
            }, mutations.includes(method));
        },
        async preview() {
            return run(async () => {
                const result = await send('previewReview');
                if (destroyed) return;
                if (!result || typeof result.stage !== 'string') throw new Error('Unconfirmed review');
                return loop('review');
            });
        },
        async build() { return run(() => loop('review')); },
        async scan() { return run(() => loop('source')); },
        async refresh() {
            return run(async () => {
                const result = await send('refreshReviewStatus');
                if (destroyed) return;
                if (!result || !('review' in result) || !('source' in result)) throw new Error('Unconfirmed status');
                unknown = false; message = 'Status refreshed. Choose Continue to do more work.';
                return result;
            }, false);
        },
        stop() { stopped = true; message = 'Stopped scheduling. An in-flight request may still commit; refresh status if needed.'; changed(); },
        destroy() { destroyed = true; stopped = true; generation++; remove(); },
    };
}

export function catalogAssetAssignment(config = {}) {
    let controller, wire, destroyed = false;
    return {
        running: false, unknown: false, message: '',
        init() {
            // Capture Livewire's native proxy before a morph detaches the initiating element.
            wire = this.$wire;
            controller = createAssetAssignmentController({ wire, onState: state => {
                Object.assign(this, state);
                if (wire.batchId) {
                    try {
                        globalThis.localStorage?.setItem(`catalog-image-review:${config.user}:${wire.targetKey}`, wire.batchId);
                    } catch { /* optional preference */ }
                }
            } });
            if (!wire.batchId) {
                try {
                    const saved = globalThis.localStorage?.getItem(`catalog-image-recipe:${config.user}:${wire.targetKey}:${wire.operation}`);
                    if (saved && saved.length <= 8192) void controller.command('restoreRecipePreferences', JSON.parse(saved));
                } catch { /* unusable optional preference */ }
            }
        },
        command(method, ...args) { return controller.command(method, ...args); },
        async preview() {
            const result = await controller.preview();
            if (!destroyed && !controller.state().unknown && wire.batchId) {
                try { globalThis.localStorage?.setItem(`catalog-image-recipe:${config.user}:${wire.targetKey}:${wire.operation}`, JSON.stringify({ mode: wire.recipeData.mode, parameters: wire.recipeData.parameters })); } catch { /* optional preference */ }
            }
            return result;
        }, build() { return controller.build(); }, scan() { return controller.scan(); },
        refresh() { return controller.refresh(); }, stop() { controller.stop(); }, destroy() { destroyed = true; controller?.destroy(); },
    };
}
