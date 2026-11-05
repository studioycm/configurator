<?php

use App\Filament\Pages\AdminAppearance;
use App\Filament\Pages\CatalogDisplay;
use App\Filament\Pages\CatalogImages;
use App\Filament\Pages\ContextSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DataExchange;
use App\Filament\Pages\ManageTags;
use App\Filament\Resources\Configurators\Pages\WriteConfiguratorRules;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\TourDefinitions\Pages\ManageTourDefinitions;

$screens = [
    'images' => ['scope' => CatalogImages::class, 'title' => 'Images', 'intro' => 'Find missing images or replace saved images for Products and Groups.', 'workspace' => 'Inspect one record or select records to review image assignments before saving them.'],
    'dashboard' => ['scope' => Dashboard::class, 'title' => 'Dashboard', 'intro' => 'Move between the catalog administration workspaces.', 'workspace' => 'Use the navigation to open Groups, Products, Configurators, Images or settings.'],
    'products-view' => ['scope' => ViewProduct::class, 'title' => 'Product details', 'intro' => 'Inspect the saved Product and its Group.', 'workspace' => 'Use Edit for administrative changes or Open catalog to configure this Product.'],
    'writing' => ['scope' => WriteConfiguratorRules::class, 'title' => 'Rules Writing', 'intro' => 'Write and review rule drafts for the current Configurator.', 'workspace' => 'A draft is not an applied rule. Save keeps the draft; use the explicit review and Apply flow to change saved Rules. This tour never saves or applies a draft.'],
    'catalog-display' => ['scope' => CatalogDisplay::class, 'title' => 'Catalog Display', 'intro' => 'Control how Group and Product cards and properties appear in the Catalog.', 'workspace' => 'Choose the display context, adjust its settings and use Save. Presentation settings do not create Groups or Products.'],
    'appearance' => ['scope' => AdminAppearance::class, 'title' => 'Admin Appearance', 'intro' => 'Adjust the shared Admin appearance and workspace presentation.', 'workspace' => 'Review the appearance controls and use Save to persist changes. Tour navigation does not change these settings.'],
    'data-exchange' => ['scope' => DataExchange::class, 'title' => 'Data Exchange', 'intro' => 'Import and export catalog definitions and Configurator Rules.', 'workspace' => 'Choose the data type and scope. Review the import preview before its explicit Apply action. Exports and imports are different operations.'],
    'tags' => ['scope' => ManageTags::class, 'title' => 'Master Value tags', 'intro' => 'Manage the tags used by shared Master Values.', 'workspace' => 'Select a tag to inspect its usage. Master Value tags are distinct from Group tags and Group properties.'],
    'context-settings' => ['scope' => ContextSettings::class, 'title' => 'Context settings', 'intro' => 'Manage shared context choices offered by Configurators.', 'workspace' => 'Review Territory and Application choices in their tabs. Use Save for changes; the tour does not change context choices.'],
    'tour-management' => ['scope' => ManageTourDefinitions::class, 'title' => 'Guided Tours', 'intro' => 'Manage a tour stage for each screen or workspace.', 'workspace' => 'Open a stage to edit its title, language, enabled state and ordered steps. Saved changes appear on the next page load.'],
    'catalog-families' => ['scope' => null, 'title' => 'Catalog · Product families', 'intro' => 'Find a product family through the family tree, Group properties or Group tags.', 'workspace' => 'Choose a family card to browse its Products. Group properties, Group tags and Product properties have distinct meanings.'],
    'catalog-products' => ['scope' => null, 'title' => 'Catalog · Group Products', 'intro' => 'Browse Products in the chosen family using Product properties and Sub Groups.', 'workspace' => 'Filter Products, select two from this Group for comparison or open one Product. Comparison shows differing properties before shared properties.'],
    'catalog-configuration' => ['scope' => null, 'title' => 'Catalog · Product configuration', 'intro' => 'Configure the selected Product using its available Attribute choices.', 'workspace' => 'Choose values and inspect the configuration code. The code loader and shared link use the current Product; this tour does not select values.'],
];

$resources = [
    'groups' => ['Groups', 'Group', 'Group', 'Manage the product-family tree. Group properties describe families; Group tags provide separate flat grouping.', 'Edit the family, parent, Group properties and image. Save applies only this Group.'],
    'products' => ['Products', 'Product', 'Product', 'Browse existing Products and open their details or image editor.', 'Edit the existing Product data and main image. Product properties are separate from Group properties.'],
    'attributes' => ['Attributes', 'Attribute', 'Attribute', 'Manage shared Attributes and the Master Values available to them.', 'Set the Attribute identifier and its available Values. Configurators include shared definitions separately.'],
    'values' => ['Values', 'Value', 'Master Value', 'Manage reusable Master Values shared by Attributes.', 'Edit the Value identifier, label and tags. Attribute assignment and Configurator inclusion are separate operations.'],
    'options' => ['Options', 'Option', 'Attribute Value', 'Manage links between shared Attributes and their available Master Values.', 'Choose the Attribute and Master Value and edit this link. Configurator inclusion is managed separately.'],
    'configurators' => ['Configurators', 'Configurator', 'Configurator', 'Manage Configurators and their Attributes, Values, Rules and preview.', 'Use the workspace tabs to manage included Attributes, Values and Rules, then inspect the configuration preview.'],
];
foreach ($resources as $key => [$directory, $entity, $label, $intro, $workspace]) {
    $namespace = 'App\\Filament\\Resources\\'.$directory.'\\Pages\\';
    $screens[$key.'-list'] = ['scope' => $namespace.'List'.$directory, 'title' => $key === 'options' ? 'Attribute Values' : $label.'s', 'intro' => $intro, 'workspace' => 'Search and filter records. Inspecting or editing one record is separate from selecting records for a bulk action.'];
    if ($key !== 'products') {
        $screens[$key.'-create'] = ['scope' => $namespace.'Create'.$entity, 'title' => 'Create '.$label, 'intro' => 'Create one new '.$label.'.', 'workspace' => 'Complete the required fields and review the values before using Create. Opening this tour does not create a record.'];
    }
    $screens[$key.'-edit'] = ['scope' => $namespace.'Edit'.$entity, 'title' => 'Edit '.$label, 'intro' => 'You are editing one saved '.$label.'.', 'workspace' => $workspace.' Unsaved input is not committed by this tour.'];
}

return ['screens' => $screens];
