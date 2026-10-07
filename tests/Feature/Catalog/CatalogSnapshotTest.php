<?php

use App\Actions\SaveCatalogGroup;
use App\Actions\SaveGroupSettings;
use App\DTO\CatalogSnapshot;
use App\Livewire\Catalog\GroupShow;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\SubGroup;
use App\Models\User;
use App\Services\BuildCatalogSnapshot;
use App\Services\CatalogCardDisplay;
use App\Services\CatalogCards;
use App\Services\CatalogPolicy;
use App\Services\CatalogRevisions;
use App\Services\CatalogSnapshots;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

test('snapshot preserves exact strings and incomplete rows using one product read', function () {
    $group = Group::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'value_order' => ['01', '0'], 'value_labels' => ['0' => 'Zero']]);
    SubGroup::factory()->for($group)->create(['property_key' => 'Model', 'allowed_values' => ['Only preset']]);
    foreach (['0', '01', 'Case', 'case', '"quoted"', 'עברית', '', null, 12, false, ['bad']] as $value) {
        Product::factory()->for($group)->create(['description' => 'Not in dataset', 'parts' => ['Part1' => 'Secret'], 'properties' => ['Working_Pressure' => $value, 'Model' => 'Only preset', 'C' => 'Unrelated']]);
    }
    DB::enableQueryLog();
    $snapshot = app(BuildCatalogSnapshot::class)->build($group->id)->data;
    $queries = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/from ["`]products["`]/i', $query['query']));
    DB::disableQueryLog();
    expect($snapshot['revision'])->toBe('1')->and($snapshot['groupId'])->toBe((string) $group->id)
        ->and(array_column($snapshot['fields'][0]['options'], 'value'))->toBe(['01', '0', 'Case', 'case', '"quoted"', 'עברית'])
        ->and($snapshot['fields'][0]['options'][1]['label'])->toBe('Zero')
        ->and($snapshot['rows'])->toHaveCount(11)->and($queries)->toHaveCount(1)
        ->and($snapshot['diagnostics'])->toHaveCount(1)->and($snapshot['diagnostics'][0]['count'])->toBe(3)
        ->and(json_encode($snapshot))->not->toContain('Secret', 'Not in dataset', 'Unrelated', 'force_hide');
});

test('cache stores plain arrays in one replaceable key and replaces stale revisions', function () {
    $group = Group::factory()->create();
    $snapshots = app(CatalogSnapshots::class);
    $first = $snapshots->get($group->id);
    expect(Cache::get($snapshots->key($group->id)))->toBeArray();
    DB::transaction(fn () => app(CatalogRevisions::class)->advance([$group->id, $group->id]));
    $second = $snapshots->get($group->id);
    expect($first->data['revision'])->toBe('1')->and($second->data['revision'])->toBe('2');
    expect(Cache::get($snapshots->key($group->id))['revision'])->toBe('2');
});

test('revision changes once for changed settings and survives neither no-op nor rollback', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    $data = ['filters' => [], 'sub_groups' => [], 'result_settings' => [...CatalogPolicy::RESULT_SETTINGS, 'products_debounce_ms' => 100]];
    app(SaveGroupSettings::class)->handle($actor, $group, $data);
    expect((string) $group->fresh()->catalog_revision)->toBe('2');
    app(SaveGroupSettings::class)->handle($actor, $group, $data);
    expect((string) $group->fresh()->catalog_revision)->toBe('2');
    try {
        DB::transaction(function () use ($group): void {
            app(CatalogRevisions::class)->advance([$group->id]);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect((string) $group->fresh()->catalog_revision)->toBe('2');
});

test('renaming an ancestor invalidates descendant card metadata', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $root = Group::factory()->create();
    $leaf = Group::factory()->for($root, 'parent')->create();
    app(SaveCatalogGroup::class)->handle($actor, $root, ['name' => 'Renamed', 'parent_id' => null, 'configurator_id' => null]);
    expect((string) $root->fresh()->catalog_revision)->toBe('2')->and((string) $leaf->fresh()->catalog_revision)->toBe('2');
});

test('unchanged dataset response reads no products even with an expired cache', function () {
    $this->actingAs(User::factory()->create());
    $group = Group::factory()->create();
    $first = $this->getJson(route('catalog.groups.dataset', $group))->assertOk();
    Cache::forget(app(CatalogSnapshots::class)->key($group->id));
    DB::enableQueryLog();
    $this->withHeader('If-None-Match', $first->headers->get('ETag'))->getJson(route('catalog.groups.dataset', $group))->assertStatus(304);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(collect($queries)->filter(fn ($query) => str_contains($query['query'], 'products')))->toBeEmpty();
});

test('warm cards use one product query no SQL counts and preserve exact matching identities', function () {
    $group = Group::factory()->create(['result_settings' => ['max_results' => 'all']]);
    foreach (['Working_Pressure', 'Connection_Type', 'Connection_Size'] as $key) {
        GroupFilter::factory()->for($group)->create(['property_key' => $key]);
    }
    $products = [];
    foreach ([['25', 'Flange', '2'], ['16', 'Flange', '1'], ['25', 'Threaded', '1']] as $i => $values) {
        $products[] = Product::factory()->for($group)->create(['product_code' => 'FIXTURE-'.$i, 'properties' => array_combine(['Working_Pressure', 'Connection_Type', 'Connection_Size'], $values)]);
    }
    $snapshot = app(CatalogSnapshots::class)->get($group->id);
    $criteria = ['version' => 1, 'filters' => ['Working_Pressure' => '25', 'Connection_Type' => 'Flange', 'Connection_Size' => '1'], 'precedence' => ['filter:Working_Pressure', 'filter:Connection_Type', 'filter:Connection_Size'], 'subGroupId' => null];
    DB::enableQueryLog();
    $response = app(CatalogCards::class)->get($group->id, $criteria, $snapshot->revision(), 'request-1');
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    expect($response['status'])->toBe('ready')->and($response['total'])->toBe(1)
        ->and(implode('', $response['htmlChunks']))->toContain('FIXTURE-1')->not->toContain('FIXTURE-0', 'FIXTURE-2')
        ->and($queries->filter(fn ($query) => preg_match('/from ["`]products["`]/i', $query['query'])))->toHaveCount(1)
        ->and($queries->filter(fn ($query) => preg_match('/count\s*\(/i', $query['query'])))->toBeEmpty();
    $criteria['precedence'] = ['filter:Connection_Type', 'filter:Working_Pressure', 'filter:Connection_Size'];
    $response = app(CatalogCards::class)->get($group->id, $criteria, $snapshot->revision(), 'request-2');
    expect(implode('', $response['htmlChunks']))->toContain('FIXTURE-2')->not->toContain('FIXTURE-0', 'FIXTURE-1');
});

