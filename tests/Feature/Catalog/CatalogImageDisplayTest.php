<?php

use App\Livewire\Catalog\ProductConfigurator;
use App\Models\CatalogAsset;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogCards;
use App\Services\CatalogNavigation;
use App\Services\CatalogProductComparison;
use App\Services\CatalogSnapshots;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    config(['catalog-assets.enabled' => true, 'filesystems.disks.catalog-assets.url' => 'https://images.example.test/managed']);
});

/** Synthetic SQL metadata only; real native assignment is covered in CatalogMainImageFieldTest. */
function catalogReadImage(): CatalogAsset
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

function catalogReadCards(Group $group): array
{
    $snapshot = app(CatalogSnapshots::class)->get($group->id);

    return app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], $snapshot->revision(), 'image-read');
}

/** @return list<array<string,mixed>> */
function catalogImageQueries(): array
{
    return array_values(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'catalog_assets')));
}

test('own ready images reach only eligible Group cards and do not change tree identity', function () {
    $actor = User::factory()->create();
    $parent = Group::factory()->create();
    $leaf = Group::factory()->for($parent, 'parent')->create();
    Product::factory()->for($leaf)->create();
    $hidden = Group::factory()->create(['is_active' => false]);
    Product::factory()->for($hidden)->create();
    $image = catalogReadImage();
    $parent->forceFill(['main_image_id' => $image->id])->save();
    $hidden->forceFill(['main_image_id' => $image->id])->save();
    $before = app(CatalogNavigation::class)->get($actor, []);
    expect($before['cards'][0]['mainImage'])->toBeNull();
    $leaf->forceFill(['main_image_id' => $image->id])->save();
    DB::enableQueryLog();
    DB::flushQueryLog();

    $after = app(CatalogNavigation::class)->get($actor, []);

    expect(array_column($after['cards'], 'id'))->toBe([$leaf->id]);
    expect($after['cards'][0]['mainImage']['id'])->toBe($image->id)
        ->and($after['cards'][0]['mainImage']['url'])->toContain('/synthetic.png')
        ->and($after['treeVersion'])->toBe($before['treeVersion'])
        ->and(catalogImageQueries())->toHaveCount(1);
    DB::disableQueryLog();
});

test('comparison projects one scoped pair query in caller order and the Product child reads its own saved image', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);
    $group = Group::factory()->create();
    $a = Product::factory()->for($group)->create();
    $b = Product::factory()->for($group)->create();
    $image = catalogReadImage();
    $a->forceFill(['main_image_id' => $image->id])->save();
    DB::enableQueryLog();
    DB::flushQueryLog();

    $pair = app(CatalogProductComparison::class)->get($actor, $group->id, [$b->id, $a->id]);

    expect(array_column($pair['products'], 'id'))->toBe([(string) $b->id, (string) $a->id]);
    expect($pair['products'][0]['mainImage'])->toBeNull();
    expect($pair['products'][1]['mainImage']['id'])->toBe($image->id)->and(catalogImageQueries())->toHaveCount(1);
    DB::disableQueryLog();
    Livewire::test(ProductConfigurator::class, ['productId' => $a->id])
        ->assertViewHas('mainImage', fn (?array $value): bool => $value['id'] === $image->id);
    Livewire::test(ProductConfigurator::class, ['productId' => $b->id])->assertViewHas('mainImage', null);
});

test('disabled image reads avoid explicit pointers Asset Media and schema probes on every consumer', function () {
    config(['catalog-assets.enabled' => false]);
    $actor = User::factory()->create();
    $this->actingAs($actor);
    $group = Group::factory()->create();
    $image = catalogReadImage();
    $group->forceFill(['main_image_id' => $image->id])->save();
    $products = Product::factory()->count(2)->for($group)->create();
    $products[0]->forceFill(['main_image_id' => $image->id])->save();
    catalogReadCards($group);
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(app(CatalogNavigation::class)->get($actor, [])['cards'][0]['mainImage'])->toBeNull();
    $pair = app(CatalogProductComparison::class)->get($actor, $group->id, $products->modelKeys());
    expect(array_column($pair['products'], 'mainImage'))->toBe([null, null]);
    expect(implode('', catalogReadCards($group)['htmlChunks']))->not->toContain('https://images.example.test');
    Livewire::test(ProductConfigurator::class, ['productId' => $products[0]->id])->assertViewHas('mainImage', null);

    foreach (DB::getQueryLog() as $query) {
        expect(strtolower($query['query']))->not->toContain('main_image_id', 'catalog_assets', '"media"', 'sqlite_master', 'pragma', 'information_schema');
    }
    DB::disableQueryLog();
});

test('saved Product card and page render managed images with intrinsic dimensions and readable page alt', function () {
    $this->actingAs(User::factory()->create());
    $group = Group::factory()->create();
    $product = Product::factory()->for($group)->create(['product_code' => 'VALVE-<01>']);
    $image = catalogReadImage();
    $product->forceFill(['main_image_id' => $image->id])->save();

    $cards = implode('', catalogReadCards($group)['htmlChunks']);
    expect($cards)->toContain('https://images.example.test/managed/'.$image->original_media_id.'/conversions/synthetic-image-160.jpg', 'width="40"', 'height="20"', 'alt=""');
    $html = $this->get(route('catalog.products.show', $product))->assertOk()->getContent();
    expect($html)->toContain('alt="VALVE-&lt;01&gt;"', 'catalog-main-image', 'width="40"')
        ->not->toContain('alt="VALVE-<01>"');
});

