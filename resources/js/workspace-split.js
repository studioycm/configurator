const stops = [0.3, 0.35, 0.4, 0.45, 0.5, 0.55, 0.6, 0.65, 0.7];
const legacyStops = [1 / 3, 1 / 2, 2 / 3];

export function workspaceSplit({ user, owner, tab }) {
    return {
        preferredRatio: 0.5,
        width: 0,
        stacked: true,
        dragging: false,
        observer: null,
        root: null,
        dragStart: 0.5,
        splitterHintVisible: false,
        splitterHintTimer: null,
        get minimumRatio() { return this.stacked ? 0.3 : Math.max(0.3, 420 / (this.width - 12)); },
        get maximumRatio() { return this.stacked ? 0.7 : 1 - this.minimumRatio; },
        get availableStops() { return stops.filter(stop => stop >= this.minimumRatio && stop <= this.maximumRatio); },
        get ratio() { return Math.max(this.minimumRatio, Math.min(this.maximumRatio, this.preferredRatio)); },
        get percentage() { return Math.round(this.ratio * 100); },
        get minimumPercentage() { return Math.round(this.minimumRatio * 100); },
        get maximumPercentage() { return Math.round(this.maximumRatio * 100); },
        get columns() { return `minmax(0, ${this.ratio}fr) 12px minmax(0, ${1 - this.ratio}fr)`; },
        get storageKey() { return `aquestia:split:${user}:${owner}:${tab}:v2`; },
        init() {
            this.root = this.$el;
            this.restore();
            this.observer = new ResizeObserver(entries => this.resize(entries[0].contentRect.width));
            this.observer.observe(this.root);
            this.resize(this.root.getBoundingClientRect().width);
        },
        restore() {
            try {
                const stored = JSON.parse(localStorage.getItem(this.storageKey));
                if (Number.isFinite(stored) && stored >= 0.3 && stored <= 0.7) {
                    this.preferredRatio = stored;
                    return;
                }
                const legacy = JSON.parse(localStorage.getItem(`aquestia:split:${user}:${owner}:${tab}:v1`));
                if (Number.isInteger(legacy) && legacy >= 0 && legacy < legacyStops.length) this.preferredRatio = legacyStops[legacy];
            } catch { /* A blocked or old preference leaves the default usable. */ }
        },
        save() {
            try { localStorage.setItem(this.storageKey, JSON.stringify(this.preferredRatio)); } catch { /* Resizing also works without storage. */ }
        },
        reset() { this.preferredRatio = 0.5; this.save(); },
        resize(width) { this.width = width; this.stacked = width < 980; },
        snap(ratio) { return this.availableStops.reduce((best, stop) => Math.abs(stop - ratio) < Math.abs(best - ratio) ? stop : best, 0.5); },
        showSplitterHint() {
            if (this.dragging) return;
            this.hideSplitterHint();
            this.splitterHintVisible = true;
            this.splitterHintTimer = setTimeout(() => { this.splitterHintVisible = false; this.splitterHintTimer = null; }, 3000);
        },
        hideSplitterHint() {
            clearTimeout(this.splitterHintTimer);
            this.splitterHintTimer = null;
            this.splitterHintVisible = false;
        },
        startDrag(event) {
            if (this.stacked || event.button !== 0) return;
            event.preventDefault();
            this.dragStart = this.preferredRatio;
            this.dragging = true;
            event.currentTarget.focus?.({ preventScroll: true });
            this.hideSplitterHint();
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        moveDrag(event) {
            if (!this.dragging) return;
            const bounds = this.root.getBoundingClientRect();
            let ratio = (event.clientX - bounds.left - 6) / (bounds.width - 12);
            if (getComputedStyle(this.root).direction === 'rtl') ratio = 1 - ratio;
            this.preferredRatio = this.snap(ratio);
        },
        endDrag(cancel = false) {
            if (!this.dragging) return;
            this.dragging = false;
            if (cancel) this.preferredRatio = this.dragStart;
            else this.save();
        },
        keyDown(event) {
            const rtl = this.root && getComputedStyle(this.root).direction === 'rtl';
            const direction = rtl ? -1 : 1;
            const previous = this.availableStops.filter(stop => stop < this.ratio - 0.000001).at(-1) ?? this.preferredRatio;
            const next = this.availableStops.find(stop => stop > this.ratio + 0.000001) ?? this.preferredRatio;
            const keys = { ArrowLeft: direction === 1 ? previous : next, ArrowRight: direction === 1 ? next : previous, Home: this.availableStops[0], End: this.availableStops.at(-1), Enter: 0.5 };
            if (!(event.key in keys)) return;
            event.preventDefault();
            this.preferredRatio = keys[event.key];
            this.save();
        },
        destroy() { this.hideSplitterHint(); this.observer?.disconnect(); },
    };
}
