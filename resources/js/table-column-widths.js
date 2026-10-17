import { interpolate } from './ui-wording.js';
export function tableColumnWidths(config) {
    let root, observer, sizeObserver, frame, activeDrag, observedWidth;
    let widths = {};
    let columns = [];
    let paintedCells = [];
    let destroyed = false;
    const key = `aquestia:columns:${config.user}:admin:${config.table}:v1`;
    const clamp = width => Math.max(64, Math.min(640, Math.round(width)));
    const save = () => { try { localStorage.setItem(key, JSON.stringify(widths)); } catch {} };
    const clear = cell => {
        for (const property of ['width', 'min-width', 'max-width']) cell.style.removeProperty(property);
    };
    const paint = () => {
        const sized = window.innerWidth >= 768 && columns.some(column => Object.hasOwn(widths, column.name));
        let total = 0;
        for (const { name, header, cells, natural } of columns) {
            const width = sized ? (widths[name] ?? natural) : null;
            for (const cell of cells) {
                if (width !== null) {
                    const pixels = `${width}px`;
                    if (cell.style.width !== pixels) {
                        cell.style.width = pixels;
                        cell.style.minWidth = pixels;
                        cell.style.maxWidth = pixels;
                    }
                } else clear(cell);
            }
            total += width ?? natural;
            const handle = header.querySelector('.catalog-column-resize');
            if (handle) handle.setAttribute('aria-valuenow', String(Math.round(width ?? natural)));
        }
        root.classList.toggle('catalog-table-has-widths', sized);
        if (sized) root.style.setProperty('--catalog-table-width', `${total}px`);
        else root.style.removeProperty('--catalog-table-width');
    };
    const finish = cancel => {
        if (!activeDrag) return;
        const { name, original } = activeDrag;
        if (cancel) { if (original !== undefined) widths[name] = original; else delete widths[name]; }
        activeDrag = null;
        root.classList.toggle('catalog-table-is-resizing', false);
        paint();
        if (!cancel) save();
    };
    const resetColumn = name => {
        finish(true);
        delete widths[name];
        hydrate();
        save();
    };
    const addHandle = header => {
        if (header.querySelector('.catalog-column-resize')) return;
        const name = header.dataset.widthColumn;
        const handle = document.createElement('button');
        handle.type = 'button';
        handle.className = 'catalog-column-resize';
        handle.setAttribute('wire:ignore', '');
        handle.setAttribute('role', 'slider');
        handle.setAttribute('aria-orientation', 'horizontal');
        const help = interpolate(config.labels.resize_column, {column: header.dataset.widthLabel || name});
        handle.setAttribute('aria-label', help);
        handle.setAttribute('title', help);
        handle.setAttribute('aria-valuemin', '64');
        handle.setAttribute('aria-valuemax', '640');
        handle.addEventListener('click', event => { event.stopPropagation(); event.preventDefault(); });
        handle.addEventListener('dblclick', event => { event.stopPropagation(); event.preventDefault(); resetColumn(name); });
        handle.addEventListener('pointerdown', event => {
            if (event.button !== 0 || window.innerWidth < 768) return;
            event.stopPropagation(); event.preventDefault();
            finish(true);
            hydrate();
            activeDrag = { pointer: event.pointerId, name, x: event.clientX, start: header.getBoundingClientRect().width, original: widths[name], direction: getComputedStyle(root).direction === 'rtl' ? -1 : 1 };
            root.classList.toggle('catalog-table-is-resizing', true);
            handle.focus({ preventScroll: true });
            handle.setPointerCapture(event.pointerId);
        });
        handle.addEventListener('pointermove', event => {
            if (activeDrag?.pointer !== event.pointerId) return;
            widths[name] = clamp(activeDrag.start + (event.clientX - activeDrag.x) * activeDrag.direction);
            paint();
        });
        handle.addEventListener('pointerup', event => { if (activeDrag?.pointer === event.pointerId) finish(false); });
        handle.addEventListener('pointercancel', () => finish(true));
        handle.addEventListener('lostpointercapture', () => finish(true));
        handle.addEventListener('keydown', event => {
            if (event.key === 'Escape' && activeDrag) { event.preventDefault(); event.stopPropagation(); finish(true); return; }
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End', 'Delete', 'Backspace'].includes(event.key) || window.innerWidth < 768) return;
            event.preventDefault(); event.stopPropagation();
            if (['Delete', 'Backspace'].includes(event.key)) { resetColumn(name); return; }
            const direction = getComputedStyle(root).direction === 'rtl' ? -1 : 1;
            widths[name] = event.key === 'Home' ? 96 : event.key === 'End' ? 320 : clamp((widths[name] ?? header.getBoundingClientRect().width) + (event.key === 'ArrowLeft' ? -16 : 16) * direction);
            paint(); save();
        });
        header.append(handle);
    };
    const hydrate = () => {
        frame = null;
        if (destroyed || root.getBoundingClientRect().width === 0) return;
        for (const cell of paintedCells) clear(cell);
        root.classList.toggle('catalog-table-has-widths', false);
        root.style.removeProperty('--catalog-table-width');
        const headers = [...root.querySelectorAll('thead th')].filter(header => header.closest('[data-width-table]') === root && header.getBoundingClientRect().width > 0);
        const cells = [...root.querySelectorAll('[data-width-column]')].filter(cell => cell.closest('[data-width-table]') === root);
        // Measure the natural layout before restoring any saved width, including selection and action columns.
        columns = headers.map(header => ({ name: header.dataset.widthColumn, header, natural: header.getBoundingClientRect().width, cells: header.dataset.widthColumn ? cells.filter(cell => cell.dataset.widthColumn === header.dataset.widthColumn) : [header] }));
        paintedCells = columns.flatMap(column => column.cells);
        for (const { name, header } of columns) if (name) addHandle(header);
        paint();
    };
    const reset = event => {
        if (event.detail.table !== config.table) return;
        finish(true);
        widths = {}; try { localStorage.removeItem(key); } catch {}
        hydrate();
    };
    const queueHydrate = () => { if (!frame) frame = requestAnimationFrame(hydrate); };
    const resize = () => { finish(true); hydrate(); };
    return {
        init() {
            root = this.$el.closest('[data-width-table]'); if (!root) return;
            try { const saved = JSON.parse(localStorage.getItem(key)); if (saved && typeof saved === 'object' && !Array.isArray(saved)) for (const [name, width] of Object.entries(saved)) if (Number.isFinite(width) && width >= 64 && width <= 640) widths[name] = width; } catch {}
            this.$nextTick(hydrate);
            observer = new MutationObserver(queueHydrate);
            observer.observe(root, { childList: true, subtree: true });
            sizeObserver = new ResizeObserver(([entry]) => {
                const width = entry.contentRect.width;
                if (width === observedWidth) return;
                observedWidth = width;
                queueHydrate();
            });
            sizeObserver.observe(root);
            window.addEventListener('table-widths-reset', reset);
            window.addEventListener('resize', resize);
        },
        destroy() {
            destroyed = true;
            observer?.disconnect(); sizeObserver?.disconnect(); if (frame) cancelAnimationFrame(frame);
            window.removeEventListener('table-widths-reset', reset);
            window.removeEventListener('resize', resize);
            activeDrag = null;
            for (const cell of paintedCells) clear(cell);
            for (const { header } of columns) header.querySelector('.catalog-column-resize')?.remove();
            root?.classList.toggle('catalog-table-is-resizing', false);
        },
    };
}
