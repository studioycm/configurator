export function tableColumnWidths(config) {
    let root, observer, frame, activeDrag;
    let widths = {};
    const key = `aquestia:columns:${config.user}:admin:${config.table}:v1`;
    const clamp = width => Math.max(64, Math.min(640, Math.round(width)));
    const save = () => { try { localStorage.setItem(key, JSON.stringify(widths)); } catch {} };
    const cells = name => [...root.querySelectorAll('[data-width-column]')].filter(cell => cell.dataset.widthColumn === name && cell.closest('[data-width-table]') === root);
    const paint = name => {
        for (const cell of cells(name)) {
            if (widths[name]) {
                cell.style.width = `${widths[name]}px`; cell.style.minWidth = `${widths[name]}px`; cell.style.maxWidth = `${widths[name]}px`;
            } else { cell.style.removeProperty('width'); cell.style.removeProperty('min-width'); cell.style.removeProperty('max-width'); }
            const handle = cell.querySelector('.catalog-column-resize');
            if (handle) handle.setAttribute('aria-valuenow', String(widths[name] ?? Math.round(cell.getBoundingClientRect().width)));
        }
        root.classList.toggle('catalog-table-has-widths', Object.keys(widths).length > 0);
    };
    const finish = cancel => {
        if (!activeDrag) return;
        const { name, original } = activeDrag;
        if (cancel) { if (original) widths[name] = original; else delete widths[name]; }
        activeDrag = null; paint(name); if (!cancel) save();
    };
    const hydrate = () => {
        frame = null;
        for (const header of root.querySelectorAll('th[data-width-column]')) {
            if (header.closest('[data-width-table]') !== root) continue;
            const name = header.dataset.widthColumn;
            if (!header.querySelector('.catalog-column-resize')) {
                const handle = document.createElement('button'); handle.type = 'button'; handle.className = 'catalog-column-resize'; handle.setAttribute('wire:ignore', '');
                handle.setAttribute('role', 'slider'); handle.setAttribute('aria-label', `Width of ${header.dataset.widthLabel || name}. Arrow keys resize; Home small; End wide; Delete resets.`);
                handle.setAttribute('aria-valuemin', '64'); handle.setAttribute('aria-valuemax', '640');
                handle.addEventListener('click', event => { event.stopPropagation(); event.preventDefault(); });
                handle.addEventListener('pointerdown', event => {
                    if (event.button !== 0 || window.innerWidth < 768) return;
                    event.stopPropagation(); event.preventDefault();
                    activeDrag = { pointer: event.pointerId, name, x: event.clientX, start: header.getBoundingClientRect().width, original: widths[name] };
                    handle.setPointerCapture(event.pointerId);
                });
                handle.addEventListener('pointermove', event => { if (activeDrag?.pointer !== event.pointerId) return; widths[name] = clamp(activeDrag.start + event.clientX - activeDrag.x); paint(name); });
                handle.addEventListener('pointerup', event => { if (activeDrag?.pointer === event.pointerId) finish(false); });
                handle.addEventListener('pointercancel', () => finish(true));
                handle.addEventListener('lostpointercapture', () => finish(true));
                handle.addEventListener('keydown', event => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End', 'Delete', 'Backspace'].includes(event.key)) return;
                    event.preventDefault(); event.stopPropagation();
                    if (['Delete', 'Backspace'].includes(event.key)) delete widths[name];
                    else widths[name] = event.key === 'Home' ? 96 : event.key === 'End' ? 320 : clamp((widths[name] ?? header.getBoundingClientRect().width) + (event.key === 'ArrowLeft' ? -16 : 16));
                    paint(name); save();
                });
                header.append(handle);
            }
            paint(name);
        }
    };
    const reset = event => {
        if (event.detail.table !== config.table) return;
        widths = {}; try { localStorage.removeItem(key); } catch {}
        for (const cell of root.querySelectorAll('[data-width-column]')) paint(cell.dataset.widthColumn);
    };
    return {
        init() {
            root = this.$el.closest('[data-width-table]'); if (!root) return;
            try { const saved = JSON.parse(localStorage.getItem(key)); if (saved && typeof saved === 'object' && !Array.isArray(saved)) for (const [name, width] of Object.entries(saved)) if (Number.isFinite(width) && width >= 64 && width <= 640) widths[name] = width; } catch {}
            this.$nextTick(hydrate);
            observer = new MutationObserver(() => { if (!frame) frame = requestAnimationFrame(hydrate); });
            observer.observe(root, { childList: true, subtree: true });
            window.addEventListener('table-widths-reset', reset);
        },
        destroy() {
            observer?.disconnect(); if (frame) cancelAnimationFrame(frame); window.removeEventListener('table-widths-reset', reset); activeDrag = null;
        },
    };
}
