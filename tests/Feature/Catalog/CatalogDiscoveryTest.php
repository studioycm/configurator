<?php

use App\DTO\CatalogDiscoveryResult;
use App\Livewire\Catalog\GroupShow;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\SubGroup;
use App\Models\User;
use App\Services\CatalogDiscovery;
use Database\Seeders\D060FilterSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function discoveryFixture(): Group
{
    $group = Group::factory()->create();
    foreach (['Working_Pressure', 'Connection_Type', 'Connection_Size'] as $order => $key) {
        GroupFilter::factory()->for($group)->create(['property_key' => $key, 'label' => $key, 'sort_order' => $order, 'value_order' => [], 'value_labels' => []]);
    }
    foreach ([['A', '25', 'Flange', '2'], ['B', '16', 'Flange', '1'], ['C', '25', 'Threaded', '1']] as [$code, $pressure, $connection, $size]) {
        Product::factory()->for($group)->create(['product_code' => $code, 'properties' => ['Working_Pressure' => $pressure, 'Connection_Type' => $connection, 'Connection_Size' => $size, 'Model' => $code]]);
    }

    return $group;
}

function discover(Group $group, array $snapshot = [], ?string $action = null, mixed $argument = null): CatalogDiscoveryResult
{
    return app(CatalogDiscovery::class)->prepare($group->id, $snapshot, $action, $argument);
}

test('independent pressure connection size sequences preserve different precedence and outcomes', function (array $choices, string $expected) {
    $group = discoveryFixture();
    $state = [];
    foreach ($choices as [$key, $value]) {
        $result = discover($group, $state, 'filter', [$key, $value]);
        $state = $result->state->toArray();
    }
    expect($result->products->pluck('product_code')->all())->toBe([$expected])->and($result->state->page)->toBe(1)
        ->and($result->notices)->not->toBeEmpty();
})->with([
    'B' => [[['Working_Pressure', '25'], ['Connection_Type', 'Flange'], ['Connection_Size', '1']], 'B'],
    'C' => [[['Connection_Type', 'Flange'], ['Working_Pressure', '25'], ['Connection_Size', '1']], 'C'],
]);

test('preset is atomic in precedence and clears a hidden manual selection', function () {
    $group = discoveryFixture();
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'allowed_values' => ['25'], 'label' => 'S']);
    $result = discover($group, [], 'filter', ['Working_Pressure', '16']);
    $result = discover($group, $result->state->toArray(), 'subgroup', $preset->id);
    expect($result->state->filters)->toBe([])->and(array_column($result->fields, 'key'))->not->toContain('Working_Pressure');
    $result = discover($group, $result->state->toArray(), 'filter', ['Connection_Type', 'Flange']);
    $result = discover($group, $result->state->toArray(), 'filter', ['Connection_Size', '1']);
    expect($result->products->pluck('product_code')->all())->toBe(['B'])->and($result->state->subGroupId)->toBeNull();
    $result = discover($group, [], 'filter', ['Connection_Type', 'Flange']);
    $result = discover($group, $result->state->toArray(), 'subgroup', $preset->id);
    $result = discover($group, $result->state->toArray(), 'filter', ['Connection_Size', '1']);
    expect($result->products->pluck('product_code')->all())->toBe(['C'])->and($result->state->subGroupId)->toBe($preset->id);
});

test('multi value presets keep their filter and compatible choices even with a legacy hide flag', function (bool $forceHide) {
    $group = discoveryFixture();
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'allowed_values' => ['16', '25'], 'force_hide' => $forceHide]);
    $result = discover($group, [], 'filter', ['Working_Pressure', '16']);
    $result = discover($group, $result->state->toArray(), 'subgroup', $preset->id);
    expect($result->state->filters)->toBe(['Working_Pressure' => '16'])
        ->and(array_column($result->fields, 'key'))->toContain('Working_Pressure')
        ->and($result->products->pluck('product_code')->all())->toBe(['B']);
    $result = discover($group, $result->state->toArray());
    expect($result->state->filters)->toBe(['Working_Pressure' => '16'])
        ->and(array_column($result->fields, 'key'))->toContain('Working_Pressure');
    $result = discover($group, $result->state->toArray(), 'reset');
    expect(array_column($result->fields, 'key'))->toContain('Working_Pressure')->and($result->state->filters)->toBe([]);
})->with([false, true]);

test('a preset with only one remaining source option hides its property filter', function () {
    $group = discoveryFixture();
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'allowed_values' => ['25', 'removed']]);

    $result = discover($group, [], 'subgroup', $preset->id);

    expect(array_column($result->fields, 'key'))->not->toContain('Working_Pressure');
    expect($result->products->pluck('product_code')->all())->toBe(['A', 'C']);
});

