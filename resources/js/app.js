import { attributeEditorDraft } from './attribute-editor-draft';
import { nextIdentifierIntent, suggestedIdentifier } from './suggested-identifier';
import { configuratorCreationGuard } from './configurator-creation';
import { masterValueBatch, catalogDraftGuard, attributeDraftIsDirty } from './master-value-batch';
import { workspaceTableSelection } from './workspace-table-selection';
import { interpolate } from './ui-wording';
import { localTableProfile, adminBrowserProfile, rememberSchemaState, localSectionProfile, localOrderProfile, rememberOrderControls } from './admin-profile';
import './catalog-filters';
import { catalogShell } from './catalog-shell';
import { itemCountPreview } from './item-count-preview';
import { workspaceDialog, workspaceColumnManager } from './workspace-dialogs';
import { tableColumnWidths } from './table-column-widths';
import { workspaceSplit } from './workspace-split';
import { editorDrafts, configuratorDrafts } from './editor-drafts';
import { tablePagination } from './table-pagination';
import { configuratorProductSearch } from './configurator-product-search';
import { configuratorPreviewWorkspace } from './configurator-preview-workspace';
import { configuratorPreviewDisplay } from './configurator-preview-display';

window.uiInterpolate = interpolate;
window.workspaceColumnManager = workspaceColumnManager;
window.rememberSchemaState = rememberSchemaState;
window.rememberOrderControls = rememberOrderControls;
window.attributeEditorDraft = attributeEditorDraft;
window.nextIdentifierIntent = nextIdentifierIntent;
window.editorDrafts = editorDrafts;
window.configuratorDrafts = configuratorDrafts;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('suggestedIdentifier', suggestedIdentifier);
    window.Alpine.data('masterValueBatch', masterValueBatch);
    window.Alpine.data('attributeCreationGuard', config => catalogDraftGuard(config, attributeDraftIsDirty));
    window.Alpine.data('configuratorCreationGuard', configuratorCreationGuard);
    window.Alpine.data('workspaceTableSelection', workspaceTableSelection);
    window.Alpine.data('configuratorProductSearch', configuratorProductSearch);
    window.Alpine.data('configuratorPreviewWorkspace', configuratorPreviewWorkspace);
    window.Alpine.data('catalogShell', catalogShell);
    window.Alpine.data('itemCountPreview', itemCountPreview);
    window.Alpine.data('workspaceDialog', workspaceDialog);
    window.Alpine.data('tableColumnWidths', tableColumnWidths);
    window.Alpine.data('workspaceSplit', workspaceSplit);
    window.Alpine.data('tablePagination', tablePagination);
    window.Alpine.data('localTableProfile', localTableProfile);
    window.Alpine.data('adminBrowserProfile', adminBrowserProfile);
    window.Alpine.data('configuratorPreviewDisplay', configuratorPreviewDisplay);
    window.Alpine.data('localSectionProfile', localSectionProfile);
    window.Alpine.data('localOrderProfile', localOrderProfile);
}, { once: true });

if (window.Alpine) {
    window.Alpine.data('suggestedIdentifier', suggestedIdentifier);
    window.Alpine.data('masterValueBatch', masterValueBatch);
    window.Alpine.data('attributeCreationGuard', config => catalogDraftGuard(config, attributeDraftIsDirty));
    window.Alpine.data('configuratorCreationGuard', configuratorCreationGuard);
    window.Alpine.data('workspaceTableSelection', workspaceTableSelection);
    window.Alpine.data('configuratorProductSearch', configuratorProductSearch);
    window.Alpine.data('configuratorPreviewWorkspace', configuratorPreviewWorkspace);
    window.Alpine.data('itemCountPreview', itemCountPreview);
    window.Alpine.data('workspaceDialog', workspaceDialog);
    window.Alpine.data('tableColumnWidths', tableColumnWidths);
    window.Alpine.data('workspaceSplit', workspaceSplit);
    window.Alpine.data('tablePagination', tablePagination);
    window.Alpine.data('localTableProfile', localTableProfile);
    window.Alpine.data('adminBrowserProfile', adminBrowserProfile);
    window.Alpine.data('configuratorPreviewDisplay', configuratorPreviewDisplay);
    window.Alpine.data('localSectionProfile', localSectionProfile);
    window.Alpine.data('localOrderProfile', localOrderProfile);
}
