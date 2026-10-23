const stops = [0.3, 0.35, 0.4, 0.45, 0.5, 0.55, 0.6, 0.65, 0.7];
const legacyStops = [1 / 3, 1 / 2, 2 / 3];
const defaultRatio = 0.65;

export function workspaceSplit({ user, owner, tab, preview = null }) {
    const previewDefault = () => Number(preview?.sideCount?.() ?? 1) === 2 ? 0.5 : 2 / 3;
    let pointerTarget = null, pointerId = null;
    return {
        preferredRatio: preview ? previewDefault() : defaultRatio,
        previewSideCount: Number(preview?.sideCount?.() ?? 0),
        get dividerTrack() { return preview?.track ?? 26; },
        get primaryGap() { return preview?.gap ?? 0; },
        get availablePaneWidth() { return Math.max(1, this.width - this.dividerTrack - this.primaryGap * 2); },
        get sideMinimumWidth() { return (preview?.sideMinimum ?? 240) * this.previewSideCount + (this.previewSideCount > 1 ? preview?.sideGap ?? 12 : 0); },
        width: 0,
        stacked: true,
        dragging: false,
        observer: null,
        root: null,
        dragStart: defaultRatio,
        splitterHintVisible: false,
        splitterHintTimer: null,
        get minimumRatio() { return preview ? (preview.primaryMinimum ?? 440) / this.availablePaneWidth : this.stacked ? 0.3 : Math.max(0.3, 420 / (this.width - 26)); },
        get maximumRatio() { return preview ? 1 - this.sideMinimumWidth / this.availablePaneWidth : this.stacked ? 0.7 : 1 - this.minimumRatio; },
        get availableStops() { return stops.filter(stop => stop >= this.minimumRatio && stop <= this.maximumRatio); },
        get ratio() { if (preview && this.stacked) return this.preferredRatio; return Math.max(this.minimumRatio, Math.min(this.maximumRatio, this.preferredRatio)); },
        get percentage() { return Math.round(this.ratio * 100); },
        get minimumPercentage() { return Math.round(this.minimumRatio * 100); },
        get maximumPercentage() { return Math.round(this.maximumRatio * 100); },
        get columns() { return `minmax(0, ${this.ratio}fr) ${this.dividerTrack}px minmax(0, ${1 - this.ratio}fr)`; },
        get storageKey() { return `aquestia:split:${user}:${owner}:${tab}:v2`; },
        init() {
            this.root = this.$el;
            this.restore();
            this.observer = new ResizeObserver(entries => this.resize(entries[0].contentRect.width));
            this.observer.observe(this.root);
            this.resize(this.root.getBoundingClientRect().width);
        },
        restore() {
            if (preview) {
                const stored = preview.read?.();
                if (Number.isFinite(stored) && stored > 0 && stored < 1) this.preferredRatio = stored;
                return;
            }
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
            if (preview) { preview.write?.(this.preferredRatio); return; }
            try { localStorage.setItem(this.storageKey, JSON.stringify(this.preferredRatio)); } catch { /* Resizing also works without storage. */ }
        },
        reset() { this.preferredRatio = preview ? previewDefault() : defaultRatio; this.save(); },
        resize(width) {
            this.width = Math.max(0, Number(width) || 0);
            this.stacked = preview ? this.previewSideCount === 0 || this.width < (preview.threshold ?? 1040) || this.availablePaneWidth < (preview.primaryMinimum ?? 440) + this.sideMinimumWidth : width < 980;
            if (this.stacked && this.dragging) this.endDrag(true);
        },
        syncPreviewPanels(count, preferred) {
            if (!preview) return;
            if (this.previewSideCount !== count && this.dragging) this.endDrag(true);
            this.previewSideCount = count;
            if (!this.dragging && Number.isFinite(preferred) && preferred > 0 && preferred < 1) this.preferredRatio = preferred;
            this.resize(this.width);
        },
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
            if (this.stacked || this.dragging || event.button !== 0) return;
            event.preventDefault();
            this.dragStart = this.preferredRatio;
            pointerTarget = event.currentTarget; pointerId = event.pointerId;
            this.dragging = true;
            event.currentTarget.focus?.({ preventScroll: true });
            this.hideSplitterHint();
            event.currentTarget.setPointerCapture?.(event.pointerId);
        },
        moveDrag(event) {
            if (!this.dragging || (event.pointerId !== undefined && event.pointerId !== pointerId)) return;
            const bounds = this.root.getBoundingClientRect();
            let ratio = (event.clientX - bounds.left - this.dividerTrack / 2 - this.primaryGap) / this.availablePaneWidth;
            if (getComputedStyle(this.root).direction === 'rtl') ratio = 1 - ratio;
            this.preferredRatio = preview ? Math.max(this.minimumRatio, Math.min(this.maximumRatio, ratio)) : this.snap(ratio);
        },
        endDrag(cancel = false) {
            if (!this.dragging) return;
            this.dragging = false;
            if (cancel) this.preferredRatio = this.dragStart;
            else this.save();
            try { pointerTarget?.releasePointerCapture?.(pointerId); } catch {}
            pointerTarget = null; pointerId = null;
        },
        keyDown(event) {
            if (event.key === 'Escape' && this.dragging) { event.preventDefault(); this.endDrag(true); return; }
            if (preview && this.stacked) return;
            const rtl = this.root && getComputedStyle(this.root).direction === 'rtl';
            const direction = rtl ? -1 : 1;
            if (preview) {
                const keys = { ArrowLeft: this.ratio - 0.02 * direction, ArrowRight: this.ratio + 0.02 * direction, Home: this.minimumRatio, End: this.maximumRatio, Enter: previewDefault() };
                if (!(event.key in keys)) return;
                event.preventDefault();
                this.preferredRatio = event.key === 'Enter' ? keys.Enter : Math.max(this.minimumRatio, Math.min(this.maximumRatio, keys[event.key]));
                this.save(); return;
            }
            const previous = this.availableStops.filter(stop => stop < this.ratio - 0.000001).at(-1) ?? this.preferredRatio;
            const next = this.availableStops.find(stop => stop > this.ratio + 0.000001) ?? this.preferredRatio;
            const keys = { ArrowLeft: direction === 1 ? previous : next, ArrowRight: direction === 1 ? next : previous, Home: this.availableStops[0], End: this.availableStops.at(-1), Enter: defaultRatio };
            if (!(event.key in keys)) return;
            event.preventDefault();
            this.preferredRatio = keys[event.key];
            this.save();
        },
        destroy() { this.endDrag(true); this.hideSplitterHint(); this.observer?.disconnect(); },
    };
}
