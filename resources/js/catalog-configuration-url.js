function withConfigurationCode(url, code) {
    const keys = [...url.searchParams.keys()].filter(key => key === 'configuration' || key.startsWith('configuration['));
    if ((code === null && keys.length === 0)
        || (code !== null && keys.length === 1 && keys[0] === 'configuration' && url.searchParams.get('configuration') === code)) return url;
    for (const key of keys) url.searchParams.delete(key);
    if (code !== null) url.searchParams.set('configuration', code);
    return url;
}

export function catalogConfigurationUrl(config, browserWindow = window) {
    const productPath = new URL(config.productUrl, browserWindow.location.href).pathname;
    let active = false, disposed = false, initialized = false, generation = 0, inFlight = false, pendingHistory = null;
    const listeners = [];

    return {
        configurationBusy: false,
        configurationDraft: config.code ?? '',
        publishedCode: config.code ?? null,
        configurationError: '',
        configurationCopyStatus: '',

        init() {
            if (initialized || disposed) return;
            initialized = true; active = true;
            const on = (name, handler) => { browserWindow.addEventListener(name, handler); listeners.push([name, handler]); };
            on('popstate', () => this.restoreConfigurationUrl());
            on('pagehide', () => { active = false; generation++; pendingHistory = null; });
            on('pageshow', event => { if (event.persisted && !disposed) { active = true; this.restoreConfigurationUrl(); } });
        },

        destroy() {
            disposed = true; active = false; generation++; pendingHistory = null;
            for (const [name, handler] of listeners) browserWindow.removeEventListener(name, handler);
            listeners.length = 0;
        },

        chooseConfiguration(attributeId, optionId) {
            return this.runConfiguration(() => this.$wire.chooseConfigurationOption(attributeId, optionId), 'push');
        },
        loadConfiguration() {
            return this.runConfiguration(() => this.$wire.loadConfigurationCode(this.configurationDraft), 'push');
        },
        refreshConfiguration() {
            return this.runConfiguration(() => this.$wire.reevaluateConfiguration(), 'replace');
        },

        restoreConfigurationUrl() {
            if (!active || disposed || browserWindow.location.pathname !== productPath) return;
            const query = new URL(browserWindow.location.href).searchParams;
            const values = query.getAll('configuration');
            const malformed = values.length > 1 || [...query.keys()].some(key => key.startsWith('configuration['));
            pendingHistory = { code: malformed ? [] : (values[0] ?? null) };
            generation++;
            if (!inFlight) return this.applyPendingConfigurationHistory();
        },

        applyPendingConfigurationHistory() {
            const intent = pendingHistory;
            pendingHistory = null;
            return this.runConfiguration(() => this.$wire.restoreConfigurationHistory(intent.code), 'history');
        },

        async runConfiguration(command, mode) {
            if (!active || disposed || inFlight || browserWindow.location.pathname !== productPath) return;
            inFlight = true; this.configurationBusy = true;
            this.configurationError = ''; this.configurationCopyStatus = '';
            const operation = ++generation;
            try {
                const reply = await command();
                if (!active || disposed || operation !== generation || browserWindow.location.pathname !== productPath || this.$el?.isConnected === false) return;
                if (reply?.componentId !== config.componentId || reply?.productId !== config.productId
                    || (this.$wire.$id && this.$wire.$id !== config.componentId)
                    || typeof reply.accepted !== 'boolean' || (reply.code !== null && typeof reply.code !== 'string')) {
                    this.publishedCode = null;
                    this.configurationError = config.failureMessage;
                    return;
                }
                this.publishedCode = reply.code;
                if (!reply.accepted) return;
                this.configurationDraft = reply.code ?? '';
                if (mode === 'history') return;
                const url = withConfigurationCode(new URL(browserWindow.location.href), reply.code);
                if (url.href !== browserWindow.location.href) browserWindow.history[mode === 'replace' ? 'replaceState' : 'pushState'](browserWindow.history.state, '', url.href);
            } catch {
                if (active && !disposed && operation === generation) {
                    this.publishedCode = null;
                    this.configurationError = config.failureMessage;
                }
            } finally {
                inFlight = false;
                if (active && !disposed && pendingHistory) await this.applyPendingConfigurationHistory();
                else this.configurationBusy = false;
            }
        },

        async copyConfigurationLink() {
            if (!active || disposed || this.configurationBusy || !this.publishedCode || browserWindow.location.pathname !== productPath) return;
            const operation = generation;
            const url = withConfigurationCode(new URL(browserWindow.location.href), this.publishedCode);
            try {
                await browserWindow.navigator.clipboard.writeText(url.href);
                if (active && !disposed && generation === operation) this.configurationCopyStatus = config.copySuccess;
            } catch {
                if (active && !disposed && generation === operation) this.configurationCopyStatus = config.copyFailure;
            }
        },
    };
}
