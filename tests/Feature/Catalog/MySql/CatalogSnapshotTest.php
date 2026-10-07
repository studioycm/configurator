<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Services\BuildCatalogSnapshot;
use App\Services\CatalogCards;
use App\Services\CatalogSnapshots;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('mysql snapshot projection preserves strings without reading unrelated properties', function () {
    $group = Group::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'value_order' => []]);
    foreach (['0', 0, false, null, '', '01', 'Aa', 'aa', 'é', 'e', 'A ', ['Aa']] as $value) {
        Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => $value, 'Model' => 'Unrelated']]);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    $snapshot = app(BuildCatalogSnapshot::class)->build($group->id);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(array_column($snapshot->data['fields'][0]['options'], 'value'))->toBe(['0', '01', 'Aa', 'aa', 'é', 'e', 'A ']);
    $products = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'from `products`'));
    expect($products)->toHaveCount(1)->and($products->first()['query'])->not->toContain('select *', 'parts', 'extra_data');
    expect($snapshot->data['rows'])->toHaveCount(12)->and($snapshot->data['diagnostics'][0]['count'])->toBe(3);
});

test('repeatable read keeps revisions and product data coherent across concurrent committed writes', function () {
    expect(config('database.connections.mysql.isolation_level'))->toBe('REPEATABLE READ');
    // This test deliberately leaves fixture isolation; both connections need committed fixtures.
    DB::commit();
    $group = null;
    $writerName = 'catalog_snapshot_writer';
    config(['database.connections.'.$writerName => config('database.connections.mysql')]);
    $writer = DB::connection($writerName);
    $phase = null;
    try {
        expect(DB::selectOne('SELECT @@transaction_isolation AS level')->level)->toBe('REPEATABLE-READ');
        $group = Group::factory()->create();
        GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'value_order' => []]);
        $product = Product::factory()->for($group)->create(['product_code' => 'BEFORE', 'properties' => ['Working_Pressure' => '25']]);
        $phase = 'snapshot';
        DB::listen(function (QueryExecuted $query) use (&$phase, $writer, $group, $product): void {
            if ($query->connectionName !== 'mysql' || $phase === null || ! str_contains($query->sql, 'from `groups`')
                || ! str_contains($query->sql, $phase === 'cards' ? 'catalog_revision' : 'limit 1')) {
                return;
            }
            $currentPhase = $phase;
            $phase = null;
            $writer->transaction(function () use ($writer, $group, $product, $currentPhase): void {
                $writer->table('products')->where('id', $product->id)->update(['product_code' => $currentPhase === 'snapshot' ? 'MIDDLE' : 'AFTER', 'properties' => json_encode(['Working_Pressure' => $currentPhase === 'snapshot' ? '16' : '10'])]);
                $writer->table('groups')->where('id', $group->id)->increment('catalog_revision');
            });
        });
        $old = app(BuildCatalogSnapshot::class)->build($group->id);
        expect($old->revision())->toBe('1')->and($old->data['fields'][0]['options'][0]['value'])->toBe('25');
        $snapshots = app(CatalogSnapshots::class);
        $current = $snapshots->get($group->id);
        expect($current->revision())->toBe('2')->and($current->data['fields'][0]['options'][0]['value'])->toBe('16');
        $phase = 'cards';
        $cards = app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], '2', 'concurrent-card');
        expect($cards['revision'])->toBe('2')->and(implode('', $cards['htmlChunks']))->toContain('MIDDLE')->not->toContain('AFTER');
        expect($snapshots->revision($group->id))->toBe('3');
    } finally {
        $phase = null;
        if ($group !== null) {
            Cache::forget(app(CatalogSnapshots::class)->key($group->id));
            DB::table('products')->where('group_id', $group->id)->delete();
            DB::table('groups')->where('id', $group->id)->delete();
        }
        DB::purge($writerName);
        DB::beginTransaction();
    }
});
