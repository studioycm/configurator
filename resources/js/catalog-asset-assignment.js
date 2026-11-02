import { interceptCatalogImageMessage } from './catalog-image-editor.js';

const context = wire => ({ session: wire.launchSession, batch: wire.batchId, version: wire.previewVersion,
    source: wire.sourceId, source_version: wire.sourceVersion });
const applyContext = wire => ({...context(wire), pending: wire.applyProgress?.pending_intent?.id ?? null, acceptance: wire.applyProgress?.acceptance_id ?? null});
const meaning = wire => JSON.stringify([context(wire), wire.recipeData, wire.oneImageChoice, wire.candidateData, wire.selectedTableRecords, wire.applyProgress]);
const applyMethods = ['prepareImageApply','applyImages','processNextImageAssignment','continueImageApply','retryFailedImages','discardImageApply','startRemainingImageReview','selectReadyImageApply'];
const methods = ['chooseReviewSource','advanceReviewSource','chooseOneImage','previewReview','buildReview',
    ...applyMethods, 'refreshReviewStatus','inspectReviewItem','navigateReviewItem','setReviewIncluded','resolveReviewCandidate',
    'restoreRecipePreferences','clearReviewOverride','useCandidateSource','setReviewPageSize','backToRecords','previousCandidatePage','nextCandidatePage'];
const mutations = [...applyMethods.filter(method => method !== 'selectReadyImageApply'), 'chooseReviewSource','advanceReviewSource','chooseOneImage','previewReview','buildReview','setReviewIncluded','resolveReviewCandidate','clearReviewOverride'];

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
        const result = await wire.$call(method, ...args, applyMethods.includes(method) ? applyContext(wire) : context(wire));
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
    const maySchedule = () => !destroyed && !stopped && !globalThis.document?.hidden;
    async function applyLoop(expectedAcceptance) {
        while (maySchedule()) {
            const progress = wire.applyProgress;
            if (!expectedAcceptance || progress?.acceptance_id !== expectedAcceptance || progress.active_acceptance_id !== expectedAcceptance || !progress.next_step_id || progress.busy || progress.recovery_required) break;
            const acceptance = progress.acceptance_id;
            const result = await send('processNextImageAssignment', [progress.next_step_id]);
            if (destroyed) return;
            if (!result || !result.progress || result.progress.acceptance_id !== acceptance) throw new Error('Unconfirmed image assignment');
            message = `This Apply: ${Object.entries(result.progress.counts ?? {}).map(([status, count]) => `${status} ${count}`).join(' · ')}`;
            changed();
            if (!['applied','unchanged','failed','stale'].includes(result.outcome)) {
                message = result.outcome === 'busy' ? 'Another image request is busy. Refresh status before explicitly continuing.'
                    : result.outcome === 'recovery_required' ? 'This request needs recovery. Choose Continue Apply to replace its expired step.'
                    : 'Apply paused. Refresh status before explicitly continuing; no next request was sent.';
                changed();
                break;
            }
        }
    }
    async function acceptPrepared() {
        if (!maySchedule()) return;
        const intent = wire.applyProgress?.pending_intent?.id;
        if (!intent) throw new Error('Prepared Apply unavailable');
        const result = await send('applyImages', [intent]);
        if (destroyed) return;
        if (!result || result.acceptance_id !== intent) throw new Error('Unconfirmed acceptance');
        return applyLoop(intent);
    }
    return {
        state,
        async apply() {
            if (wire.applyProgress?.active_acceptance_id || wire.applyProgress?.pending_intent || !wire.selectedTableRecords?.length) return;
            return run(async () => {
                const result = await send('prepareImageApply', [[...wire.selectedTableRecords]]);
                if (destroyed) return;
                if (!result?.pending_intent?.id) throw new Error('Unconfirmed prepared Apply');
                return acceptPrepared();
            });
        },
        async confirmApply() { return run(() => acceptPrepared()); },
        async continueApply() {
            return run(async () => {
                const acceptance = wire.applyProgress?.acceptance_id;
                const result = await send('continueImageApply');
                if (destroyed) return;
                if (!result?.acceptance_id) throw new Error('Unconfirmed Continue');
                return applyLoop(acceptance);
            });
        },
        async retryApply() {
            return run(async () => {
                const acceptance = wire.applyProgress?.acceptance_id;
                const result = await send('retryFailedImages');
                if (destroyed) return;
                if (!result?.acceptance_id) throw new Error('Unconfirmed Retry');
                return applyLoop(acceptance);
            });
        },
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
                const syncChecks = () => {
                    const table = wire.$el?.querySelector('.catalog-asset-review-native [x-data]');
                    const data = table && globalThis.Alpine?.$data(table);
                    if (data?.selectedRecords instanceof Set) data.selectedRecords = new Set((wire.selectedTableRecords ?? []).map(String));
                };
                if (this.$nextTick) this.$nextTick(syncChecks); else syncChecks();
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
        apply() { return controller.apply(); }, confirmApply() { return controller.confirmApply(); }, continueApply() { return controller.continueApply(); }, retryApply() { return controller.retryApply(); },
        refresh() { return controller.refresh(); }, stop() { controller.stop(); }, destroy() { destroyed = true; controller?.destroy(); },
    };
}