test('full matching Product images are deduplicated and chunked without changing all-result property differences', function (int $count, bool $unique, int $expectedQueries) {
    $group = Group::factory()->create(['result_settings' => ['max_results' => 'all', 'card_properties' => ['pressure'], 'card_only_differences' => true]]);
    $shared = $unique ? null : catalogReadImage();
    for ($index = 0; $index < $count; $index++) {
        $image = $shared ?? catalogReadImage();
        $product = Product::factory()->for($group)->create(['product_code' => sprintf('IMG-%04d', $index), 'profile_fields' => ['pressure' => $index === $count - 1 ? 'different' : 'common']]);
        $product->forceFill(['main_image_id' => $image->id])->save();
    }
    app(CatalogSnapshots::class)->get($group->id);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $result = catalogReadCards($group);

    expect($result['total'])->toBe($count)->and(count($result['htmlChunks']))->toBe((int) ceil($count / 24))
        ->and(catalogImageQueries())->toHaveCount($expectedQueries);
    expect($result['htmlChunks'][0])->toContain('https://images.example.test/managed/');
    if ($count > 1) {
        expect($result['htmlChunks'][0])->toContain('data-property-key="pressure"', 'common');
    } else {
        expect($result['htmlChunks'][0])->not->toContain('data-property-key="pressure"');
    }
    foreach (catalogImageQueries() as $query) {
        expect(count($query['bindings']))->toBeLessThanOrEqual(505);
    }
    DB::disableQueryLog();
    expect(json_encode(app(CatalogSnapshots::class)->get($group->id)->data))->not->toContain('main_image_id', 'thumbnail_url', 'images.example.test');
})->with(['one' => [1, true, 1], 'one chunk' => [24, true, 1], 'five hundred' => [500, true, 1], 'five hundred and one unique' => [501, true, 2], 'five hundred and one repeated' => [501, false, 1]]);

test('empty threshold blocked and pointerless Product reads never query image metadata', function (string $condition) {
    $group = Group::factory()->create(['result_settings' => ['max_results' => $condition === 'threshold' ? 1 : 'all']]);
    if ($condition !== 'empty') {
        $products = Product::factory()->count(2)->for($group)->create();
        if ($condition === 'threshold') {
            $products[0]->forceFill(['main_image_id' => catalogReadImage()->id])->save();
        }
    }
    app(CatalogSnapshots::class)->get($group->id);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $result = catalogReadCards($group);

    expect(catalogImageQueries())->toBe([]);
    expect($result['status'])->toBe(match ($condition) {
        'empty' => 'empty', 'threshold' => 'above_threshold', default => 'ready'
    });
    if ($condition !== 'pointerless') {
        expect($result['htmlChunks'])->toBe([]);
    }
    DB::disableQueryLog();
})->with(['empty', 'threshold', 'pointerless']);

test('unready corrupt foreign-media or wrong-disk pointers produce placeholders without guessing from extra data', function (string $damage) {
    $actor = User::factory()->create();
    $this->actingAs($actor);
    $group = Group::factory()->create();
    $product = Product::factory()->for($group)->create(['profile_fields' => ['product_pic' => '/unmanaged/D60EL01.jpg']]);
    $other = Product::factory()->for($group)->create();
    $image = catalogReadImage();
    $group->forceFill(['main_image_id' => $image->id])->save();
    $product->forceFill(['main_image_id' => $image->id])->save();
    match ($damage) {
        'unready' => $image->forceFill(['preparation_status' => 'preparing'])->save(),
        'corrupt' => $image->forceFill(['verified_metadata' => ['broken' => true]])->save(),
        'foreign-media' => Media::whereKey($image->original_media_id)->update(['model_id' => catalogReadImage()->id]),
        'wrong-disk' => Media::whereKey($image->original_media_id)->update(['disk' => 'local']),
    };

    expect(app(CatalogNavigation::class)->get($actor, [])['cards'][0]['mainImage'])->toBeNull();
    expect(array_column(app(CatalogProductComparison::class)->get($actor, $group->id, [$product->id, $other->id])['products'], 'mainImage'))->toBe([null, null]);
    expect(implode('', catalogReadCards($group)['htmlChunks']))->not->toContain('https://images.example.test', '/unmanaged/D60EL01.jpg');
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->assertViewHas('mainImage', null);
})->with(['unready', 'corrupt', 'foreign-media', 'wrong-disk']);

test('a materialized image frame is a pure escaped view with no persistence or storage reads', function () {
    $image = ['id' => 1, 'url' => 'https://images.example.test/a.png', 'thumbnail_url' => 'https://images.example.test/a.png?name="<unsafe>', 'width' => 40, 'height' => 20];
    DB::enableQueryLog();
    DB::flushQueryLog();

    $html = view('components.catalog.main-image', ['image' => $image, 'decorative' => false, 'alt' => 'Valve "<01>'])->render();

    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
    expect($html)->toContain('alt="Valve &quot;&lt;01&gt;"', 'a.png?name=&quot;&lt;unsafe&gt;')->not->toContain('alt="Valve "<01>');
});
