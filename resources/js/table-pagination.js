export function tablePagination() {
    let root, observer, sizeObserver, frame;
    const refresh = () => {
        frame = null;
        if (!root.clientWidth) return;
        for (const footer of root.querySelectorAll('.fi-pagination')) {
            if (footer.closest('.catalog-workspace-table') !== root || footer.classList.contains('fi-simple')) continue;
            const pages = footer.querySelector('.fi-pagination-items');
            if (!pages || root.clientWidth < 480) {
                if (footer.classList.contains('catalog-pagination-overflow')) footer.classList.remove('catalog-pagination-overflow');
                continue;
            }
            const selector = footer.querySelector('.fi-pagination-records-per-page-select-ctn');
            const summary = root.clientWidth >= 832 ? footer.querySelector('.fi-pagination-overview') : null;
            const required = pages.getBoundingClientRect().width + (selector?.getBoundingClientRect().width ?? 0)
                + (summary?.getBoundingClientRect().width ?? 0) + 24 + (summary && selector ? 24 : selector || summary ? 12 : 0);
            const overflow = required > footer.clientWidth;
            if (footer.classList.contains('catalog-pagination-overflow') !== overflow) footer.classList.toggle('catalog-pagination-overflow', overflow);
        }
    };
    const schedule = () => { if (!frame) frame = requestAnimationFrame(refresh); };
    return {
        init() {
            root = this.$el.closest('.catalog-workspace-table');
            if (!root) return;
            this.$nextTick(refresh);
            observer = new MutationObserver(schedule);
            observer.observe(root, { childList: true, characterData: true, attributes: true, attributeFilter: ['class'], subtree: true });
            sizeObserver = new ResizeObserver(schedule);
            sizeObserver.observe(root);
        },
        destroy() {
            observer?.disconnect(); sizeObserver?.disconnect();
            if (frame) cancelAnimationFrame(frame);
        },
    };
}
