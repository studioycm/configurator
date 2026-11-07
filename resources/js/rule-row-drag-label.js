import { interpolate } from './ui-wording.js';

export function ruleRowDragLabel(config, { Observer = globalThis.MutationObserver } = {}) {
    return {
        observer: null,
        init() {
            this.$nextTick(() => {
                const header = this.$el.closest('.fi-fo-repeater-item-header');
                if (!header) return;
                const update = () => {
                    const name = header.querySelector('.fi-fo-repeater-item-header-label')?.textContent.trim() || config.fallback;
                    const label = interpolate(config.template, { name });
                    for (const attribute of ['aria-label', 'title']) {
                        if (this.$el.getAttribute(attribute) !== label) this.$el.setAttribute(attribute, label);
                    }
                };
                update();
                this.observer = new Observer(update);
                this.observer.observe(header, { childList: true, characterData: true, subtree: true, attributes: true, attributeFilter: ['aria-label', 'title'] });
            });
        },
        destroy() {
            this.observer?.disconnect();
        },
    };
}
