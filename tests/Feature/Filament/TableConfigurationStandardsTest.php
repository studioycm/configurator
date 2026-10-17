<?php

use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Configurators\Pages\ListConfigurators;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
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
        'columns' => ['id', 'name', 'legacy_id', 'parent.name', 'configurator.name', 'sort_order', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'name', 'legacy_id', 'parent.name'],
        'filters' => ['parent_id'],
    ],
    'config attributes' => [
        'page' => ListAttributes::class,
        'columns' => ['id', 'key', 'label', 'options_count', 'configurator_attributes_count', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'key', 'label'],
        'filters' => [],
    ],
    'config options' => [
        'page' => ListOptions::class,
        'columns' => ['id', 'code', 'attribute.label', 'value.label', 'value.semantic_id', 'configurator_options_count', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'code', 'attribute.label', 'value.label', 'value.semantic_id'],
        'filters' => ['attribute_id'],
    ],
    'master values' => [
        'page' => ListValues::class,
        'columns' => ['id', 'label', 'semantic_id', 'description', 'tags', 'options_count', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'label', 'semantic_id', 'description'],
        'filters' => ['tags'],
    ],
    'config profiles' => [
        'page' => ListConfigurators::class,
        'columns' => ['id', 'name', 'groups_count', 'configurator_attributes_count', 'rules_count', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'name'],
        'filters' => [],
    ],
    'configuration parts' => [
        'page' => ListConfigurationParts::class,
        'columns' => ['id', 'productConfiguration.name', 'part.name', 'part_number', 'label', 'material', 'quantity', 'unit', 'segment_index', 'sort_order', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'productConfiguration.name', 'part.name', 'label', 'material', 'unit'],
        'filters' => ['product_configuration_id', 'part_id'],
    ],
    'configuration specifications' => [
        'page' => ListConfigurationSpecifications::class,
        'columns' => ['id', 'productConfiguration.name', 'spec_group', 'key', 'value', 'unit', 'sort_order', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'productConfiguration.name', 'spec_group', 'key', 'value', 'unit'],
        'filters' => ['product_configuration_id'],
    ],
    'option rules' => [
        'page' => ListOptionRules::class,
        'columns' => ['id', 'configProfile.name', 'optionAttribute.label', 'option.label', 'targetAttribute.label', 'allowed_option_ids', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'configProfile.name', 'optionAttribute.label', 'targetAttribute.label'],
        'filters' => ['config_profile_id', 'option', 'optionAttribute', 'target_attribute_id'],
    ],
    'parts' => [
        'page' => ListParts::class,
        'columns' => ['id', 'name', 'code', 'default_material', 'is_active', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'name', 'code', 'default_material'],
        'filters' => [],
    ],
    'product configurations' => [
        'page' => ListProductConfigurations::class,
        'columns' => ['id', 'productProfile.name', 'configuration_code', 'name', 'is_active', 'drawing_image_path', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'productProfile.name', 'configuration_code', 'name'],
        'filters' => ['product_profile_id'],
    ],
    'products' => [
        'page' => ListProducts::class,
        'columns' => ['id', 'product_code', 'product_name', 'group.name', 'legacy_id', 'created_at', 'updated_at'],
        'searchableColumns' => ['id', 'product_code', 'product_name', 'group.name', 'legacy_id'],
        'filters' => ['group_id'],
    ],
]);

it('applies the requested Filament table standards', function (string $page, array $columns, array $searchableColumns, array $filters) {
    $component = Livewire::test($page);

    foreach ($columns as $name) {
        $component->assertTableColumnExists($name);
    }

    $isCurrent = str_starts_with($page, 'App\\Filament\\');

    foreach ($searchableColumns as $name) {
        $component->assertTableColumnExists($name, function (TextColumn $column) use ($isCurrent): bool {
            if ($isCurrent) {
                return $column->isGloballySearchable() && ! $column->isIndividuallySearchable();
            }

            return $column->isSearchable()
                && $column->isIndividuallySearchable()
                && ! $column->isGloballySearchable();
        });
    }

    foreach ($filters as $name) {
        $component->assertTableFilterExists($name);
    }

    $table = $component->instance()->getTable();

    if ($isCurrent) {
        foreach ($table->getColumns() as $column) {
            expect($column->canHeaderWrap())->toBeTrue();
            if ($column instanceof TextColumn) {
                expect($column->canWrap())->toBeTrue();
            }
        }

        expect($table->getFiltersLayout())->toBe(FiltersLayout::Modal)
            ->and($table->hasDeferredFilters())->toBeTrue()
            ->and($table->hasReorderableColumns())->toBeTrue();

        return;
    }

    expect($table->getFiltersLayout())->toBe(FiltersLayout::AboveContent)
        ->and($table->getFiltersFormColumns())->toBe(5)
        ->and($table->hasDeferredFilters())->toBeFalse();
})->with('table-standards-pages');

test('the compact Columns drawer retains native Apply Reset and width-only Reset', function () {
    $page = Livewire::test(ListOptions::class);
    $actions = $page->instance()->getTable()->getColumnManagerTriggerAction()->getExtraModalFooterActions();
    expect(array_map(fn ($action): string => $action->getName(), $actions))->toContain('applyTableColumnManager', 'resetColumnManager', 'resetWidths');
    $page->call('resetTableColumnManager')->assertDispatched('table-widths-reset');
});
