const presets = { small: 640, medium: 960, large: 1280 };

export function workspaceColumnManager(element, operation) {
    const manager = element.closest('.fi-modal-window')?.querySelector('.fi-ta-col-manager');
    if (!manager || !['applyTableColumnManager', 'resetDeferredColumns'].includes(operation)) return;
    return window.Alpine.$data(manager)[operation]();
}

export function workspaceDialog(config) {
    let windowElement, toolbar, resizeListener, observer;
    let destroyed = false;
    const defaultWidth = config.width ?? 960;
    let width = defaultWidth;
    const storageKey = `aquestia:dialog:${config.user}:admin:${config.purpose}:v1`;
    const viewportMax = () => Math.max(280, window.innerWidth - (window.innerWidth < 640 ? 0 : 32));
    const clamp = value => Math.max(Math.min(320, viewportMax()), Math.min(viewportMax(), value));
    const steps = () => [...new Set([...Object.values(presets), viewportMax()].map(clamp))].sort((a, b) => a - b);
    const nextWidth = direction => direction < 0
        ? steps().filter(value => value < clamp(width)).at(-1)
        : steps().find(value => value > clamp(width));
    const paint = () => {
        if (!windowElement || destroyed) return;
        const pixels = `${clamp(width)}px`;
        if (windowElement.style.getPropertyValue('width') !== pixels) windowElement.style.setProperty('width', pixels);
        if (windowElement.style.getPropertyValue('max-width') !== '100vw') windowElement.style.setProperty('max-width', '100vw');
        for (const button of toolbar?.querySelectorAll('[data-width-step]') ?? []) button.disabled = nextWidth(Number(button.dataset.widthStep)) === undefined;
    };
    const save = () => { try { localStorage.setItem(storageKey, JSON.stringify(Math.round(width))); } catch {} };
    const addControls = () => {
        const header = windowElement.querySelector('.fi-modal-header');
        if (!header || header.querySelector('.catalog-dialog-width-controls')) return;
        toolbar = document.createElement('div');
        toolbar.className = 'catalog-dialog-width-controls';
        toolbar.setAttribute('role', 'group');
        toolbar.setAttribute('aria-label', 'Dialog width');
        toolbar.setAttribute('wire:ignore', '');
        const button = (label, run) => {
            const element = document.createElement('button'); element.type = 'button'; element.textContent = label;
            element.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); run(); });
            toolbar.append(element); return element;
        };
        for (const [label, direction] of [['Narrower', -1], ['Wider', 1]]) {
            button(label, () => { const next = nextWidth(direction); if (next === undefined) return; width = next; paint(); save(); }).dataset.widthStep = String(direction);
        }
        button('Reset width', () => { width = defaultWidth; try { localStorage.removeItem(storageKey); } catch {} paint(); });
        header.append(toolbar);
        paint();
    };
    return {
        init() {
            windowElement = this.$el;
            try { const saved = JSON.parse(localStorage.getItem(storageKey)); if (Number.isFinite(saved) && saved >= 320 && saved <= 4096) width = saved; } catch {}
            this.$nextTick(() => {
                if (destroyed) return;
                addControls();
                paint();
                observer = new MutationObserver(mutations => {
                    if (destroyed || !mutations.some(mutation => mutation.type === 'childList' || mutation.target === windowElement)) return;
                    addControls();
                    paint();
                });
                observer.observe(windowElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
            });
            resizeListener = () => paint(); window.addEventListener('resize', resizeListener);
        },
        destroy() {
            destroyed = true;
            observer?.disconnect(); window.removeEventListener('resize', resizeListener);
            toolbar?.remove();
        },
    };
}
