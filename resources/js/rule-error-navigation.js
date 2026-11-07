export function ruleErrorNavigation(targetId) {
    return {
        reveal() {
            const root = this.$el.closest('[wire\\:id]');
            const anchor = [...(root?.querySelectorAll('[id], label[for]') ?? [])].find(element => element.id === targetId || element.id === `${targetId}-label` || element.getAttribute('for') === targetId);
            const field = anchor?.closest('[data-field-wrapper]') ?? anchor;
            if (!field) return;
            for (let parent = field.parentElement; parent && parent !== root; parent = parent.parentElement) {
                parent.dispatchEvent(new CustomEvent('expand'));
            }
            this.$nextTick(() => {
                const controls = [anchor, ...field.querySelectorAll('input, select, textarea'), ...field.querySelectorAll('[role="combobox"]'), ...field.querySelectorAll('button, [tabindex]'), field];
                const control = controls.find(element => element.matches('input, select, textarea, button, [tabindex]') && !element.disabled && !element.hidden && element.getClientRects().length > 0);
                field.scrollIntoView({ block: 'center', behavior: 'instant' });
                control?.focus({ preventScroll: true });
            });
        },
    };
}
