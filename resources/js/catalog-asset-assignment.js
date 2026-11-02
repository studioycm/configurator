import { interceptCatalogImageMessage } from './catalog-image-editor.js';
import { createCatalogAssetUpload, transferCatalogAssetUpload } from './catalog-asset-upload.js';

const context = wire => ({ session: wire.launchSession, batch: wire.batchId, version: wire.previewVersion,
    source: wire.sourceId, source_version: wire.sourceVersion });
const applyContext = wire => ({...context(wire), pending: wire.applyProgress?.pending_intent?.id ?? null, acceptance: wire.applyProgress?.acceptance_id ?? null});
const meaning = wire => JSON.stringify([context(wire), wire.recipeData, wire.oneImageChoice, wire.candidateData, wire.selectedTableRecords, wire.applyProgress]);
const applyMethods = ['prepareImageApply','applyImages','processNextImageAssignment','continueImageApply','retryFailedImages','discardImageApply','startRemainingImageReview','selectReadyImageApply'];
const uploadMethods = ['issueReviewUpload', 'beginReviewUpload', 'issueReviewUploadEntry', 'refreshReviewUpload', 'finishReviewUpload'];
const methods = ['chooseReviewSource','advanceReviewSource','chooseOneImage','previewReview','buildReview',
    ...applyMethods, ...uploadMethods, 'refreshReviewStatus','inspectReviewItem','navigateReviewItem','setReviewIncluded','resolveReviewCandidate',
    'restoreRecipePreferences','clearReviewOverride','useCandidateSource','setReviewPageSize','backToRecords','previousCandidatePage','nextCandidatePage'];
const mutations = [...applyMethods.filter(method => method !== 'selectReadyImageApply'), 'beginReviewUpload', 'finishReviewUpload', 'chooseReviewSource','advanceReviewSource','chooseOneImage','previewReview','buildReview','setReviewIncluded','resolveReviewCandidate','clearReviewOverride'];

/** The foreground owns scheduling; committed cursor recovery is always read-only. */
export function createAssetAssignmentController({ wire, csrf, transferUpload = (cap, file) => transferCatalogAssetUpload(cap, file, { launchToken: wire.launchToken, csrf }), onState = () => {} }) {
    let destroyed = false, running = false, unknown = false, stopped = false, generation = 0, message = '';
    let upload = null, uploadInfo = null, cancelWait = null;
    const state = () => ({ running, unknown, message, upload: uploadInfo });
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
            if (kind === 'source' && progress.stage === 'uploading') {
                message = 'Copy all selected files and finish the upload before scanning.';
                changed();
                break;
            }
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
    function waitForUpload(ms) {
        return new Promise(resolve => {
            const finish = () => { clearTimeout(timer); globalThis.document?.removeEventListener?.('visibilitychange', hidden); cancelWait = null; resolve(); };
            const hidden = () => { if (globalThis.document?.hidden) finish(); };
            const timer = setTimeout(finish, Math.min(ms, 2147483647));
            cancelWait = finish;
            globalThis.document?.addEventListener?.('visibilitychange', hidden);
            if (!maySchedule()) finish();
        });
    }
    async function uploadLoop() {
        if (!upload) {
            if (!wire.intakeProgress?.declared_files) { message = 'Choose image files or reopen an owned batch upload source.'; return; }
            const progress = await send('refreshReviewUpload', [null]);
            if (!maySchedule()) return;
            if (!progress?.declared_files || progress.file_count !== progress.declared_files || progress.unsettled_uploads) {
                message = 'This browser no longer has the unsent Files. Preserve this source and choose a fresh selection.';
                return;
            }
            if (!progress.uploads_sealed) await send('finishReviewUpload', [progress.revision]);
            return;
        }
        while (maySchedule() && !upload.state().done) {
            try {
                const result = await upload.advance();
                if (!result || destroyed) break;
                message = result.message ?? '';
                changed();
                if (result.outcome === 'busy' || result.recovery === 'unresolved') break;
            } catch (error) {
                if (error.status !== 429) throw error;
                message = 'Upload limit reached. Waiting for Retry-After; Stop remains available.';
                changed();
                await waitForUpload(error.retryAfterMs ?? 60000);
            }
        }
    }
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
        uploadProgress(info = uploadInfo) {
            const progress = info?.progress?.source_id === wire.sourceId ? info.progress : wire.intakeProgress;
            return progress?.declared_files ? progress : null;
        },
        selectUploadFiles(files) {
            if (destroyed || running || unknown || wire.applyProgress?.accepted) return;
            try {
                const next = createCatalogAssetUpload(files, {
                    issue: (metadata, label) => send('issueReviewUpload', [metadata, label]),
                    begin: token => send('beginReviewUpload', [token]),
                    entry: index => send('issueReviewUploadEntry', [index]),
                    transfer: async (cap, file) => {
                        const ticket = generation, original = meaning(wire);
                        const result = await transferUpload(cap, file);
                        if (destroyed || ticket !== generation || original !== meaning(wire)) throw new Error('The upload context changed.');
                        return result;
                    },
                    status: entry => wire.sourceId ? send('refreshReviewUpload', [entry]) : send('refreshReviewStatus'),
                    finish: revision => send('finishReviewUpload', [revision]),
                }, { mayContinue: maySchedule, onChange: info => { uploadInfo = info; changed(); } });
                upload?.destroy(); upload = next; uploadInfo = next.state();
                message = uploadInfo.skipped.length ? `${uploadInfo.skipped.length} unsupported files skipped before selection.` : 'Images selected. Choose Upload files to start.';
            } catch (error) { message = error.message; }
            changed();
        },
        async uploadFiles() { return run(uploadLoop); },
        async continueUpload() { return run(uploadLoop); },
        async refreshUpload() {
            return run(async () => {
                const result = upload?.state().source_id ? await upload.refresh()
                    : wire.intakeProgress?.declared_files ? await send('refreshReviewUpload', [null]) : await send('refreshReviewStatus');
                if (destroyed) return;
                if (!result || typeof result !== 'object') throw new Error('Unconfirmed upload status');
                unknown = false; message = 'Upload status refreshed. Choose Continue upload explicitly.';
                return result;
            }, false);
        },
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
                if (!destroyed && method === 'chooseReviewSource' && upload && uploadInfo?.source_id !== wire.sourceId) {
                    upload.destroy(); upload = null; uploadInfo = null;
                }
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
        stop() { stopped = true; cancelWait?.(); message = 'Stopped scheduling. An in-flight request may still commit; refresh status if needed.'; changed(); },
        destroy() { destroyed = true; stopped = true; generation++; cancelWait?.(); upload?.destroy(); remove(); },
    };
}

export function catalogAssetAssignment(config = {}) {
    let controller, wire, destroyed = false;
    return {
        running: false, unknown: false, message: '', upload: null,
        init() {
            // Capture Livewire's native proxy before a morph detaches the initiating element.
            wire = this.$wire;
            controller = createAssetAssignmentController({ wire, csrf: config.csrf, onState: state => {
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
        selectUploadFiles(files) { controller.selectUploadFiles(files); },
        uploadProgress() { return controller.uploadProgress(this.upload); },
        uploadFiles() { return controller.uploadFiles(); }, continueUpload() { return controller.continueUpload(); }, refreshUpload() { return controller.refreshUpload(); },
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
