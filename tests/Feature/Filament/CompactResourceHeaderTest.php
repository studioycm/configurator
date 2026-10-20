<?php

use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Configurators\Pages\ListConfigurators;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('resource lists show one quiet breadcrumb title and the full table count', function (string $page, string $model) {
    $model::factory()->count(3)->create();

    $list = Livewire::test($page)->assertSee('catalog-resource-list-header', false)
        ->assertSee('catalog-table-count', false)->assertSee(__('admin.tables.counts.total', ['total' => 3]))
        ->assertDontSee('catalog-table-heading', false);

    $resource = $page::getResource();
    expect($list->instance()->getBreadcrumbs())->toBe([$resource::getNavigationGroup(), $resource::getTitleCasePluralModelLabel()]);
    $list->assertSee($resource::getTitleCasePluralModelLabel());
    expect($list->instance()->resourceTableCounts)->toBe(['matched' => 3, 'total' => 3, 'filtered' => false]);
})->with([
    'master values' => [ListValues::class, Value::class],
    'options' => [ListOptions::class, Option::class],
    'attributes' => [ListAttributes::class, Attribute::class],
    'configurators' => [ListConfigurators::class, Configurator::class],
    'groups' => [ListGroups::class, Group::class],
    'products' => [ListProducts::class, Product::class],
]);

test('matched counts include every page and update only when pending filters are applied', function () {
    Value::factory()->count(12)->sequence(fn (Sequence $sequence): array => ['label' => 'Flange '.$sequence->index, 'tags' => ['metal']])->create();
    Value::factory()->count(3)->sequence(fn (Sequence $sequence): array => ['label' => 'Other '.$sequence->index, 'tags' => ['polymer']])->create();

    $list = Livewire::test(ListValues::class)->set('tableRecordsPerPage', 5)->searchTable('Flange')
        ->assertSee(__('admin.tables.counts.matched', ['matched' => 12, 'total' => 15]));

    expect($list->instance()->getTableRecords())->toHaveCount(5);
    $list->set('tableDeferredFilters.tags.values', ['polymer'])->assertSee(__('admin.tables.counts.matched', ['matched' => 12, 'total' => 15]));
    $list->call('applyTableFilters')->assertSee(__('admin.tables.counts.matched', ['matched' => 0, 'total' => 15]));
    $list->searchTable('')->assertSee(__('admin.tables.counts.matched', ['matched' => 3, 'total' => 15]));
    $list->call('resetTableFiltersForm')->assertSee(__('admin.tables.counts.total', ['total' => 15]));
});

test('empty resource lists keep a visible zero count and their creation action', function () {
    Livewire::test(ListValues::class)->assertSee(__('admin.tables.counts.total', ['total' => 0]))->assertActionExists(TestAction::make('create')->table());
    Livewire::test(ListGroups::class)->assertSee(__('admin.tables.counts.total', ['total' => 0]))->assertActionExists(TestAction::make('create')->table());
    Livewire::test(ListConfigurators::class)->assertSee(__('admin.tables.counts.total', ['total' => 0]))->assertActionExists(TestAction::make('create')->table());
});

test('tag counts ignore the operator alone and show applied tags even when every record matches', function (string $page, string $operator) {
    $value = Value::factory()->create(['tags' => ['metal']]);
    if ($page === ListOptions::class) {
        Option::factory()->for($value)->create();
    }

    $list = Livewire::test($page)->set('tableFilters.tags.operator', $operator)
        ->assertSee(__('admin.tables.counts.total', ['total' => 1]));
    expect($list->instance()->resourceTableCounts)->toBe(['matched' => 1, 'total' => 1, 'filtered' => false]);

    $list->set('tableFilters.tags.values', ['metal'])->assertSee(__('admin.tables.counts.matched', ['matched' => 1, 'total' => 1]));
    expect($list->instance()->resourceTableCounts)->toBe(['matched' => 1, 'total' => 1, 'filtered' => true]);

    $list->set('tableFilters.tags.values', [])->assertSee(__('admin.tables.counts.total', ['total' => 1]));
    expect($list->instance()->resourceTableCounts)->toBe(['matched' => 1, 'total' => 1, 'filtered' => false]);
})->with([
    'master values with OR' => [ListValues::class, 'or'],
    'master values with AND' => [ListValues::class, 'and'],
    'options with OR' => [ListOptions::class, 'or'],
    'options with AND' => [ListOptions::class, 'and'],
]);
