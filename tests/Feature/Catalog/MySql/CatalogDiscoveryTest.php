<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogDiscovery;
use Database\Seeders\D060FilterSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

test('mysql scalar json facets distinguish strings case blanks and json types', function () {
    $group = Group::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'value_order' => []]);
    foreach (['0', 0, null, '', 'Aa', 'aa', 'é', 'e', 'A ', ['Aa']] as $value) {
        Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => $value]]);
    }
    Product::factory()->for($group)->create(['properties' => []]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $result = app(CatalogDiscovery::class)->prepare($group->id, []);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(array_column($result->fields[0]['values'], 'value'))->toBe(['0', 'Aa', 'aa', 'é', 'e', 'A ']);
    foreach (['0', 'Aa', 'aa', 'é', 'e', 'A '] as $value) {
        expect(app(CatalogDiscovery::class)->prepare($group->id, [], 'filter', ['Working_Pressure', $value])->products->total())->toBe(1);
    }
    expect(count($queries))->toBeLessThanOrEqual(10);
    foreach ($result->products->items() as $product) {
        expect(array_keys($product->getAttributes()))->toBe(['id', 'product_code', 'properties']);
    }
});

test('profiles the audited corpus with bounded queries selected card columns and current mysql plans', function () {
    require_once base_path('tests/CatalogImportFixtures.php');
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    applyCatalogFixture($actor, storage_path('app/imports/ari/d060.groups_2144.normalized.csv'));
    $this->seed(D060FilterSeeder::class);
    $group = Group::where('legacy_id', '2144')->sole();
    $service = app(CatalogDiscovery::class);
    $pressure = $service->prepare($group->id, [], 'filter', ['Working_Pressure', '25 bar (360 psi)']);
    $filtered = $service->prepare($group->id, $pressure->state->toArray(), 'filter', ['Connection_Type', 'Flange']);
    $heavy = $filtered;
    foreach ($filtered->fields as $field) {
        if (isset($heavy->state->filters[$field['key']])) {
            continue;
        }
        $current = collect($heavy->fields)->firstWhere('key', $field['key']);
        $choice = collect($current['values'] ?? [])->first();
        if ($choice) {
            $heavy = $service->prepare($group->id, $heavy->state->toArray(), 'filter', [$field['key'], $choice['value']]);
        }
    }
    $samples = ['initial' => [], 'zero_compatibility' => $filtered->state->toArray(), 'heavily_filtered' => $heavy->state->toArray(), 'page_2' => array_replace($filtered->state->toArray(), ['page' => 2])];
    $profile = ['isolation' => DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level, 'samples' => []];
    foreach ($samples as $name => $state) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $started = hrtime(true);
        $result = $service->prepare($group->id, $state);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $cardQuery = collect($queries)->first(fn (array $query): bool => str_starts_with($query['query'], 'select `id`, `product_code`, `properties`'));
        expect($cardQuery)->not->toBeNull()->and(count($queries))->toBeLessThanOrEqual(32)->and($result->products->count())->toBe(min(10, $result->products->total()));
        $profile['samples'][$name] = ['query_count' => count($queries), 'sql_ms' => array_sum(array_column($queries, 'time')), 'elapsed_ms' => (hrtime(true) - $started) / 1e6, 'total' => $result->products->total(), 'card_json_bytes' => strlen($result->products->getCollection()->toJson()), 'explain' => DB::select('EXPLAIN '.$cardQuery['query'], $cardQuery['bindings'])];
    }
    $response = $this->actingAs($actor)->get(route('catalog.groups.show', $group))->assertOk();
    $profile['initial_html_bytes'] = strlen($response->getContent());
    File::ensureDirectoryExists(storage_path('framework/testing'));
    File::put(storage_path('framework/testing/catalog-discovery-profile.json'), json_encode($profile, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
});
