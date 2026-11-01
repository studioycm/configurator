<?php

use App\Models\CatalogAsset;
use App\Services\CatalogAssetDisplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

function displayFixture(): CatalogAsset
{
    $asset = CatalogAsset::factory()->create();
    $media = Media::query()->create(['model_type' => $asset->getMorphClass(), 'model_id' => $asset->id,
        'collection_name' => 'original', 'name' => 'Synthetic', 'file_name' => 'synthetic.png', 'mime_type' => 'image/png',
        'disk' => 'catalog-assets', 'conversions_disk' => 'catalog-assets', 'size' => 100,
        'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => ['image-160' => true], 'responsive_images' => []]);
    $asset->forceFill(['preparation_status' => 'ready', 'prepared_at' => now(), 'original_media_id' => $media->id,
        'verified_metadata' => ['sha256' => $asset->sha256, 'bytes' => 100, 'width' => 40, 'height' => 20,
            'mime_type' => 'image/png', 'conversions' => ['image-160' => true], 'warnings' => []]])->save();

    return $asset;
}

test('saved image projection deduplicates then chunks beyond five hundred without truncation or file probes', function () {
    config(['filesystems.disks.catalog-assets.url' => 'https://images.example.test/managed']);
    $ids = [];
    for ($i = 0; $i < 501; $i++) {
        $ids[] = displayFixture()->id;
    }
    DB::enableQueryLog();
    $images = app(CatalogAssetDisplay::class)->forIds([...$ids, ...$ids]);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($images)->toHaveCount(501)->and(array_keys($images))->toBe($ids)->and($queries)->toHaveCount(2);
    foreach ($queries as $query) {
        expect(count($query['bindings']))->toBeLessThanOrEqual(505);
    }
    expect($images[$ids[500]]['url'])->toContain('/synthetic.png')
        ->and($images[$ids[500]]['thumbnail_url'])->toContain('/conversions/synthetic-image-160.jpg');
    expect(catalogImageTestDisk('catalog-assets')->allFiles())->toBe([]);
});

test('an empty image projection has no query and malformed identities fail before reads', function () {
    DB::enableQueryLog();
    expect(app(CatalogAssetDisplay::class)->forIds([]))->toBe([]);
    foreach ([[0], ['1'], [-1], [1.0], [null]] as $ids) {
        expect(fn () => app(CatalogAssetDisplay::class)->forIds($ids))->toThrow(ValidationException::class);
    }
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});

test('projection rejects an unready winner and media belonging to another asset', function () {
    $asset = displayFixture();
    $asset->forceFill(['preparation_status' => 'failed'])->save();
    expect(app(CatalogAssetDisplay::class)->forIds([$asset->id]))->toBe([]);
    $asset->forceFill(['preparation_status' => 'ready'])->save();
    $other = CatalogAsset::factory()->create();
    Media::query()->whereKey($asset->original_media_id)->update(['model_id' => $other->id]);
    expect(app(CatalogAssetDisplay::class)->forIds([$asset->id]))->toBe([]);
});

test('projection uses the original when a conversion is incomplete and rejects unsafe metadata', function () {
    $asset = displayFixture();
    Media::query()->whereKey($asset->original_media_id)->update(['generated_conversions' => '{}']);
    $images = app(CatalogAssetDisplay::class)->forIds([$asset->id]);
    expect($images[$asset->id]['thumbnail_url'])->toBe($images[$asset->id]['url']);
    Media::query()->whereKey($asset->original_media_id)->update(['file_name' => '../escape.png']);
    expect(app(CatalogAssetDisplay::class)->forIds([$asset->id]))->toBe([]);
});

test('projection rejects corrupt ready metadata exceeding accepted image limits', function (array $changes) {
    $asset = displayFixture();
    $metadata = [...$asset->verified_metadata, ...$changes];
    $asset->forceFill(['verified_metadata' => $metadata])->save();
    Media::query()->whereKey($asset->original_media_id)->update(['size' => $metadata['bytes']]);
    expect(app(CatalogAssetDisplay::class)->forIds([$asset->id]))->toBe([]);
})->with([
    'byte ceiling' => [['bytes' => 3145729]],
    'decoded pixels' => [['width' => 5001, 'height' => 5000]],
]);
