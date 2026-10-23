const interactive = 'a, button, input, select, textarea, label, summary, [role="button"], [role="link"], [contenteditable], [x-sortable-handle]';

export function workspaceTableSelection() {
    return {
        toggle(event) {
            if (event.defaultPrevented || event.button !== 0 || event.detail > 1 || globalThis.getSelection?.()?.toString()) return;
            if (event.target.closest(interactive)) return;
            const row = event.target.closest('.fi-ta-row, .fi-ta-record');
            if (!row || row.closest('[data-workspace-row-selection]') !== this.$el || row.matches('[aria-disabled="true"], [data-loading], [wire\\:loading]')) return;
            const table = this.$el.querySelector?.('.fi-ta-table');
            if (table && row.closest('table') !== table) return;
            const checkbox = row.querySelector('.fi-ta-record-checkbox');
            if (!checkbox || checkbox.disabled || checkbox.matches(':disabled') || checkbox.closest('[inert], [aria-busy="true"], [data-loading]')) return;
            if (row.closest('table') && row.closest('table') !== checkbox.closest('table')) return;
            checkbox.click();
        },
    };
}
