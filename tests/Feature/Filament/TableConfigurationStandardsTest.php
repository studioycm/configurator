<?php

use App\Filament\Resources\CatalogGroups\Pages\ListCatalogGroups;
use App\Filament\Resources\ConfigAttributes\Pages\ListConfigAttributes;
use App\Filament\Resources\ConfigOptions\Pages\ListConfigOptions;
use App\Filament\Resources\ConfigProfiles\Pages\ListConfigProfiles;
use App\Filament\Resources\ConfigurationParts\Pages\ListConfigurationParts;
use App\Filament\Resources\ConfigurationSpecifications\Pages\ListConfigurationSpecifications;
use App\Filament\Resources\OptionRules\Pages\ListOptionRules;
use App\Filament\Resources\Parts\Pages\ListParts;
use App\Filament\Resources\ProductConfigurations\Pages\ListProductConfigurations;
use App\Filament\Resources\ProductProfiles\Pages\ListProductProfiles;
use App\Models\User;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\BaseFilter;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'email' => 'ycm@data4.work',
    ]));
});

dataset('table-standards-pages', [
    'catalog groups' => [
        'page' => ListCatalogGroups::class,
        'columns' => [
            'id' => 'ID',
            'name' => 'Name',
            'slug' => 'Slug',
            'parent.name' => 'Parent',
            'is_active' => 'Active',
            'sort_order' => 'Sort Order',
            'path' => 'Path',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'name', 'slug', 'parent.name', 'path'],
        'filters' => [
            'parent_id' => 'Parent',
        ],
    ],
    'config attributes' => [
        'page' => ListConfigAttributes::class,
        'columns' => [
            'id' => 'ID',
            'configProfile.name' => 'Configurator',
            'label' => 'Name',
            'input_type' => 'Input Type',
            'sort_order' => 'Sort Order',
            'is_required' => 'Required',
            'segment_index' => 'Segment Index',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'configProfile.name', 'label', 'input_type'],
        'filters' => [
            'config_profile_id' => 'Configurator',
        ],
    ],
    'config options' => [
        'page' => ListConfigOptions::class,
        'columns' => [
            'id' => 'ID',
            'label' => 'Name',
            'code' => 'Code',
            'attribute.label' => 'Attribute',
            'configProfile.name' => 'Configurator',
            'sort_order' => 'Sort Order',
            'is_default' => 'Default',
            'is_active' => 'Active',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'label', 'code', 'attribute.label', 'configProfile.name'],
        'filters' => [
            'config_profile_id' => 'Configurator',
            'attribute' => 'Attribute',
        ],
    ],
    'config profiles' => [
        'page' => ListConfigProfiles::class,
        'columns' => [
            'id' => 'ID',
            'productProfile.name' => 'Product',
            'name' => 'Name',
            'slug' => 'Slug',
            'scope' => 'Scope',
            'is_active' => 'Active',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'productProfile.name', 'name', 'slug', 'scope'],
        'filters' => [
            'product_profile_id' => 'Product',
        ],
    ],
    'configuration parts' => [
        'page' => ListConfigurationParts::class,
        'columns' => [
            'id' => 'ID',
            'productConfiguration.name' => 'Product Configuration',
            'part.name' => 'Part',
            'part_number' => 'Part Number',
            'label' => 'Name',
            'material' => 'Material',
            'quantity' => 'Quantity',
            'unit' => 'Unit',
            'segment_index' => 'Segment Index',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'productConfiguration.name', 'part.name', 'label', 'material', 'unit'],
        'filters' => [
            'product_configuration_id' => 'Product Configuration',
            'part_id' => 'Part',
        ],
    ],
    'configuration specifications' => [
        'page' => ListConfigurationSpecifications::class,
        'columns' => [
            'id' => 'ID',
            'productConfiguration.name' => 'Product Configuration',
            'spec_group' => 'Spec Group',
            'key' => 'Key',
            'value' => 'Value',
            'unit' => 'Unit',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'productConfiguration.name', 'spec_group', 'key', 'value', 'unit'],
        'filters' => [
            'product_configuration_id' => 'Product Configuration',
        ],
    ],
    'option rules' => [
        'page' => ListOptionRules::class,
        'columns' => [
            'id' => 'ID',
            'configProfile.name' => 'Configurator',
            'optionAttribute.label' => 'Attribute',
            'option.label' => 'Option',
            'targetAttribute.label' => 'Target Attribute',
            'allowed_option_ids' => 'Allowed Options',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'configProfile.name', 'optionAttribute.label', 'targetAttribute.label'],
        'filters' => [
            'config_profile_id' => 'Configurator',
            'option' => 'Option',
            'optionAttribute' => 'Attribute',
            'target_attribute_id' => 'Target Attribute',
        ],
    ],
    'parts' => [
        'page' => ListParts::class,
        'columns' => [
            'id' => 'ID',
            'name' => 'Name',
            'code' => 'Code',
            'default_material' => 'Default Material',
            'is_active' => 'Active',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'name', 'code', 'default_material'],
        'filters' => [],
    ],
    'product configurations' => [
        'page' => ListProductConfigurations::class,
        'columns' => [
            'id' => 'ID',
            'productProfile.name' => 'Product',
            'configuration_code' => 'Configuration Code',
            'name' => 'Name',
            'is_active' => 'Active',
            'drawing_image_path' => 'Drawing Image',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'productProfile.name', 'configuration_code', 'name'],
        'filters' => [
            'product_profile_id' => 'Product',
        ],
    ],
    'product profiles' => [
        'page' => ListProductProfiles::class,
        'columns' => [
            'id' => 'ID',
            'catalogGroup.name' => 'Category',
            'name' => 'Name',
            'product_code' => 'Product Code',
            'slug' => 'Slug',
            'short_label' => 'Short Label',
            'is_active' => 'Active',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'searchableColumns' => ['id', 'catalogGroup.name', 'name', 'product_code', 'slug', 'short_label'],
        'filters' => [
            'catalog_group_id' => 'Category',
        ],
    ],
]);

it('applies the requested Filament table standards', function (string $page, array $columns, array $searchableColumns, array $filters) {
    $component = Livewire::test($page);

    foreach ($columns as $name => $label) {
        $component->assertTableColumnExists($name, function (Column $column) use ($label): bool {
            return $column->getLabel() === $label;
        });
    }

    foreach ($searchableColumns as $name) {
        $component->assertTableColumnExists($name, function (TextColumn $column): bool {
            return $column->isSearchable()
                && $column->isIndividuallySearchable()
                && ! $column->isGloballySearchable();
        });
    }

    foreach ($filters as $name => $label) {
        $component->assertTableFilterExists($name, function (BaseFilter $filter) use ($label): bool {
            return $filter->getLabel() === $label;
        });
    }

    $table = $component->instance()->getTable();

    expect($table->getFiltersLayout())->toBe(FiltersLayout::AboveContent)
        ->and($table->getFiltersFormColumns())->toBe(5)
        ->and($table->hasDeferredFilters())->toBeFalse();
})->with('table-standards-pages');
