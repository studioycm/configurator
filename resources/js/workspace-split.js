const stops = [1 / 3, 1 / 2, 2 / 3];

export function workspaceSplit({ user, owner, tab }) {
    return {
        step: 1,
        stacked: true,
        dragging: false,
        observer: null,
        root: null,
        dragStart: 1,
        get ratio() { return stops[this.step]; },
        get percentage() { return Math.round(this.ratio * 100); },
        get columns() { return `minmax(0, ${this.step + 2}fr) 12px minmax(0, ${4 - this.step}fr)`; },
        get storageKey() { return `aquestia:split:${user}:${owner}:${tab}:v1`; },
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
                if (Number.isInteger(stored) && stored >= 0 && stored <= 2) this.step = stored;
            } catch { /* A blocked or old preference leaves the default usable. */ }
        },
        save() {
            try { localStorage.setItem(this.storageKey, JSON.stringify(this.step)); } catch { /* Resizing also works without storage. */ }
        },
        reset() { this.step = 1; this.save(); },
        resize(width) { this.stacked = width < 980; },
        snap(ratio) { return stops.reduce((best, stop, index) => Math.abs(stop - ratio) < Math.abs(stops[best] - ratio) ? index : best, 0); },
        startDrag(event) {
            if (this.stacked || event.button !== 0) return;
            event.preventDefault();
            this.dragStart = this.step;
            this.dragging = true;
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        moveDrag(event) {
            if (!this.dragging) return;
            const bounds = this.root.getBoundingClientRect();
            let ratio = (event.clientX - bounds.left - 6) / (bounds.width - 12);
            if (getComputedStyle(this.root).direction === 'rtl') ratio = 1 - ratio;
            this.step = this.snap(ratio);
        },
        endDrag(cancel = false) {
            if (!this.dragging) return;
            this.dragging = false;
            if (cancel) this.step = this.dragStart;
            else this.save();
        },
        keyDown(event) {
            const rtl = this.root && getComputedStyle(this.root).direction === 'rtl';
            const direction = rtl ? -1 : 1;
            const keys = { ArrowLeft: this.step - direction, ArrowRight: this.step + direction, Home: 0, End: 2, Enter: 1 };
            if (!(event.key in keys)) return;
            event.preventDefault();
            this.step = Math.max(0, Math.min(2, keys[event.key]));
            this.save();
        },
        destroy() { this.observer?.disconnect(); },
    };
}
