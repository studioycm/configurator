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
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('resource lists show one quiet breadcrumb title and the full table count', function (string $page, string $model, string $group, string $title) {
    $model::factory()->count(3)->create();

    $list = Livewire::test($page)->assertSee('catalog-resource-list-header', false)
        ->assertSee('catalog-table-count', false)->assertSee('3 total')
        ->assertDontSee('catalog-table-heading', false);

    expect($list->instance()->getBreadcrumbs())->toBe([$group, $title]);
    expect($list->instance()->resourceTableCounts)->toBe(['matched' => 3, 'total' => 3, 'filtered' => false]);
})->with([
    'master values' => [ListValues::class, Value::class, 'Product configuration', 'Master Values'],
    'options' => [ListOptions::class, Option::class, 'Product configuration', 'Options'],
    'attributes' => [ListAttributes::class, Attribute::class, 'Product configuration', 'Attributes'],
    'configurators' => [ListConfigurators::class, Configurator::class, 'Product configuration', 'Configurators'],
    'groups' => [ListGroups::class, Group::class, 'Catalog', 'Groups'],
    'products' => [ListProducts::class, Product::class, 'Catalog', 'Products'],
]);

test('matched counts include every page and update only when pending filters are applied', function () {
    Value::factory()->count(12)->sequence(fn (Sequence $sequence): array => ['label' => 'Flange '.$sequence->index, 'tags' => ['metal']])->create();
    Value::factory()->count(3)->sequence(fn (Sequence $sequence): array => ['label' => 'Other '.$sequence->index, 'tags' => ['polymer']])->create();

    $list = Livewire::test(ListValues::class)->set('tableRecordsPerPage', 5)->searchTable('Flange')
        ->assertSee('12 of 15');

    expect($list->instance()->getTableRecords())->toHaveCount(5);
    $list->set('tableDeferredFilters.tags.values', ['polymer'])->assertSee('12 of 15');
    $list->call('applyTableFilters')->assertSee('0 of 15');
    $list->searchTable('')->assertSee('3 of 15');
    $list->call('resetTableFiltersForm')->assertSee('15 total');
});

test('empty resource lists keep a visible zero count and their creation action', function () {
    Livewire::test(ListValues::class)->assertSee('0 total')->assertTableActionExists('create');
    Livewire::test(ListGroups::class)->assertSee('0 total')->assertActionExists('create');
    Livewire::test(ListConfigurators::class)->assertSee('0 total')->assertActionExists('create');
});

test('an applied tag remains visible in the count even when every record matches', function () {
    Value::factory()->create(['tags' => ['metal']]);

    Livewire::test(ListValues::class)->set('tableFilters.tags.values', ['metal'])->assertSee('1 of 1')
        ->set('tableFilters.tags.values', [])->assertSee('1 total');
});