test('preset only properties, clear filters and toggling use distinct boundaries', function () {
    $group = discoveryFixture();
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Model', 'allowed_values' => ['A', 'C']]);
    $result = discover($group, [], 'subgroup', $preset->id);
    $result = discover($group, $result->state->toArray(), 'filter', ['Connection_Size', '1']);
    expect($result->products->pluck('product_code')->all())->toBe(['C']);
    $result = discover($group, $result->state->toArray(), 'clear');
    expect($result->state->subGroupId)->toBe($preset->id)->and($result->products->total())->toBe(2)->and(array_column($result->fields, 'key'))->not->toContain('Model');
    $result = discover($group, $result->state->toArray(), 'filter', ['Connection_Size', '1']);
    $result = discover($group, $result->state->toArray(), 'filter', ['Connection_Size', '1']);
    expect($result->state->filters)->toBe([])->and($result->products->total())->toBe(2);
});

test('filter vocabulary remains available across pages without option counts', function () {
    $group = discoveryFixture();
    $group->update(['result_settings' => ['default_page_size' => 1, 'allow_page_size_change' => true, 'page_size_options' => [1, 2, 10]]]);
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'allowed_values' => ['25', '16']]);
    $result = discover($group, [], 'subgroup', $preset->id);
    $result = discover($group, $result->state->toArray(), 'filter', ['Working_Pressure', '25']);
    $field = collect($result->fields)->firstWhere('key', 'Working_Pressure');
    expect(array_column($field['values'], 'value'))->toBe(['25', '16'])->and(array_column($field['values'], 'count'))->toBe([])->and($result->products->count())->toBe(1)->and($result->products->total())->toBe(2);
    $state = $result->state->toArray();
    $state['page'] = 2;
    expect(discover($group, $state)->state->page)->toBe(2);
    expect(discover($group, $state, 'size', 2)->state->page)->toBe(1);
});

test('untrusted url inputs are bounded normalized and cannot widen the group', function () {
    $group = discoveryFixture();
    $foreign = SubGroup::factory()->create();
    $result = discover($group, ['version' => 1, 'filters' => ['Working_Pressure' => ['25'], 'properties->secret' => 'x'], 'precedence' => ['evil'], 'subGroupId' => $foreign->id, 'page' => ['2'], 'perPage' => 100]);
    expect($result->state->filters)->toBe([])->and($result->state->subGroupId)->toBeNull()->and($result->state->page)->toBe(1)->and($result->state->perPage)->toBe(10)->and($result->products->total())->toBe(3)->and($result->notices)->not->toBeEmpty();
    $result = discover($group, ['version' => 99, 'filters' => ['Working_Pressure' => '25']]);
    expect($result->state->filters)->toBe([])->and($result->repaired)->toBeTrue();
    Livewire::test(GroupShow::class, ['group' => $group])->call('selectFilter', 'foreign', 'x')->assertSet('discovery.filters', []);
});

test('livewire restores one complete valid snapshot including page and precedence', function () {
    $group = discoveryFixture();
    $group->update(['result_settings' => ['default_page_size' => 1, 'allow_page_size_change' => false, 'page_size_options' => [1]]]);
    Livewire::test(GroupShow::class, ['group' => $group])->call('selectFilter', 'Working_Pressure', '25')->call('goToPage', 2)
        ->assertSet('discovery.page', 2)->assertSet('discovery.precedence', ['filter:Working_Pressure'])
        ->call('clearFilters')->assertSet('discovery.page', 1)->assertSet('discovery.filters', []);
});

test('audited source filter metadata reproduces the observed 36 versus 9 outcomes', function () {
    require_once base_path('tests/CatalogImportFixtures.php');
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    applyCatalogFixture($actor, storage_path('app/imports/ari/d060.groups_2144.normalized.csv'));
    $this->seed(D060FilterSeeder::class);
    $group = Group::where('legacy_id', '2144')->sole();
    expect($group->filters()->count())->toBe(7)->and($group->subGroups()->count())->toBe(0);
    foreach ([['Working_Pressure', 'Connection_Type', 36], ['Connection_Type', 'Working_Pressure', 9]] as [$first, $second, $expected]) {
        $values = ['Working_Pressure' => '25 bar (360 psi)', 'Connection_Type' => 'Flange', 'Connection_Size' => '1″'];
        $state = [];
        foreach ([$first, $second, 'Connection_Size'] as $key) {
            $result = discover($group, $state, 'filter', [$key, $values[$key]]);
            $state = $result->state->toArray();
        }
        expect($result->products->total())->toBe($expected);
    }
});

test('malformed scalar snapshots render a repairable page instead of a server error', function () {
    $group = discoveryFixture();
    $this->actingAs(User::factory()->create())->get(route('catalog.groups.show', $group).'?d=invalid')->assertOk()->assertSee('data-catalog-repair="1"', false);
});

test('filtered discovery calculates only the total count and skips card fetching above the threshold', function () {
    $group = discoveryFixture();
    $group->update(['result_settings' => ['max_results' => 1]]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $result = discover($group, [], 'filter', ['Working_Pressure', '25']);
        $queries = collect(DB::getQueryLog())->pluck('query');
    } finally {
        DB::disableQueryLog();
    }
    expect($result->products->total())->toBe(2)->and($result->showProducts)->toBeFalse();
    expect($queries->filter(fn (string $sql): bool => str_contains(strtolower($sql), 'count(*)')))->toHaveCount(1);
    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'product_code')))->toBeEmpty();
});
