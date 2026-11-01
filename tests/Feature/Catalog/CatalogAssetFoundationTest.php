<?php

use App\Models\CatalogAsset;
use App\Models\Group;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('minimum image schema starts empty with independent commits and no batch manager tables', function () {
    expect(DB::transactionLevel())->toBe(0);
    $this->assertDatabaseCount('catalog_assets', 0);
    $this->assertDatabaseCount('catalog_asset_import_sources', 0);
    expect(Schema::hasTable('catalog_asset_batches'))->toBeFalse();
    expect(Schema::hasColumn('groups', 'main_image_id'))->toBeTrue()
        ->and(Schema::hasColumn('products', 'main_image_id'))->toBeTrue();
    expect(config('catalog-assets.enabled'))->toBeFalse()->and(config('catalog-assets.sources'))->toBe([]);
});

test('owner image identities are guarded and only valid managed assets may be referenced', function () {
    $asset = CatalogAsset::factory()->create();
    $group = Group::factory()->create();
    $product = Product::factory()->for($group)->create();
    $group->fill(['main_image_id' => $asset->id]);
    $product->fill(['main_image_id' => $asset->id]);
    expect($group->main_image_id)->toBeNull()->and($product->main_image_id)->toBeNull();
    $group->main_image_id = $asset->id;
    $group->save();
    expect($group->fresh()->mainImage->id)->toBe($asset->id);
    expect(fn () => $asset->delete())->toThrow(QueryException::class);
});

test('preparation identity is unique without deduplicating unrelated equal photographs', function () {
    $asset = CatalogAsset::factory()->create();
    expect(fn () => CatalogAsset::factory()->create(['import_key' => $asset->import_key]))->toThrow(QueryException::class);
    $other = CatalogAsset::factory()->create(['sha256' => $asset->sha256]);
    expect($other->id)->not->toBe($asset->id);
    expect($asset->toArray())->not->toHaveKeys(['source', 'preparation_token', 'verified_metadata', 'error_detail']);
});