test('dataset access requires authentication and rejects branches and deleted groups', function () {
    $group = Group::factory()->create();
    $this->getJson(route('catalog.groups.dataset', $group))->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    Group::factory()->for($group, 'parent')->create();
    $this->getJson(route('catalog.groups.dataset', $group))->assertStatus(409);
    $this->getJson('/dashboard/catalog/groups/999999/dataset')->assertNotFound();
});

test('json card errors reject malformed shape foreign choices and missing resources without an HTML render', function () {
    $this->actingAs(User::factory()->create());
    $group = Group::factory()->create();
    $page = Livewire::test(GroupShow::class, ['group' => $group]);
    $page->call('loadCards', 'invalid', '1', 'bad-shape');
    expect($page->effects['returnsMeta'][0]['errors'])->toHaveKey('criteria');
    expect($page->effects)->not->toHaveKey('html');
    $criteria = ['version' => 1, 'filters' => ['foreign' => 'x'], 'subGroupId' => null, 'precedence' => ['filter:foreign']];
    $page->call('loadCards', $criteria, '1', 'bad-key');
    expect($page->effects['returnsMeta'][0]['errors'])->not->toBeEmpty();
    $group->delete();
    $page->call('loadCards', ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], '1', 'missing');
    expect($page->effects['returnsMeta'][0]['status'])->toBe(404);
});

test('card revisions reject obsolete datasets without reading products', function () {
    $group = Group::factory()->create();
    DB::transaction(fn () => app(CatalogRevisions::class)->advance([$group->id]));
    DB::flushQueryLog();
    DB::enableQueryLog();
    $result = app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], '1', 'old');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($result)->toMatchArray(['status' => 'refresh_required', 'revision' => '2', 'htmlChunks' => []]);
    expect(collect($queries)->filter(fn ($q) => str_contains($q['query'], 'products')))->toBeEmpty();
});

test('a persistent rebuild lock timeout never performs an unprotected rebuild', function () {
    $group = Group::factory()->create();
    $directory = sys_get_temp_dir().'/catalog-lock-'.Str::uuid();
    config(['cache.default' => 'catalog-lock-test', 'cache.stores.catalog-lock-test' => ['driver' => 'file', 'path' => $directory, 'lock_path' => $directory]]);
    $snapshots = app(CatalogSnapshots::class);
    $lock = Cache::lock($snapshots->key($group->id).':rebuild', 10);
    expect($lock->get())->toBeTrue();
    try {
        expect(fn () => $snapshots->get($group->id))->toThrow(ServiceUnavailableHttpException::class);
        expect(Cache::get($snapshots->key($group->id)))->toBeNull();
    } finally {
        $lock->release();
        File::deleteDirectory($directory);
    }
});

