let previewId = 0;

export function itemCountPreview(key, parentId) {
    let tooltip;
    let loaded;
    let visible = false;
    return {
        async show() {
            visible = true;
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.className = 'catalog-item-preview';
                tooltip.setAttribute('role', 'tooltip');
                tooltip.id = `catalog-items-preview-${++previewId}`;
                this.$el.querySelector('button, a')?.setAttribute('aria-describedby', tooltip.id);
                document.body.append(tooltip);
            }
            tooltip.textContent = 'Loading items…';
            const box = this.$el.getBoundingClientRect();
            tooltip.style.left = `${Math.max(8, Math.min(box.left, innerWidth - 280))}px`;
            tooltip.style.top = `${Math.min(box.bottom + 4, innerHeight - 150)}px`;
            tooltip.hidden = false;
            try {
                const request = loaded ??= this.$wire.itemCountPreview(key, parentId);
                const lines = await request;
                if (visible && loaded === request) tooltip.textContent = lines.join('\n');
            } catch {
                loaded = null;
                if (visible) tooltip.textContent = 'Open list to inspect items';
            }
        },
        hide() { visible = false; loaded = null; if (tooltip) tooltip.hidden = true; },
        destroy() { visible = false; tooltip?.remove(); },
    };
}
