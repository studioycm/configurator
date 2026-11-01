<?php

use App\Models\Group;
use App\Models\Product;
use App\Services\CatalogAssetRecordSearch;
use App\Services\CatalogAssetRecordSequence;
use Illuminate\Support\Facades\DB;

test('image sequence uses the same default ordering and crosses native table pages', function () {
    $group = Group::factory()->create();
    $records = [];
    foreach (['E', 'B', 'B', 'A', 'D', 'C'] as $code) {
        $records[] = Product::factory()->for($group)->create(['product_code' => $code.'-'.count($records)]);
    }
    $sort = app(CatalogAssetRecordSearch::class)->sortDescriptor('product', null);
    $sequence = app(CatalogAssetRecordSequence::class);
    $query = Product::query()->where('group_id', $group->id);

    expect($sequence->position($query, $records[5]->id, $sort, 3))->toBe(['record_id' => $records[5]->id, 'position' => 4, 'total' => 6, 'page' => 2]);
    expect($sequence->neighbor($query, $records[2]->id, 'next', $sort, 3)['record_id'])->toBe($records[5]->id);
    expect($sequence->neighbor($query, $records[5]->id, 'previous', $sort, 3)['record_id'])->toBe($records[2]->id);
    expect($sequence->boundary($query, 'previous', $sort, 3)['record_id'])->toBe($records[0]->id);
    expect($sequence->boundary($query, 'next', $sort, 3)['record_id'])->toBe($records[3]->id);
    expect($sequence->neighbor($query, $records[0]->id, 'next', $sort, 3))->toBeNull();
});

test('image sequence preserves chosen scalar sort null placement and unique ties in bounded reads', function () {
    $a = Group::factory()->create(['legacy_id' => null]);
    $b = Group::factory()->create(['legacy_id' => null]);
    $c = Group::factory()->create(['legacy_id' => null]);
    $d = Group::factory()->create(['legacy_id' => 'Zed']);
    $sort = app(CatalogAssetRecordSearch::class)->sortDescriptor('group', 'legacy_id:desc');
    $sequence = app(CatalogAssetRecordSequence::class);
    $query = Group::query()->whereKey([$a->id, $b->id, $c->id, $d->id]);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $result = $sequence->neighbor($query, $a->id, 'next', $sort, 2);

    expect($result)->toBe(['record_id' => $b->id, 'position' => 3, 'total' => 4, 'page' => 2]);
    expect(DB::getQueryLog())->toHaveCount(4);
    DB::disableQueryLog();
    expect($sequence->boundary($query, 'next', $sort, 2)['record_id'])->toBe($d->id);
    expect($sequence->boundary($query, 'previous', $sort, 2)['record_id'])->toBe($c->id);
    expect($sequence->position($query->whereKey([$a->id, $d->id]), $b->id, $sort, 2))->toBeNull();
});
