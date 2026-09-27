import { createFilterEngine, readDiscovery, discoveryUrl } from './catalog-filter-engine.js';

function catalogFilters() {
    // Dataset rows, indexes, timers and transport identities never enter Alpine's proxy.
    let snapshot;
    let root;
    let engine;
    let displayedRevision;
    let generation = 0;
    let refreshSequence = 0;
    let refreshPromise;
    let refreshController;
    let cardTimer;
    let freshnessTimer;
    let insertionFrame;
    let cleanupFrame;
    let cleanupPromise;
    let resolveCleanup;
    let confirmedAt = 0;
    let attemptedAt = -Infinity;
    let automaticRetries = 1;
    let alive = true;
    let suspended = false;
    let initialized = false;
    const instance = crypto.randomUUID();
    const listeners = [];
    const current = (id, revision) => alive && !suspended && id === generation && snapshot?.revision === revision;
    const listen = (target, type, callback) => { target.addEventListener(type, callback); listeners.push(()=>target.removeEventListener(type,callback)); };
    const stopWork = () => {
        generation++;
        clearTimeout(cardTimer);
        clearTimeout(freshnessTimer);
        cancelAnimationFrame(insertionFrame);
        cancelAnimationFrame(cleanupFrame);
        resolveCleanup?.();
        cleanupPromise = null;
        resolveCleanup = null;
        refreshSequence++;
        refreshController?.abort();
        refreshPromise = null;
    };
    const errorText = error => [401,403,419].includes(Number(error?.status))
        ? 'Your session has expired or access changed. Reload this page to continue.'
        : 'Products could not be loaded. Please retry.';

    return {
        state: {version:1, filters:{}, subGroupId:null, precedence:[]},
        fields: [], presets: [], notices: [], total: null, columns: 4, maximum: 'all',
        available: false, unavailable: '', reloadRequired: false, freshnessError: '', refreshing: false,
        cardStatus: 'idle', cardError: '', cardChunks: [], cardsVisible: false,

        init() {
            if (initialized) return;
            initialized = true;
            root = this.$el;
            const source = root.querySelector('[data-catalog-snapshot]');
            try {
                const initial = JSON.parse(source?.textContent ?? 'null');
                source?.remove();
                if (initial) {
                    snapshot = initial;
                    engine = createFilterEngine(snapshot);
                    this.available = true;
                    confirmedAt = performance.now();
                    this.publish(engine.normalize(readDiscovery(location.href)), 'replace');
                } else this.unavailable = 'Filters are temporarily unavailable. Please retry.';
            } catch {
                this.unavailable = 'The catalog could not be initialized. Reload this page.';
                this.reloadRequired = true;
            }
            listen(window, 'popstate', () => {
                if (!alive || suspended || !engine) return;
                automaticRetries = 1;
                this.publish(engine.normalize(readDiscovery(location.href)), 'replace');
            });
            listen(window, 'focus', () => this.checkFreshness());
            listen(document, 'visibilitychange', () => this.checkFreshness());
            listen(window, 'online', () => this.checkFreshness());
            listen(window, 'pagehide', () => { suspended = true; stopWork(); });
            listen(window, 'pageshow', () => {
                if (!suspended || !alive) return;
                suspended = false;
                this.refreshing = false;
                if (engine) this.publish(engine.normalize(readDiscovery(location.href)), 'replace');
                this.checkFreshness();
            });
            this.armFreshness();
        },

        destroy() { alive = false; stopWork(); listeners.splice(0).forEach(remove=>remove()); },

        choose(type, key = null, value = null) {
            if (!this.available || suspended) return;
            const started = performance.now();
            const result = engine.change(this.state, type === 'filter' ? {type,key,value} : type === 'preset' ? {type,id:key} : {type});
            if (!result.changed) return;
            automaticRetries = 1;
            this.publish(result, 'push', started);
        },

        publish(result, historyMode, started = performance.now()) {
            generation++;
            clearTimeout(cardTimer);
            cancelAnimationFrame(insertionFrame);
            this.clearCards();
            this.cardError = '';
            this.state = result.state;
            this.notices = result.notices;
            const computed = engine.evaluate(result.state);
            this.total = computed.total;
            this.maximum = snapshot.settings.max_results;
            this.columns = snapshot.settings.cards_per_row;
            if (displayedRevision !== snapshot.revision) {
                this.presets = snapshot.presets.map(({id,label})=>({id,label}));
                this.fields = snapshot.fields.map(field => ({key:field.key,label:field.label,hidden:false,
                    options:field.options.map(option=>({value:option.value,label:option.label,code:option.code,
                        identity:JSON.stringify([snapshot.groupId,field.key,option.value]),selected:false,compatible:false}))}));
                displayedRevision = snapshot.revision;
            }
            for (const field of this.fields) {
                field.hidden = field.key === computed.hiddenKey || field.options.length === 0;
                for (const option of field.options) {
                    option.selected = result.state.filters[field.key] === option.value;
                    option.compatible = computed.compatibility[field.key][option.code];
                }
            }
            const url = discoveryUrl(location.href, result.state);
            if (url.href !== location.href) history[historyMode === 'push' ? 'pushState' : 'replaceState'](history.state, '', url);
            const focusKey = document.activeElement?.closest('[data-filter-key]')?.dataset.filterKey;
            const computationMs = performance.now() - started;
            const id = generation;
            this.$nextTick(() => {
                if (!alive || suspended || id !== generation) return;
                if (focusKey && this.fields.some(field => field.key === focusKey && field.hidden)) this.$refs.reset.focus();
                const domMs = performance.now() - started;
                requestAnimationFrame(()=>requestAnimationFrame(()=> {
                    if (alive && !suspended && id === generation) root.dispatchEvent(new CustomEvent('catalog:feedback', {bubbles:true,detail:{computationMs,domMs,paintEstimateMs:performance.now()-started}}));
                }));
            });
            this.scheduleCards();
        },

        clearCards() {
            this.cardsVisible = false;
            if (cleanupPromise) return cleanupPromise;
            if (this.cardChunks.length === 0) return Promise.resolve();
            cleanupPromise = new Promise(resolve => { resolveCleanup = resolve; });
            const clear = () => {
                if (!alive || suspended) return;
                if (this.cardChunks.length > 0) {
                    this.cardChunks.pop();
                    this.$nextTick(() => { if (alive && !suspended) cleanupFrame = requestAnimationFrame(clear); });
                } else {
                    resolveCleanup?.();
                    resolveCleanup = null;
                    cleanupPromise = null;
                }
            };
            // Let the filter update paint before retiring old, already-hidden card chunks.
            this.$nextTick(() => { if (alive && !suspended) cleanupFrame = requestAnimationFrame(clear); });
            return cleanupPromise;
        },

        scheduleCards() {
            clearTimeout(cardTimer);
            if (!alive || suspended || !this.available) return;
            if (this.total === 0) { this.cardStatus = 'empty'; return; }
            if (this.maximum !== 'all' && this.total > this.maximum) { this.cardStatus = 'above_threshold'; return; }
            this.cardStatus = 'loading';
            const id = generation;
            const delay = snapshot.settings.products_debounce_ms;
            if (delay === 0) this.loadCards(id);
            else cardTimer = setTimeout(()=>this.loadCards(id), delay);
        },

        async loadCards(id) {
            const revision = snapshot.revision;
            if (!current(id, revision)) return;
            const requestId = `${instance}:${id}`;
            try {
                const response = await this.$wire.loadCards(JSON.parse(JSON.stringify(this.state)), revision, requestId);
                if (!current(id, revision) || response?.requestId !== requestId) return;
                if (response.status === 'refresh_required' || response.revision !== revision) {
                    if (automaticRetries > 0) {
                        automaticRetries--;
                        const refreshed = await this.refreshSnapshot(true);
                        if (refreshed && current(id, revision)) this.scheduleCards();
                        else if (!refreshed && current(id, revision)) { this.cardStatus = 'error'; this.cardError = 'The catalog changed. Retry to load current products.'; }
                    } else { this.cardStatus = 'error'; this.cardError = 'The catalog is still changing. Please retry.'; }
                    return;
                }
                if (!['ready','empty','above_threshold'].includes(response.status) || response.total !== this.total) throw new Error('Inconsistent catalog response');
                this.confirm(revision);
                if (response.status !== 'ready') { this.cardStatus = response.status; return; }
                await this.clearCards();
                if (!current(id, revision)) return;
                let index = 0;
                const insert = () => {
                    if (!current(id, revision)) return;
                    if (index < response.htmlChunks.length) {
                        this.cardChunks.push({key:`${requestId}:${index}`, html:response.htmlChunks[index++]});
                        this.cardsVisible = true;
                        this.$nextTick(()=> { if (current(id, revision)) insertionFrame = requestAnimationFrame(insert); });
                    } else this.cardStatus = 'ready';
                };
                insertionFrame = requestAnimationFrame(insert);
            } catch (error) {
                if (!current(id, revision)) return;
                this.cardStatus = 'error';
                this.cardError = errorText(error);
                if ([404,409].includes(Number(error?.status))) this.exitLeaf(Number(error.status));
            }
        },

        retryCards() {
            automaticRetries = 1;
            generation++;
            cancelAnimationFrame(insertionFrame);
            this.clearCards();
            this.cardError = '';
            this.scheduleCards();
        },

        confirm(revision) {
            if (snapshot?.revision !== revision || !alive || suspended) return;
            confirmedAt = performance.now();
            this.freshnessError = '';
            this.armFreshness();
        },

        armFreshness() {
            clearTimeout(freshnessTimer);
            if (!alive || suspended || document.hidden || this.reloadRequired) return;
            const due = Math.max(confirmedAt + 60000, attemptedAt + 60000);
            freshnessTimer = setTimeout(()=>this.checkFreshness(), Math.max(1000, due - performance.now()));
        },

        checkFreshness() {
            clearTimeout(freshnessTimer);
            if (!alive || suspended || document.hidden || this.reloadRequired) return;
            if (performance.now() - confirmedAt >= 60000 && performance.now() - attemptedAt >= 60000) this.refreshSnapshot(false);
            else this.armFreshness();
        },

        refreshSnapshot(manual = true) {
            if (!alive || suspended || this.reloadRequired) return Promise.resolve(false);
            if (refreshPromise) return refreshPromise;
            const sequence = ++refreshSequence;
            const revision = snapshot?.revision;
            refreshController = new AbortController();
            const signal = refreshController.signal;
            attemptedAt = performance.now();
            this.refreshing = true;
            const valid = () => alive && !suspended && sequence === refreshSequence && snapshot?.revision === revision;
            refreshPromise = (async () => {
                try {
                    const headers = {Accept:'application/json'};
                    if (snapshot) headers['If-None-Match'] = `"catalog-${snapshot.groupId}-${snapshot.schema}-${revision}"`;
                    const response = await fetch(root.dataset.datasetUrl, {headers,signal,credentials:'same-origin',cache:'no-cache'});
                    if (!valid()) return false;
                    if (response.status === 304 && snapshot) { this.confirm(revision); return true; }
                    if ([404,409].includes(response.status)) { this.exitLeaf(response.status); return false; }
                    if (!response.ok) throw {status:response.status};
                    const replacement = await response.json();
                    if (!valid()) return false;
                    if (replacement.schema !== 1) {
                        this.reloadRequired = true;
                        this.freshnessError = 'The catalog format changed. Reload this page.';
                        return false;
                    }
                    if (replacement.groupId !== root.dataset.groupId || !/^[1-9][0-9]{0,19}$/.test(replacement.revision)) throw new Error('Invalid dataset identity');
                    if (revision && BigInt(replacement.revision) < BigInt(revision)) return false;
                    if (replacement.revision !== revision || !engine) {
                        const newEngine = createFilterEngine(replacement);
                        const choices = engine ? this.state : readDiscovery(location.href);
                        const normalized = newEngine.normalize(choices);
                        snapshot = replacement;
                        engine = newEngine;
                        this.available = true;
                        this.unavailable = '';
                        this.publish(normalized, 'replace');
                    }
                    this.confirm(snapshot.revision);
                    return true;
                } catch (error) {
                    if (!valid() || signal.aborted) return false;
                    const message = [401,403,419].includes(Number(error?.status)) ? errorText(error) : 'Could not check for catalog updates. Your current filters remain available. Retry when connected.';
                    if (this.available) this.freshnessError = message;
                    else this.unavailable = 'Filters are temporarily unavailable. Please retry.';
                    return false;
                } finally {
                    if (sequence === refreshSequence) {
                        refreshPromise = null;
                        this.refreshing = false;
                        this.armFreshness();
                    }
                }
            })();
            return refreshPromise;
        },

        exitLeaf(status) {
            stopWork();
            this.available = false;
            this.cardChunks = [];
            this.cardStatus = 'idle';
            this.refreshing = false;
            this.reloadRequired = true;
            this.unavailable = status === 404 ? 'This Group is no longer available. Return to the catalog.' : 'This Group now contains other groups. Reload to browse them.';
        },
    };
}

document.addEventListener('alpine:init', () => window.Alpine.data('catalogFilters', catalogFilters), {once:true});