test('an expired rebuild lease cannot start another build after a concurrent revision change', function () {
    $group = Group::factory()->create();
    $directory = sys_get_temp_dir().'/catalog-lease-'.Str::uuid();
    config(['cache.default' => 'catalog-lease-test', 'cache.stores.catalog-lease-test' => ['driver' => 'file', 'path' => $directory, 'lock_path' => $directory]]);
    $expire = function () use ($group): void {
        DB::transaction(fn () => app(CatalogRevisions::class)->advance([$group->id]));
        $this->travel(61)->seconds();
    };
    $builder = new class($expire) extends BuildCatalogSnapshot
    {
        public int $builds = 0;

        public function __construct(private Closure $expire) {}

        public function build(int $groupId): CatalogSnapshot
        {
            $snapshot = parent::build($groupId);
            if (++$this->builds === 1) {
                ($this->expire)();
            }

            return $snapshot;
        }
    };
    $snapshots = new CatalogSnapshots($builder);
    try {
        expect(fn () => $snapshots->get($group->id))->toThrow(ServiceUnavailableHttpException::class);
        expect($builder->builds)->toBe(1);
        expect(Cache::get($snapshots->key($group->id)))->toBeNull();
    } finally {
        $this->travelBack();
        File::deleteDirectory($directory);
    }
});

test('card comparison preserves canonical strings and one shared field order', function () {
    $products = collect([
        Product::factory()->make(['properties' => ['Model' => '01', 'Working_Pressure' => '0', 'Connection_Type' => 'Case', 'C' => '']]),
        Product::factory()->make(['properties' => ['Model' => '1', 'Working_Pressure' => '0', 'Connection_Type' => 'case', 'C' => false]]),
        Product::factory()->make(['properties' => ['Model' => null, 'Working_Pressure' => '0', 'Connection_Type' => 'עברית "quoted"', 'C' => ['invalid']]]),
    ]);
    $display = app(CatalogCardDisplay::class)->compare($products, ['Model', 'Working_Pressure', 'Connection_Type', 'C'], ['Model' => 'Model label'], true);

    expect($display)->toBe(['fields' => [
        ['key' => 'Model', 'label' => 'Model label'],
        ['key' => 'Connection_Type', 'label' => 'Connection Type'],
    ], 'noticeReason' => null]);
    $all = app(CatalogCardDisplay::class)->compare($products, ['Model', 'Working_Pressure', 'Connection_Type', 'C'], [], false);
    expect(array_column($all['fields'], 'key'))->toBe(['Model', 'Working_Pressure', 'Connection_Type']);
});

test('card comparison explains single shared or unpopulated facts once', function (array $rows, bool $onlyDifferences, ?string $reason) {
    $products = collect($rows)->map(fn (array $properties) => Product::factory()->make(['properties' => $properties]));
    $display = app(CatalogCardDisplay::class)->compare($products, ['Model'], [], $onlyDifferences);

    expect($display)->toBe(['fields' => [], 'noticeReason' => $reason]);
})->with([
    'empty set' => [[], true, null],
    'single populated match' => [[['Model' => '0']], true, 'single_match'],
    'duplicate populated combinations' => [[['Model' => '0'], ['Model' => '0']], true, 'shared_properties'],
    'single unpopulated match' => [[['Model' => '']], true, 'no_populated_properties'],
    'invalid cells in all mode' => [[['Model' => 0], ['Model' => true]], false, 'no_populated_properties'],
]);

test('new snapshots contain appearance metadata but no card-only row values', function () {
    $group = Group::factory()->create(['result_settings' => ['card_properties' => ['Model'], 'card_show_labels' => true, 'card_property_columns' => 1]]);
    Product::factory()->for($group)->create(['properties' => ['Model' => 'Keep outside dataset']]);

    $snapshot = app(BuildCatalogSnapshot::class)->build($group->id)->data;

    expect($snapshot['schema'])->toBe(1)->and($snapshot['settings'])->toMatchArray([
        'card_show_labels' => true, 'card_property_columns' => 1, 'card_only_differences' => true,
    ]);
    expect(json_encode($snapshot))->not->toContain('Keep outside dataset');
});

test('old cached appearance metadata receives safe defaults without a snapshot rebuild', function () {
    $group = Group::factory()->create(['result_settings' => ['card_properties' => ['Model']]]);
    Product::factory()->for($group)->create(['product_code' => 'OLD-CACHE', 'properties' => ['Model' => 'One']]);
    $snapshots = app(CatalogSnapshots::class);
    $cached = $snapshots->get($group->id)->data;
    $cached['settings'] = ['card_properties' => ['Model'], 'cards_per_row' => 4, 'max_results' => 'all', 'products_debounce_ms' => 0];
    Cache::put($snapshots->key($group->id), $cached, 300);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], '1', 'old-cache');
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    expect($response)->toMatchArray(['status' => 'ready', 'propertiesNotice' => 'Only one product matches, so there are no differing properties to show.']);
    expect(implode('', $response['htmlChunks']))->toContain('OLD-CACHE')->not->toContain('>One<');
    expect($queries->filter(fn ($query) => preg_match('/from ["`]products["`]/i', $query['query'])))->toHaveCount(1);
    expect($queries)->toHaveCount(2);
});
