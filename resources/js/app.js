import { interpolate } from './ui-wording';
import { localTableProfile, adminBrowserProfile, rememberSchemaState, localTraceProfile, localSectionProfile, localOrderProfile, rememberOrderControls } from './admin-profile';
import './catalog-filters';
import { catalogShell } from './catalog-shell';
import { itemCountPreview } from './item-count-preview';
import { workspaceDialog, workspaceColumnManager } from './workspace-dialogs';
import { tableColumnWidths } from './table-column-widths';
import { workspaceSplit } from './workspace-split';
import { tablePagination } from './table-pagination';
import { configuratorProductSearch } from './configurator-product-search';

window.uiInterpolate = interpolate;
window.workspaceColumnManager = workspaceColumnManager;
window.rememberSchemaState = rememberSchemaState;
window.rememberOrderControls = rememberOrderControls;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('configuratorProductSearch', configuratorProductSearch);
    window.Alpine.data('catalogShell', catalogShell);
    window.Alpine.data('itemCountPreview', itemCountPreview);
    window.Alpine.data('workspaceDialog', workspaceDialog);
    window.Alpine.data('tableColumnWidths', tableColumnWidths);
    window.Alpine.data('workspaceSplit', workspaceSplit);
    window.Alpine.data('tablePagination', tablePagination);
    window.Alpine.data('localTableProfile', localTableProfile);
    window.Alpine.data('adminBrowserProfile', adminBrowserProfile);
    window.Alpine.data('localTraceProfile', localTraceProfile);
    window.Alpine.data('localSectionProfile', localSectionProfile);
    window.Alpine.data('localOrderProfile', localOrderProfile);
}, { once: true });

if (window.Alpine) {
    window.Alpine.data('configuratorProductSearch', configuratorProductSearch);
    window.Alpine.data('itemCountPreview', itemCountPreview);
    window.Alpine.data('workspaceDialog', workspaceDialog);
    window.Alpine.data('tableColumnWidths', tableColumnWidths);
    window.Alpine.data('workspaceSplit', workspaceSplit);
    window.Alpine.data('tablePagination', tablePagination);
    window.Alpine.data('localTableProfile', localTableProfile);
    window.Alpine.data('adminBrowserProfile', adminBrowserProfile);
    window.Alpine.data('localTraceProfile', localTraceProfile);
    window.Alpine.data('localSectionProfile', localSectionProfile);
    window.Alpine.data('localOrderProfile', localOrderProfile);
}
