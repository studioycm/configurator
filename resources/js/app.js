import './catalog-filters';
import { catalogShell } from './catalog-shell';
import { itemCountPreview } from './item-count-preview';
import { workspaceDialog, workspaceColumnManager } from './workspace-dialogs';
import { tableColumnWidths } from './table-column-widths';

window.workspaceColumnManager = workspaceColumnManager;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('catalogShell', catalogShell);
    window.Alpine.data('itemCountPreview', itemCountPreview);
    window.Alpine.data('workspaceDialog', workspaceDialog);
    window.Alpine.data('tableColumnWidths', tableColumnWidths);
}, { once: true });

if (window.Alpine) {
    window.Alpine.data('itemCountPreview', itemCountPreview);
    window.Alpine.data('workspaceDialog', workspaceDialog);
    window.Alpine.data('tableColumnWidths', tableColumnWidths);
}

window.addEventListener('admin-appearance-saved', event => {
    for (const [key, value] of Object.entries(event.detail.variables ?? {})) {
        if (/^--aquestia-[a-z-]+$/.test(key) && /^\d+px$/.test(value)) document.documentElement.style.setProperty(key, value);
    }
});
