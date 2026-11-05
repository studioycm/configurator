import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const appSource = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const filtersSource = readFileSync(new URL('../../resources/js/catalog-filters.js', import.meta.url), 'utf8');
const names = ['suggestedIdentifier', 'masterValueBatch', 'attributeCreationGuard', 'configuratorCreationGuard', 'workspaceTableSelection', 'workspaceSelectionScope', 'batchPreviewGuard', 'configuratorProductSearch', 'configuratorPreviewWorkspace', 'catalogShell', 'itemCountPreview', 'workspaceDialog', 'tableColumnWidths', 'workspaceSplit', 'tablePagination', 'localTableProfile', 'adminBrowserProfile', 'configuratorPreviewDisplay', 'localSectionProfile', 'localOrderProfile', 'catalogFilters', 'catalogNavigation', 'catalogConfigurationUrl', 'catalogProductComparison', 'catalogImageEditor', 'catalogImage', 'catalogImagesBrowser', 'catalogImagesEditor', 'catalogAssetAssignment', 'ruleWritingWorkspace', 'ruleWritingEntry', 'guidedTour', 'tourAuthor', 'guidedTourAuthorPage'];

function startup(alreadyPresent) {
    const registrations = [];
    const stores = new Map();
    const alpine = {
        data(name, factory) { registrations.push([name, factory]); },
        store(name, value) {
            if (arguments.length > 1) stores.set(name, value);
            return stores.get(name);
        },
    };
    const context = { document: new EventTarget(), window: alreadyPresent ? { Alpine: alpine } : {}, WeakSet };
    for (const source of [filtersSource, appSource]) {
        for (const match of source.matchAll(/^import\s+\{([^}]+)\}[^;]+;/gm)) {
            for (const name of match[1].split(',').map(name => name.trim())) {
                context[name] ??= () => {};
            }
        }
    }
    vm.createContext(context);
    // Execute the actual module bodies, replacing only the bundler's import/export syntax.
    vm.runInContext(filtersSource.replace(/^import[^;]+;\n/gm, '').replace(/^export /gm, ''), context);
    vm.runInContext(appSource.replace(/^import[^;]+;\n/gm, ''), context);
    return { registrations, alpine, context, init() { context.window.Alpine = alpine; context.document.dispatchEvent(new Event('alpine:init')); } };
}

for (const alreadyPresent of [false, true]) {
    test(`complete Alpine registration when Alpine is ${alreadyPresent ? 'already present' : 'announced by its event'}`, () => {
        const state = startup(alreadyPresent);
        if (!alreadyPresent) assert.equal(state.registrations.length, 0);
        else assert.deepEqual(state.registrations.map(([name]) => name).sort(), [...names].sort());
        state.init();
        assert.deepEqual(state.registrations.map(([name]) => name).sort(), [...names].sort());
        assert.ok(state.registrations.every(([, factory]) => typeof factory === 'function'));
        for (const name of ['attributeEditorDraft', 'editorDrafts', 'configuratorDrafts', 'sameEditorMeaning', 'workspaceColumnManager']) {
            assert.equal(typeof state.context.window[name], 'function');
        }
        const help = state.alpine.store('guidedTourHelp');
        assert.equal(help.enabled, false);
        assert.equal(help.userKey, null);
        assert.equal(Object.keys(help.contexts).length, 0);
        assert.equal(help.available, false);
        help.enabled = true;
        help.contexts.images = { title: 'Images' };
        assert.equal(help.available, true);
        state.init();
        assert.equal(state.registrations.length, names.length);
        assert.equal(state.alpine.store('guidedTourHelp'), help);
        assert.equal(help.enabled, true);
    });
}
