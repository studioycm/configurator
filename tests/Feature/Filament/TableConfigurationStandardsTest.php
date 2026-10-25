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

    foreach ($searchableColumns as $name) {
        $component->assertTableColumnExists($name, fn (TextColumn $column): bool => $column->isGloballySearchable() && ! $column->isIndividuallySearchable());
    }

    foreach ($filters as $name) {
        $component->assertTableFilterExists($name);
    }

    $table = $component->instance()->getTable();

    expect($component->instance()->isTableColumnToggledHidden('id'))->toBeTrue();

    foreach ($table->getColumns() as $column) {
        expect($column->canHeaderWrap())->toBeTrue();
        if ($column instanceof TextColumn) {
            expect($column->canWrap())->toBeTrue();
        }
    }

    expect($table->getFiltersLayout())->toBe(FiltersLayout::Modal)
        ->and($table->hasDeferredFilters())->toBeTrue()
        ->and($table->hasReorderableColumns())->toBeTrue();
})->with('table-standards-pages');

test('the compact Columns drawer retains native Apply Reset and width-only Reset', function () {
    $page = Livewire::test(ListOptions::class);
    $actions = $page->instance()->getTable()->getColumnManagerTriggerAction()->getExtraModalFooterActions();
    expect(array_map(fn ($action): string => $action->getName(), $actions))->toContain('applyTableColumnManager', 'resetColumnManager', 'resetWidths');
    $page->call('resetTableColumnManager')->assertDispatched('table-widths-reset');
});
