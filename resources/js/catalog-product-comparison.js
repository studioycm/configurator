let previousGroup = null;
const canonicalId = value => typeof value === 'string' && /^[1-9][0-9]*$/.test(value)
    && (value.length < 19 || (value.length === 19 && value <= '9223372036854775807'));
const validRows = rows => Array.isArray(rows) && rows.every(row => row && typeof row.key === 'string'
    && typeof row.label === 'string' && Array.isArray(row.values) && row.values.length === 2
    && row.values.every(value => value === null || typeof value === 'string'));

export function catalogProductComparison(config, browserWindow = window) {
    let root, dialog, opener, restoreFocus = true, alive = false, suspended = false, initialized = false, generation = 0;
    const listeners = [];
    const labels = config.labels;
    const groupId = config.groupId;
    const owns = () => alive && !suspended && root?.isConnected !== false && root?.dataset.catalogComparisonGroup === groupId;

    return {
        comparisonItems: [], comparisonReplacement: null, comparisonResult: null,
        comparisonBusy: false, comparisonOpen: false, comparisonAvailable: true,
        comparisonError: '', comparisonNotice: labels.group_scope,

        init() {
            if (initialized) return;
            initialized = true; alive = true; root = this.$el;
            dialog = root.querySelector('[data-comparison-dialog]');
            if (previousGroup && previousGroup.id !== groupId && previousGroup.hadSelection) this.comparisonNotice = labels.group_changed;
            previousGroup = { id: groupId, hadSelection: false };
            const on = (target, event, callback) => { target.addEventListener(event, callback); listeners.push(() => target.removeEventListener(event, callback)); };
            on(dialog, 'cancel', event => { event.preventDefault(); this.closeComparison(); });
            on(dialog, 'close', () => this.afterComparisonClose());
            on(browserWindow, 'pagehide', () => { suspended = true; this.closeComparison(false); });
            on(browserWindow, 'pageshow', () => { if (alive) suspended = false; });
            on(browserWindow, 'focus', () => { if (owns() && this.comparisonOpen) this.readComparison(true); });
        },

        destroy() {
            this.closeComparison(false); alive = false; generation++;
            listeners.splice(0).forEach(remove => remove());
        },

        comparisonIsSelected(id) { return this.comparisonItems.some(item => item.id === id); },

        toggleComparisonProduct(id, label, inResults) {
            if (!owns() || !this.comparisonAvailable || !canonicalId(id) || typeof label !== 'string') return;
            if (this.comparisonIsSelected(id)) { this.removeComparisonProduct(id); return; }
            const item = { id, label, inResults: inResults === true };
            if (this.comparisonItems.length === 2) { this.comparisonReplacement = item; return; }
            this.changeComparisonPair([...this.comparisonItems, item]);
        },

        changeComparisonPair(items) {
            this.closeComparison(); this.comparisonItems = items; this.comparisonReplacement = null;
            if (items.length) this.comparisonNotice = labels.group_scope;
            previousGroup = { id: groupId, hadSelection: items.length > 0 };
        },

        removeComparisonProduct(id) {
            this.changeComparisonPair(this.comparisonItems.filter(item => item.id !== id));
        },
        replaceComparisonProduct(slot) {
            if (!owns() || ![0, 1].includes(slot) || this.comparisonItems.length !== 2 || !this.comparisonReplacement) return;
            this.changeComparisonPair(this.comparisonItems.map((item, index) => index === slot ? this.comparisonReplacement : item));
        },
        cancelComparisonReplacement() { this.comparisonReplacement = null; },

        updateComparisonMatches(ids, currentGroup, options = {}) {
            if (currentGroup !== groupId || options.unavailable) {
                this.changeComparisonPair([]); this.comparisonAvailable = false; this.comparisonNotice = labels.group_changed;
                return;
            }
            this.comparisonAvailable = true;
            this.comparisonItems = this.comparisonItems.map(item => ({ ...item, inResults: ids.includes(item.id) }));
            if (this.comparisonReplacement) this.comparisonReplacement = { ...this.comparisonReplacement, inResults: ids.includes(this.comparisonReplacement.id) };
        },

        async openComparison() {
            if (!owns() || !this.comparisonAvailable || this.comparisonItems.length !== 2 || this.comparisonOpen || this.comparisonBusy) return;
            opener = browserWindow.document.activeElement; restoreFocus = true;
            this.comparisonResult = null; this.comparisonError = '';
            try { dialog.showModal(); this.comparisonOpen = true; }
            catch { this.comparisonError = labels.open_failed; return; }
            await this.readComparison();
        },

        retryComparison() { return this.readComparison(); },

        async readComparison(force = false) {
            if (!owns() || !this.comparisonOpen || !this.comparisonAvailable || this.comparisonItems.length !== 2 || (this.comparisonBusy && !force)) return;
            const operation = ++generation;
            const pair = this.comparisonItems.map(item => item.id);
            const requestId = `${config.componentId}:comparison:${operation}`;
            this.comparisonResult = null; this.comparisonError = ''; this.comparisonBusy = true;
            const current = () => owns() && this.comparisonOpen && operation === generation
                && JSON.stringify(this.comparisonItems.map(item => item.id)) === JSON.stringify(pair);
            try {
                const reply = await this.$wire.loadComparison(pair, requestId);
                if (!current()) return;
                if (!reply || reply.requestId !== requestId || reply.componentId !== config.componentId || reply.groupId !== groupId
                    || (this.$wire.$id && this.$wire.$id !== config.componentId)
                    || JSON.stringify(reply.pairIds) !== JSON.stringify(pair)
                    || typeof reply.groupRevision !== 'string' || !/^[1-9][0-9]*$/.test(reply.groupRevision)
                    || !Array.isArray(reply.products) || reply.products.length !== 2
                    || reply.products.some((product, index) => product.id !== pair[index] || typeof product.code !== 'string')
                    || !validRows(reply.differences) || !validRows(reply.same)) throw new Error('Invalid comparison response');
                this.comparisonResult = { products: reply.products, differences: reply.differences, same: reply.same };
            } catch (error) {
                if (!current()) return;
                this.comparisonResult = null;
                const stale = [401, 403, 404, 409, 422].includes(Number(error?.status));
                if (stale) {
                    this.comparisonItems = []; this.comparisonReplacement = null;
                    this.comparisonNotice = labels.stale; previousGroup = { id: groupId, hadSelection: false };
                }
                this.comparisonError = stale ? labels.stale : labels.failed;
            } finally {
                if (operation === generation) this.comparisonBusy = false;
            }
        },

        closeComparison(shouldRestoreFocus = true) {
            generation++; this.comparisonBusy = false; this.comparisonResult = null; this.comparisonError = '';
            this.comparisonOpen = false; restoreFocus = shouldRestoreFocus;
            if (dialog?.open) dialog.close();
            else if (!shouldRestoreFocus) opener = null;
        },

        afterComparisonClose() {
            // Native close events are queued and may belong to a previous opening.
            if (dialog?.open) return;
            generation++; this.comparisonOpen = false; this.comparisonBusy = false; this.comparisonResult = null; this.comparisonError = '';
            const target = opener; opener = null;
            const shouldFocus = restoreFocus; restoreFocus = false;
            if (!shouldFocus || !alive || suspended) return;
            if (target?.isConnected && !target.disabled && root.contains(target) && !target.closest?.('[inert]')
                && (!target.getClientRects || target.getClientRects().length > 0)) target.focus();
            else {
                const button = root.querySelector('[data-comparison-open]');
                if (button && !button.disabled) button.focus();
                else root.querySelector('[data-comparison-tray]')?.focus();
            }
        },
    };
}
