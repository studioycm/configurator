<?php

use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Configurators\Pages\ListConfigurators;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\User;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\BaseFilter;
use Livewire\Livewire;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\Pages\ListConfigurationParts;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Pages\ListConfigurationSpecifications;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Pages\ListOptionRules;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\Pages\ListParts;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\Pages\ListProductConfigurations;
use Tests\Fixtures\Legacy\LoadsLegacyFixtures;

uses(LoadsLegacyFixtures::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'email' => 'ycm@data4.work',
    ]));
});

dataset('table-standards-pages', [
    'groups' => [
        'page' => ListGroups::class,
        'columns' => ['id' => 'ID', 'name' => 'Name', 'legacy_id' => 'Legacy ID', 'parent.name' => 'Parent', 'configurator.name' => 'Configurator', 'sort_order' => 'Sort Order', 'created_at' => 'Created At', 'updated_at' => 'Updated At'],
        'searchableColumns' => ['id', 'name', 'legacy_id', 'parent.name'],
        'filters' => ['parent_id' => 'Parent'],
    ],
    'config attributes' => [
        'page' => ListAttributes::class,
        'columns' => ['id' => 'ID', 'key' => 'Key', 'label' => 'Label', 'options_count' => 'Options', 'configurator_attributes_count' => 'Used in configurators', 'created_at' => 'Created At', 'updated_at' => 'Updated At'],
        'searchableColumns' => ['id', 'key', 'label'],
        'filters' => [],
    ],
    'config options' => [
        'page' => ListOptions::class,
        'columns' => ['id' => 'ID', 'code' => 'Code', 'attribute.label' => 'Attribute', 'value.label' => 'Value', 'configurator_options_count' => 'Local uses', 'created_at' => 'Created At', 'updated_at' => 'Updated At'],
        'searchableColumns' => ['id', 'code', 'attribute.label', 'value.label'],
        'filters' => ['attribute_id' => 'Attribute'],
    ],
    'config profiles' => [
        'page' => ListConfigurators::class,
        'columns' => ['id' => 'ID', 'name' => 'Name', 'groups_count' => 'Assigned groups', 'configurator_attributes_count' => 'Attributes', 'rules_count' => 'Rules', 'created_at' => 'Created At', 'updated_at' => 'Updated At'],
        'searchableColumns' => ['id', 'name'],
        'filters' => [],
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
            'allowed_option_ids' => 'Allowed Target Options',
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
    'products' => [
        'page' => ListProducts::class,
        'columns' => ['id' => 'ID', 'product_code' => 'Product Code', 'product_name' => 'Product Name', 'group.name' => 'Group', 'legacy_id' => 'Legacy ID', 'created_at' => 'Created At', 'updated_at' => 'Updated At'],
        'searchableColumns' => ['id', 'product_code', 'product_name', 'group.name', 'legacy_id'],
        'filters' => ['group_id' => 'Group'],
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
