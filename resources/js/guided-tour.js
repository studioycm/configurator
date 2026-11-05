const escapeText = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);

export function guidedTour(config, dependencies = {}) {
    const document = dependencies.document ?? globalThis.document;
    const window = dependencies.window ?? globalThis.window;
    const observe = dependencies.observe ?? (callback => new MutationObserver(callback));
    let driver = null, observer = null, launcher = null, element = null;
    let keyboard, navigation, pagehide;
    let items = [];
    const resolve = step => {
        const selector = config.targets?.[step?.target];
        const target = selector ? document.querySelector(selector) : null;
        return target?.isConnected && target.getClientRects().length && !target.closest('[hidden], [inert], [aria-hidden="true"]') ? target : null;
    };

    return {
        active: false,
        notice: '',
        index: 0,
        init() {
            keyboard = event => {
                if (!this.active) return;
                if (event.key === 'Escape') {
                    event.preventDefault(); event.stopPropagation(); this.close();
                } else if (['ArrowRight', 'ArrowLeft'].includes(event.key) && !event.target?.closest?.('input, textarea, select, [contenteditable="true"]')) {
                    event.preventDefault(); this.move(event.key === 'ArrowRight' ? 1 : -1);
                }
            };
            navigation = () => this.close();
            pagehide = () => this.close();
            document.addEventListener('keydown', keyboard, true);
            document.addEventListener('livewire:navigating', navigation);
            window.addEventListener('pagehide', pagehide);
        },
        start(key, source) {
            this.close();
            launcher = source?.currentTarget ?? source ?? null;
            this.notice = '';
            const tour = config.tours?.find(tour => tour.key === key);
            items = (tour?.steps ?? []).filter(step => step.page === config.page && config.targets?.[step.target]);
            let first = items.findIndex(step => resolve(step));
            if (first < 0) { this.notice = config.labels.unavailable; return; }
            this.index = first;
            element = resolve(items[first]);
            driver = dependencies.createDriver({
                animate: !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches,
                allowKeyboardControl: false,
                allowClose: true,
                disableActiveInteraction: true,
                showProgress: true,
                progressText: '{{current}} / {{total}}',
                popoverClass: 'ari-guided-tour',
                nextBtnText: config.labels.next,
                prevBtnText: config.labels.previous,
                doneBtnText: config.labels.done,
                closeBtnLabel: config.labels.close,
                steps: items.map(step => ({
                    element: () => resolve(step),
                    popover: { title: escapeText(step.title), description: escapeText(step.description), side: step.placement, align: 'start' },
                })),
                onNextClick: () => this.move(1),
                onPrevClick: () => this.move(-1),
                onDoneClick: () => this.close(),
                onCloseClick: () => this.close(),
                onDestroyed: () => this.finished(),
            });
            this.active = true;
            driver.drive(first);
            observer = observe(() => {
                if (this.active && (resolve(items[this.index]) !== element || !element?.isConnected)) this.close(config.labels.changed);
            });
            observer.observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['hidden', 'class', 'style', 'inert', 'aria-hidden'] });
        },
        move(direction) {
            if (!driver || !this.active) return;
            let next = this.index + direction;
            while (next >= 0 && next < items.length && !resolve(items[next])) next += direction;
            if (next < 0) return;
            if (next >= items.length) { this.close(); return; }
            this.index = next;
            element = resolve(items[next]);
            driver.moveTo(next);
        },
        finished() {
            driver = null;
            observer?.disconnect(); observer = null;
            element = null;
            this.active = false;
            if (launcher?.isConnected) launcher.focus();
            launcher = null;
        },
        close(message = '') {
            const current = driver;
            driver = null;
            observer?.disconnect(); observer = null;
            current?.destroy();
            this.finished();
            this.notice = message;
        },
        destroy() {
            this.close();
            document.removeEventListener('keydown', keyboard, true);
            document.removeEventListener('livewire:navigating', navigation);
            window.removeEventListener('pagehide', pagehide);
        },
    };
}
