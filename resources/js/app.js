import { attributeEditorDraft } from './attribute-editor-draft';
import { nextIdentifierIntent, suggestedIdentifier } from './suggested-identifier';
import { configuratorCreationGuard } from './configurator-creation';
import { masterValueBatch, catalogDraftGuard, attributeDraftIsDirty } from './master-value-batch';
import { workspaceTableSelection } from './workspace-table-selection';
import { interpolate } from './ui-wording';
import { localTableProfile, adminBrowserProfile, rememberSchemaState, localSectionProfile, localOrderProfile, rememberOrderControls } from './admin-profile';
import { catalogImageEditor } from './catalog-image-editor';
import { catalogFilters } from './catalog-filters';
import { catalogNavigation } from './catalog-navigation';
import { catalogConfigurationUrl } from './catalog-configuration-url';
import { catalogProductComparison } from './catalog-product-comparison';
import { catalogShell } from './catalog-shell';
import { itemCountPreview } from './item-count-preview';
import { workspaceDialog, workspaceColumnManager } from './workspace-dialogs';
import { tableColumnWidths } from './table-column-widths';
import { workspaceSplit } from './workspace-split';
import { editorDrafts, configuratorDrafts, sameEditorMeaning } from './editor-drafts';
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
window.sameEditorMeaning = sameEditorMeaning;

const registeredAlpine = new WeakSet();

function registerComponents(Alpine) {
    if (!Alpine || registeredAlpine.has(Alpine)) return;
    Alpine.data('suggestedIdentifier', suggestedIdentifier);
    Alpine.data('masterValueBatch', masterValueBatch);
    Alpine.data('attributeCreationGuard', config => catalogDraftGuard(config, attributeDraftIsDirty));
    Alpine.data('configuratorCreationGuard', configuratorCreationGuard);
    Alpine.data('workspaceTableSelection', workspaceTableSelection);
    Alpine.data('configuratorProductSearch', configuratorProductSearch);
    Alpine.data('configuratorPreviewWorkspace', configuratorPreviewWorkspace);
    Alpine.data('catalogShell', catalogShell);
    Alpine.data('catalogNavigation', catalogNavigation);
    Alpine.data('catalogConfigurationUrl', catalogConfigurationUrl);
    Alpine.data('catalogProductComparison', catalogProductComparison);
    Alpine.data('itemCountPreview', itemCountPreview);
    Alpine.data('workspaceDialog', workspaceDialog);
    Alpine.data('tableColumnWidths', tableColumnWidths);
    Alpine.data('workspaceSplit', workspaceSplit);
    Alpine.data('tablePagination', tablePagination);
    Alpine.data('localTableProfile', localTableProfile);
    Alpine.data('adminBrowserProfile', adminBrowserProfile);
    Alpine.data('configuratorPreviewDisplay', configuratorPreviewDisplay);
    Alpine.data('localSectionProfile', localSectionProfile);
    Alpine.data('localOrderProfile', localOrderProfile);
    Alpine.data('catalogFilters', catalogFilters);
    Alpine.data('catalogImageEditor', catalogImageEditor);
    registeredAlpine.add(Alpine);
}

document.addEventListener('alpine:init', () => registerComponents(window.Alpine), { once: true });
registerComponents(window.Alpine);
