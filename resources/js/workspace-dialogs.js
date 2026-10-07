const presets = { small: 640, medium: 960, large: 1280 };

export function workspaceColumnManager(element, operation) {
    const manager = element.closest('.fi-modal-window')?.querySelector('.fi-ta-col-manager');
    if (!manager || !['applyTableColumnManager', 'resetDeferredColumns'].includes(operation)) return;
    return window.Alpine.$data(manager)[operation]();
}

export function workspaceDialog(config) {
    let windowElement, toolbar, handle, resizeListener, observer, activeDrag;
    let width = config.width ?? 960;
    let restoreWidth = width;
    let maximized = false;
    const storageKey = `aquestia:dialog:${config.user}:admin:${config.purpose}:v1`;
    const viewportMax = () => Math.max(280, window.innerWidth - (window.innerWidth < 640 ? 0 : 32));
    const clamp = value => Math.max(Math.min(320, viewportMax()), Math.min(viewportMax(), value));
    const paint = () => {
        if (!windowElement) return;
        windowElement.style.setProperty('width', `${maximized ? viewportMax() : clamp(width)}px`);
        windowElement.style.setProperty('max-width', '100vw');
        if (handle) handle.hidden = window.innerWidth < 768 || !config.slideOver;
        const button = toolbar?.querySelector('[data-maximize]');
        if (button) { button.textContent = maximized ? 'Restore' : 'Maximize'; button.setAttribute('aria-pressed', String(maximized)); }
    };
    const save = () => { try { localStorage.setItem(storageKey, JSON.stringify(Math.round(width))); } catch {} };
    const finishDrag = cancel => {
        if (!activeDrag) return;
        if (cancel) width = activeDrag.startWidth;
        activeDrag = null;
        paint();
        if (!cancel) save();
    };
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
        for (const [name, pixels] of Object.entries(presets)) button(name[0].toUpperCase() + name.slice(1), () => { maximized = false; width = pixels; paint(); save(); });
        button('Maximize', () => { if (maximized) { width = restoreWidth; maximized = false; } else { restoreWidth = width; maximized = true; } paint(); }).dataset.maximize = '';
        button('Reset width', () => { maximized = false; width = config.width; try { localStorage.removeItem(storageKey); } catch {} paint(); });
        header.append(toolbar);
        paint();
    };
    return {
        init() {
            windowElement = this.$el;
            try { const saved = JSON.parse(localStorage.getItem(storageKey)); if (Number.isFinite(saved) && saved >= 320 && saved <= 4096) width = saved; } catch {}
            this.$nextTick(() => {
                addControls();
                handle = document.createElement('button'); handle.type = 'button'; handle.className = 'catalog-dialog-resize-handle';
                handle.setAttribute('aria-label', 'Resize slide-over. Use left and right arrows, Home or End.'); handle.setAttribute('wire:ignore', '');
                handle.addEventListener('pointerdown', event => {
                    if (!config.slideOver || window.innerWidth < 768 || event.button !== 0) return;
                    event.preventDefault(); event.stopPropagation(); maximized = false;
                    activeDrag = { pointer: event.pointerId, x: event.clientX, startWidth: clamp(width) };
                    handle.setPointerCapture(event.pointerId);
                });
                handle.addEventListener('pointermove', event => { if (activeDrag?.pointer !== event.pointerId) return; width = clamp(activeDrag.startWidth + activeDrag.x - event.clientX); paint(); });
                handle.addEventListener('pointerup', event => { if (activeDrag?.pointer === event.pointerId) finishDrag(false); });
                handle.addEventListener('pointercancel', () => finishDrag(true));
                handle.addEventListener('lostpointercapture', () => finishDrag(true));
                handle.addEventListener('keydown', event => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault(); maximized = false;
                    width = event.key === 'Home' ? 640 : event.key === 'End' ? viewportMax() : clamp(width + (event.key === 'ArrowLeft' ? 32 : -32)); paint(); save();
                });
                windowElement.append(handle); paint();
                observer = new MutationObserver(() => {
                    addControls();
                    if (handle && !handle.isConnected) windowElement.append(handle);
                });
                observer.observe(windowElement, { childList: true, subtree: true });
            });
            resizeListener = () => paint(); window.addEventListener('resize', resizeListener);
        },
        destroy() {
            observer?.disconnect(); window.removeEventListener('resize', resizeListener);
            toolbar?.remove(); handle?.remove(); activeDrag = null;
        },
    };
}
