import { browserProfile } from './admin-profile.js';

const presets = [480, 560, 640, 720, 800, 880, 960, 1120, 1280, 1440];

export function workspaceColumnManager(element, operation) {
    const manager = element.closest('.fi-modal-window')?.querySelector('.fi-ta-col-manager');
    if (!manager || !['applyTableColumnManager', 'resetDeferredColumns'].includes(operation)) return;
    return window.Alpine.$data(manager)[operation]();
}

export function workspaceDialog(config) {
    let windowElement, toolbar, handle, resizeListener, observer, drag;
    let destroyed = false;
    const personal = config.allowPersonalWidth ? browserProfile(config.user, 'global').read()?.appearance?.settings : null;
    const preferredWidth = { small: 640, medium: 960, large: 1280 }[personal?.[config.slideOver ? 'slide_over_width' : 'modal_width']];
    const defaultWidth = preferredWidth ?? config.width ?? 960;
    let width = defaultWidth;
    const legacyKey = `aquestia:dialog:${config.user}:admin:${config.purpose}:v1`;
    const storageKey = window.location?.pathname ? `${legacyKey}:page:${window.location.pathname}` : legacyKey;
    const viewportMax = () => Math.max(280, window.innerWidth - (window.innerWidth < 640 ? 0 : 32));
    const clamp = value => Math.max(Math.min(320, viewportMax()), Math.min(viewportMax(), value));
    const steps = () => [...new Set([...presets, viewportMax()].map(clamp))].sort((a, b) => a - b);
    const nextWidth = direction => direction < 0
        ? steps().filter(value => value < clamp(width)).at(-1)
        : steps().find(value => value > clamp(width));
    const paint = () => {
        if (!windowElement || destroyed) return;
        const pixels = `${clamp(width)}px`;
        if (windowElement.style.getPropertyValue('width') !== pixels) windowElement.style.setProperty('width', pixels);
        if (windowElement.style.getPropertyValue('max-width') !== '100vw') windowElement.style.setProperty('max-width', '100vw');
        for (const button of toolbar?.querySelectorAll('[data-width-step]') ?? []) button.disabled = nextWidth(Number(button.dataset.widthStep)) === undefined;
        if (handle) {
            handle.setAttribute('aria-valuemin', String(steps()[0]));
            handle.setAttribute('aria-valuemax', String(steps().at(-1)));
            handle.setAttribute('aria-valuenow', String(Math.round(clamp(width))));
            handle.setAttribute('aria-valuetext', `${Math.round(clamp(width))} px`);
        }
    };
    const save = () => { try { localStorage.setItem(storageKey, JSON.stringify(Math.round(width))); } catch {} };
    const addControls = () => {
        const header = windowElement.querySelector('.fi-modal-header');
        if (!header || header.querySelector('.catalog-dialog-width-controls')) return;
        toolbar = document.createElement('div');
        toolbar.className = 'catalog-dialog-width-controls';
        toolbar.setAttribute('role', 'group');
        toolbar.setAttribute('aria-label', config.labels.width);
        toolbar.setAttribute('wire:ignore', '');
        const button = (name, label, run) => {
            const element = document.createElement('button'); element.type = 'button';
            element.setAttribute('aria-label', label); element.title = label;
            const icon = document.createElement('span'); icon.className = 'catalog-dialog-control-icon'; icon.setAttribute('aria-hidden', 'true'); icon.innerHTML = config.icons?.[name] ?? '';
            const text = document.createElement('span'); text.className = 'catalog-dialog-control-label'; text.textContent = label;
            element.append(icon); element.append(text);
            element.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); run(); });
            toolbar.append(element); return element;
        };
        for (const [name, label, direction] of [['narrower', config.labels.narrower, -1], ['wider', config.labels.wider, 1]]) {
            button(name, label, () => { const next = nextWidth(direction); if (next === undefined) return; width = next; paint(); save(); }).dataset.widthStep = String(direction);
        }
        button('reset', config.labels.reset, () => { width = defaultWidth; try { localStorage.setItem(storageKey, 'null'); } catch {} paint(); });
        header.append(toolbar);
        paint();
    };
    const reset = () => { width = defaultWidth; try { localStorage.setItem(storageKey, 'null'); } catch {} paint(); };
    const endDrag = cancel => {
        if (!drag) return;
        if (cancel) width = drag.width;
        drag = null;
        handle?.classList.toggle('is-dragging', false);
        paint();
        if (!cancel) save();
    };
    const addHandle = () => {
        if (!config.slideOver || windowElement.querySelector('.catalog-dialog-resize-handle')) return;
        handle = document.createElement('div');
        handle.className = 'catalog-dialog-resize-handle';
        handle.setAttribute('role', 'separator'); handle.setAttribute('tabindex', '0');
        handle.setAttribute('aria-orientation', 'vertical'); handle.setAttribute('aria-label', config.labels.resize ?? config.labels.width);
        handle.setAttribute('wire:ignore', '');
        handle.addEventListener('pointerdown', event => {
            if (event.button !== 0) return;
            event.preventDefault(); event.stopPropagation();
            drag = { x: event.clientX, width, visibleWidth: clamp(width) };
            handle.focus({ preventScroll: true }); handle.setPointerCapture(event.pointerId);
            handle.classList.toggle('is-dragging', true);
        });
        handle.addEventListener('pointermove', event => {
            if (!drag) return;
            const direction = getComputedStyle(windowElement).direction === 'rtl' ? -1 : 1;
            const target = clamp(drag.visibleWidth + (drag.x - event.clientX) * direction);
            width = steps().reduce((best, step) => Math.abs(step - target) < Math.abs(best - target) ? step : best, steps()[0]);
            paint();
        });
        handle.addEventListener('pointerup', () => endDrag(false));
        handle.addEventListener('pointercancel', () => endDrag(true));
        handle.addEventListener('lostpointercapture', () => endDrag(true));
        handle.addEventListener('dblclick', reset);
        handle.addEventListener('keydown', event => {
            if (event.key === 'Escape' && drag) { event.preventDefault(); event.stopPropagation(); endDrag(true); return; }
            const rtl = getComputedStyle(windowElement).direction === 'rtl';
            const direction = { ArrowLeft: rtl ? -1 : 1, ArrowRight: rtl ? 1 : -1 }[event.key];
            if (direction) {
                event.preventDefault(); event.stopPropagation();
                const next = nextWidth(direction);
                if (next !== undefined) { width = next; paint(); save(); }
            } else if (['Home', 'End', 'Enter'].includes(event.key)) {
                event.preventDefault(); event.stopPropagation();
                if (event.key === 'Enter') reset();
                else { width = event.key === 'Home' ? steps()[0] : steps().at(-1); paint(); save(); }
            }
        });
        windowElement.append(handle);
    };
    return {
        init() {
            windowElement = this.$el;
            try { const saved = JSON.parse(localStorage.getItem(storageKey) ?? localStorage.getItem(legacyKey)); if (Number.isFinite(saved) && saved >= 320 && saved <= 4096) width = saved; } catch {}
            this.$nextTick(() => {
                if (destroyed) return;
                addControls();
                addHandle();
                paint();
                observer = new MutationObserver(mutations => {
                    if (destroyed || !mutations.some(mutation => mutation.type === 'childList' || mutation.target === windowElement)) return;
                    addControls();
                    addHandle();
                    paint();
                });
                observer.observe(windowElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
            });
            resizeListener = () => paint(); window.addEventListener('resize', resizeListener);
        },
        destroy() {
            destroyed = true;
            observer?.disconnect(); window.removeEventListener('resize', resizeListener);
            toolbar?.remove(); handle?.remove(); drag = null;
        },
    };
}
