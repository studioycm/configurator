import { guidedTour } from './guided-tour.js';

// Keep launchers attached to visible sections, including inside independently scrolling panes.
export function tourAnchorRect(target, browserWindow) {
    if (!target?.isConnected || !target.getClientRects().length || target.closest('[hidden], [inert], [aria-hidden="true"]')) return null;
    const style = browserWindow.getComputedStyle(target);
    if (style.visibility === 'hidden' || style.display === 'none') return null;
    const rect = target.getBoundingClientRect();
    let left = Math.max(0, rect.left), top = Math.max(0, rect.top);
    let right = Math.min(browserWindow.innerWidth, rect.right), bottom = Math.min(browserWindow.innerHeight, rect.bottom);
    for (let parent = target.parentElement; parent; parent = parent.parentElement) {
        const css = browserWindow.getComputedStyle(parent), bounds = parent.getBoundingClientRect();
        if (/(auto|scroll|hidden|clip)/.test(css.overflowX)) { left = Math.max(left, bounds.left); right = Math.min(right, bounds.right); }
        if (/(auto|scroll|hidden|clip)/.test(css.overflowY)) { top = Math.max(top, bounds.top); bottom = Math.min(bottom, bounds.bottom); }
    }
    if (right - left < 32 || bottom - top < 32) return null;
    return { left, top, right, bottom };
}

export function foregroundTourModal(browserDocument, browserWindow) {
    return [...browserDocument.querySelectorAll('dialog[open], [aria-modal="true"]')]
        .find(node => node.getClientRects().length && browserWindow.getComputedStyle(node).visibility !== 'hidden');
}

let contextSequence = 0;
export function guidedTourWorkspace(config, dependencies = {}) {
    const browserWindow = dependencies.window ?? window, browserDocument = dependencies.document ?? document;
    const tour = guidedTour(config, dependencies);
    const context = `tour-context-${++contextSequence}`;
    let frame = null, observer, sizes, restore, dead = false;
    const removers = [];
    const on = (target, event, callback, options) => {
        target.addEventListener(event, callback, options);
        removers.push(() => target.removeEventListener(event, callback, options));
    };
    const key = `ari-guided-tour-help:${config.userId}`;
    return {
        ...tour, launchers: [],
        init() {
            tour.init.call(this);
            const store = this.$store.guidedTourHelp;
            store.contexts[context] = true;
            if (store.userKey !== key) {
                store.userKey = key;
                try { store.enabled = browserWindow.sessionStorage.getItem(key) === '1'; } catch { store.enabled = false; }
            }
            const schedule = () => this.scheduleLaunchers();
            on(browserWindow, 'guided-tour-help-toggle', () => {
                store.enabled = !store.enabled;
                try { browserWindow.sessionStorage.setItem(key, store.enabled ? '1' : '0'); } catch { /* Help still works without storage. */ }
                if (!store.enabled) this.close();
                schedule();
            });
            on(browserDocument, 'keydown', event => {
                if (event.key === 'Escape' && !event.defaultPrevented && !this.active && store.enabled && !foregroundTourModal(browserDocument, browserWindow)) {
                    store.enabled = false;
                    try { browserWindow.sessionStorage.removeItem(key); } catch { /* Optional tab preference. */ }
                    schedule();
                }
            });
            on(browserWindow, 'scroll', () => { this.refresh(); schedule(); }, true);
            on(browserWindow, 'resize', () => { this.refresh(); schedule(); });
            on(browserDocument, 'livewire:navigating', () => { this.launchers = []; this.destroy(); });
            observer = new MutationObserver(records => {
                if (records.some(record => !record.target.closest?.('[data-tour-launchers], .driver-popover, .driver-overlay'))) schedule();
            });
            observer.observe(browserDocument.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'hidden', 'inert', 'aria-hidden', 'open'] });
            sizes = new ResizeObserver(schedule);
            sizes.observe(browserDocument.body);
            this.scheduleLaunchers();
        },
        scheduleLaunchers() {
            if (dead || frame !== null) return;
            frame = browserWindow.requestAnimationFrame(() => { frame = null; this.positionLaunchers(); });
        },
        positionLaunchers() {
            if (this.active) return;
            const next = [], used = new Map();
            if (this.$store.guidedTourHelp.enabled && !this.active) {
                const modal = foregroundTourModal(browserDocument, browserWindow);
                for (const item of config.tours ?? []) {
                    const selector = config.targets[item.steps?.[0]?.target];
                    const target = selector ? browserDocument.querySelector(selector) : null;
                    if (modal && !modal.contains(target)) continue;
                    const bounds = tourAnchorRect(target, browserWindow);
                    if (!bounds) continue;
                    const slot = used.get(target) ?? 0;
                    const columns = Math.max(1, Math.floor((bounds.right - bounds.left) / 34));
                    const x = bounds.right - 32 - (slot % columns) * 34;
                    const y = bounds.top + 2 + Math.floor(slot / columns) * 34;
                    if (y + 30 > bounds.bottom) continue;
                    used.set(target, slot + 1);
                    next.push({ key: item.key, title: item.title, x: Math.round(x), y: Math.round(y) });
                }
            }
            if (JSON.stringify(next) !== JSON.stringify(this.launchers)) this.launchers = next;
        },
        start(key, source) {
            tour.start.call(this, key, source);
            restore = source?.currentTarget ?? source;
            this.scheduleLaunchers();
        },
        finished() {
            tour.finished.call(this);
            this.scheduleLaunchers();
            const target = restore; restore = null;
            this.$nextTick(() => {
                if (!dead && !this.active && this.$store.guidedTourHelp.enabled && target?.isConnected) target.focus();
            });
        },
        destroy() {
            if (dead) return;
            dead = true;
            tour.destroy.call(this);
            observer?.disconnect(); sizes?.disconnect();
            removers.splice(0).forEach(remove => remove());
            if (frame !== null) browserWindow.cancelAnimationFrame(frame);
            frame = null; this.launchers = [];
            delete this.$store.guidedTourHelp.contexts[context];
        },
    };
}
