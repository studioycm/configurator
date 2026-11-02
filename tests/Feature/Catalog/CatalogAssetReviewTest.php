<?php

use App\Actions\ReviewCatalogAssets;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetBatchItem;
use App\Models\CatalogAssetImportSource;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetMatcher;
use App\Services\CatalogAssetReviewFiles;
use App\Services\CatalogAssetReviewQuery;
use App\Services\CatalogAssetSources;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['catalog-assets.enabled' => true, 'cache.default' => 'array']);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

function catalogReviewSource(array $names = ['a.png', 'b.png', 'b.jpg', 'c.png'], string $target = 'product.main_image'): string
{
    $disk = catalogImageTestDisk('review-fixture');
    $image = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    foreach ($names as $name) {
        if (str_ends_with($name, '.jpg')) {
            $photo = imagecreatetruecolor(40, 20);
            ob_start();
            imagejpeg($photo);
            $jpeg = ob_get_clean();
            imagedestroy($photo);
            $disk->put($name, $jpeg);
        } else {
            $disk->put($name, $bytes);
        }
    }
    config(['catalog-assets.sources.review_fixture' => ['label' => 'Owned review fixture', 'path' => $disk->path(''), 'read_only' => true]]);
    $s = app(CatalogAssetSources::class);
    $source = $s->startScan(auth()->user(), 'review_fixture', $target, [], false);
    for ($step = 0; $step < 12; $step++) {
        $progress = $s->status(auth()->user(), $source->id);
        if ($progress['stage'] === 'ready') {
            return $source->id;
        }
        if ($progress['traversal_complete']) {
            $s->finalize(auth()->user(), $source->id, 1, $progress['revision']);
        } else {
            $s->scanNext(auth()->user(), $source->id, 1, $progress['revision']);
        }
    }
    throw new RuntimeException('Fixture scanner did not finish its bounded requests.');
}

function catalogReviewCreate(array $ids, string $source): CatalogAssetBatch
{
    $a = app(ReviewCatalogAssets::class);
    $scope = $a->freeze(auth()->user(), 'product.main_image', [], $ids, [['column' => 'products.product_code', 'direction' => 'asc'], ['column' => 'products.id', 'direction' => 'asc']]);

    return $a->handle(auth()->user(), 'product', app(CatalogAssetReviewFiles::class)->openLaunch(auth()->user(), $scope['token'])['record_ids'], ['review_id' => $scope['review_id'], 'scope_token' => $scope['token'], 'source_id' => $source, 'source_version' => 1, 'operation' => 'folder_matches', 'mode' => 'fill_missing', 'recipe' => []]);
}

function catalogReviewFinish(CatalogAssetBatch $batch): void
{
    for ($step = 0; $step < 60 && $batch->fresh()->status === 'building'; $step++) {
        $batch->refresh();
        app(ReviewCatalogAssets::class)->buildNext(auth()->user(), $batch->id, $batch->preview_version, $batch->build_revision);
    }
    expect($batch->fresh()->status)->toBe('review');
}

test('review builds every frozen position in independent commits and replay never advances another chunk or owner', function () {
    $source = catalogReviewSource(['a.png']);
    $group = Group::factory()->create();
    $products = Product::factory()->count(101)->for($group)->sequence(fn ($s) => ['product_code' => sprintf('P-%03d', $s->index), 'profile_fields' => ['product_pic' => 'a']])->create();
    $before = $products[0]->fresh()->getAttributes();
    $groupBefore = $group->fresh()->getAttributes();
    $batch = catalogReviewCreate($products->pluck('id')->all(), $source);
    $products[100]->delete();
    $a = app(ReviewCatalogAssets::class);
    $a->buildNext(auth()->user(), $batch->id, 1, 0);
    expect($batch->fresh()->build_position)->toBe(100)->and($batch->items()->count())->toBe(100)->and(DB::transactionLevel())->toBe(0);
    $a->buildNext(auth()->user(), $batch->id, 1, 0);
    expect($batch->fresh()->build_position)->toBe(100);
    $a->buildNext(auth()->user(), $batch->id, 1, 1);
    expect($batch->fresh()->status)->toBe('review')->and($batch->items()->count())->toBe(101)->and($batch->items()->whereNull('record_fingerprint')->count())->toBe(1);
    expect($products[0]->fresh()->getAttributes())->toBe($before)->and(CatalogAsset::count())->toBe(0);
    expect(array_diff_key($group->fresh()->getAttributes(), array_flip(['catalog_revision', 'updated_at'])))->toBe(array_diff_key($groupBefore, array_flip(['catalog_revision', 'updated_at'])));
});

test('excluded A and C survive B resolution rebuild lost response and reload until B alone is explicitly included', function () {
    $source = catalogReviewSource();
    $products = collect(['a', 'b', 'c'])->map(fn ($ref) => Product::factory()->create(['product_code' => strtoupper($ref), 'profile_fields' => ['product_pic' => $ref]]));
    $batch = catalogReviewCreate($products->pluck('id')->all(), $source);
    catalogReviewFinish($batch);
    $a = app(ReviewCatalogAssets::class);
    $items = $batch->items()->orderBy('position')->get();
    expect($items->pluck('match_status')->all())->toBe(['ready', 'ambiguous', 'ready']);
    $a->setIncluded(auth()->user(), $batch->id, 1, [$items[0]->id, $items[2]->id], false);
    $entry = collect(app(CatalogAssetSources::class)->manifest(auth()->user(), $source, 1)['entries'])->firstWhere('name', 'b.png');
    $a->revise(auth()->user(), $batch->id, 1, ['override' => ['record_id' => $products[1]->id, 'choice' => ['source_id' => $source, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']]]]);
    $batch->refresh();
    $version = $batch->preview_version;
    $revision = $batch->build_revision;
    $a->buildNext(auth()->user(), $batch->id, $version, $revision);
    $a->buildNext(auth()->user(), $batch->id, $version, $revision);
    $fresh = CatalogAssetBatch::findOrFail($batch->id);
    $rows = $fresh->items()->orderBy('position')->get();
    expect($rows->pluck('included')->all())->toBe([false, false, false])->and($rows[1]->match_status)->toBe('ready');
    expect(fn () => $a->setIncluded(auth()->user(), $batch->id, 1, [$items[0]->id], true))->toThrow(ValidationException::class);
    $a->setIncluded(auth()->user(), $batch->id, $version, [$rows[1]->id], true);
    expect($fresh->items()->orderBy('position')->pluck('included')->all())->toBe([false, true, false]);
    expect(CatalogAsset::count())->toBe(0)->and(Product::whereNotNull('main_image_id')->count())->toBe(0);
});

test('creation retries preserve one journal and conflicting meaning is rejected', function () {
    $source = catalogReviewSource(['a.png']);
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $a = app(ReviewCatalogAssets::class);
    $scope = $a->freeze(auth()->user(), 'product.main_image', [], [$p->id], [['column' => 'products.id', 'direction' => 'asc']]);
    $intent = ['review_id' => $scope['review_id'], 'scope_token' => $scope['token'], 'operation' => 'folder_matches', 'source_id' => $source, 'source_version' => 1, 'recipe' => [], 'mode' => 'fill_missing'];
    $batch = $a->handle(auth()->user(), 'product', [$p->id], $intent);
    expect($a->handle(auth()->user(), 'product', [$p->id], $intent)->id)->toBe($batch->id)->and(CatalogAssetBatch::count())->toBe(1);
    expect(fn () => $a->handle(auth()->user(), 'product', [$p->id], [...$intent, 'mode' => 'replace_existing']))->toThrow(ValidationException::class);
    expect(fn () => $a->buildNext(auth()->user(), $batch->id, 1, 9))->toThrow(ValidationException::class);
});

test('live build lease is read only and expired competing worker is fenced from publishing', function () {
    $source = catalogReviewSource(['a.png']);
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $batch = catalogReviewCreate([$p->id], $source);
    $token = (string) Str::ulid();
    $batch->forceFill(['build_token' => $token, 'build_expires_at' => now()->addMinute()])->save();
    $a = app(ReviewCatalogAssets::class);
    expect($a->buildNext(auth()->user(), $batch->id, 1, 0)['outcome'])->toBe('busy')->and($batch->items()->count())->toBe(0);
    expect($batch->fresh()->build_token)->toBe($token);
    $batch->forceFill(['build_expires_at' => now()->subSecond()])->save();
    $real = app(CatalogAssetMatcher::class);
    $taken = false;
    $this->partialMock(CatalogAssetMatcher::class, function ($mock) use ($real, $batch, &$taken) {
        $mock->shouldReceive('reviewChunk')->andReturnUsing(function (...$args) use ($real, $batch, &$taken) {
            if (! $taken) {
                $taken = true;
                $batch->refresh()->forceFill(['build_expires_at' => now()->subSecond()])->save();
                app(ReviewCatalogAssets::class)->buildNext(auth()->user(), $batch->id, 1, 0);
            }

            return $real->reviewChunk(...$args);
        });
    });
    expect(fn () => app(ReviewCatalogAssets::class)->buildNext(auth()->user(), $batch->id, 1, 0))->toThrow(ValidationException::class);
    expect($batch->fresh()->status)->toBe('review')->and($batch->items()->count())->toBe(1)->and($batch->fresh()->build_revision)->toBe(1);
});

test('failure before publication leaves no partial rows or cursor advancement', function () {
    $source = catalogReviewSource(['a.png']);
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $batch = catalogReviewCreate([$p->id], $source);
    $this->partialMock(CatalogAssetMatcher::class, function ($mock) {
        $mock->shouldReceive('reviewChunk')->once()->andThrow(new RuntimeException('Synthetic pre-publication failure'));
    });
    expect(fn () => app(ReviewCatalogAssets::class)->buildNext(auth()->user(), $batch->id, 1, 0))->toThrow(RuntimeException::class);
    expect($batch->items()->count())->toBe(0)->and($batch->fresh()->build_position)->toBe(0)->and($batch->fresh()->build_revision)->toBe(0)->and($batch->fresh()->status)->toBe('failed')->and($batch->fresh()->build_token)->toBeNull();
    expect($p->fresh()->main_image_id)->toBeNull()->and(CatalogAsset::count())->toBe(0);
});

test('missing private evidence and changed source hash stop without publishing or recreating files', function () {
    $source = catalogReviewSource(['a.png']);
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $batch = catalogReviewCreate([$p->id], $source);
    $path = $batch->manifest_path;
    Storage::disk('catalog-asset-imports')->delete($path);
    expect(fn () => app(ReviewCatalogAssets::class)->buildNext(auth()->user(), $batch->id, 1, 0))->toThrow(DomainException::class);
    expect(Storage::disk('catalog-asset-imports')->exists($path))->toBeFalse()->and($batch->items()->count())->toBe(0)->and($batch->fresh()->build_position)->toBe(0);
    $other = catalogReviewCreate([$p->id], $source);
    CatalogAssetImportSource::findOrFail($source)->forceFill(['manifest_sha256' => str_repeat('f', 64)])->save();
    expect(fn () => app(ReviewCatalogAssets::class)->buildNext(auth()->user(), $other->id, 1, 0))->toThrow(DomainException::class);
    expect($other->items()->count())->toBe(0)->and($other->fresh()->build_position)->toBe(0);
});

test('expiry blocks inclusion and explicit fresh revision preserves exclusions and deadline semantics', function () {
    $source = catalogReviewSource(['a.png']);
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $batch = catalogReviewCreate([$p->id], $source);
    catalogReviewFinish($batch);
    $a = app(ReviewCatalogAssets::class);
    $row = $batch->items()->sole();
    $a->setIncluded(auth()->user(), $batch->id, 1, [$row->id], false);
    $this->travel(25)->hours();
    expect($a->status(auth()->user(), $batch->id)['stage'])->toBe('expired');
    expect(fn () => $a->setIncluded(auth()->user(), $batch->id, 1, [$row->id], true))->toThrow(ValidationException::class);
    $a->revise(auth()->user(), $batch->id, 1, ['recipe' => []]);
    catalogReviewFinish($batch->fresh());
    expect($batch->items()->sole()->included)->toBeFalse()->and($a->status(auth()->user(), $batch->id)['explicitly_excluded'])->toBe(1);
});

test('source and exact field lookups stay within actor target and selected records', function () {
    $source = catalogReviewSource(['a.png']);
    $p = Product::factory()->create(['profile_fields' => ['Pic.Key' => 'a', 'pic.key' => 'a', 'product_pic' => 'a']]);
    Product::factory()->create(['profile_fields' => ['secret_other_record' => 'x']]);
    $a = app(ReviewCatalogAssets::class);
    $launch = $a->freeze(auth()->user(), 'product.main_image', [], [$p->id], [['column' => 'products.id', 'direction' => 'asc']]);
    $q = app(CatalogAssetReviewQuery::class);
    $keys = $q->fieldOptions(auth()->user(), $launch['token'], '');
    expect($keys)->toHaveKeys(['Pic.Key', 'pic.key'])->not->toHaveKey('secret_other_record');
    expect($q->sourceOptions(auth()->user(), $launch['token'], 'Owned review'))->toHaveKey('source:'.$source);
    expect($q->sourceOptions(auth()->user(), $launch['token'], '%_'))->toBe([]);
    $foreign = User::factory()->create();
    Gate::define('manage-catalog', fn () => true);
    expect(fn () => $a->owned($foreign, $launch['review_id']))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(CatalogAssetReviewFiles::class)->openLaunch($foreign, $launch['token']))->toThrow(ValidationException::class);
    CatalogAssetImportSource::findOrFail($source)->forceFill(['actor_id' => $foreign->id])->save();
    expect($q->sourceOptions(auth()->user(), $launch['token'], 'Owned review'))->not->toHaveKey('source:'.$source);
    expect(fn () => $a->handle(auth()->user(), 'product', [$p->id], ['review_id' => $launch['review_id'], 'scope_token' => $launch['token'], 'operation' => 'folder_matches', 'source_id' => $source, 'source_version' => 1, 'recipe' => [], 'mode' => 'fill_missing']))->toThrow(ModelNotFoundException::class);
});

test('one image Group review preserves the full Group and assets while rejecting Group filename recipes', function () {
    $source = catalogReviewSource(['a.png'], 'group.main_image');
    $group = Group::factory()->create(['description' => 'Keep me', 'catalog_attributes' => ['x' => ['0', '001']]]);
    $before = $group->fresh()->getAttributes();
    $a = app(ReviewCatalogAssets::class);
    $launch = $a->freeze(auth()->user(), 'group.main_image', [], [$group->id], [['column' => 'groups.id', 'direction' => 'asc']]);
    $entry = app(CatalogAssetSources::class)->manifest(auth()->user(), $source, 1)['entries'][0];
    $intent = ['review_id' => $launch['review_id'], 'scope_token' => $launch['token'], 'operation' => 'one_image', 'source_id' => $source, 'source_version' => 1, 'recipe' => [], 'mode' => 'fill_missing', 'entry_choice' => ['source_id' => $source, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']]];
    $batch = $a->handle(auth()->user(), 'group', [$group->id], $intent);
    catalogReviewFinish($batch);
    expect($batch->items()->sole()->match_status)->toBe('ready')->and($group->fresh()->getAttributes())->toBe($before)->and(CatalogAsset::count())->toBe(0);
    expect(fn () => $a->handle(auth()->user(), 'group', [$group->id], [...$intent, 'operation' => 'folder_matches']))->toThrow(ValidationException::class);
});

test('a row insert failure rolls back the entire chunk and durable selection initialization', function () {
    $source = catalogReviewSource(['a.png']);
    $products = Product::factory()->count(3)->create(['profile_fields' => ['product_pic' => 'a']]);
    $batch = catalogReviewCreate($products->pluck('id')->all(), $source);
    $inserts = 0;
    CatalogAssetBatchItem::creating(function ($row) use ($batch, &$inserts) {
        if ($row->batch_id === $batch->id && ++$inserts === 2) {
            throw new RuntimeException('Synthetic row failure');
        }
    });
    expect(fn () => app(ReviewCatalogAssets::class)->buildNext(auth()->user(), $batch->id, 1, 0))->toThrow(RuntimeException::class);
    expect($batch->items()->count())->toBe(0)->and($batch->fresh()->build_position)->toBe(0)->and($batch->fresh()->recipe['review_selection'])->toBe([]);
});

test('empty automatic identifiers stay invalid and excluded while an explicit exact file choice can resolve them', function () {
    $source = catalogReviewSource(['---.png', '001.png']);
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => '---']]);
    $a = app(ReviewCatalogAssets::class);
    foreach (['exact', 'contains'] as $comparison) {
        $launch = $a->freeze(auth()->user(), 'product.main_image', [], [$p->id], [['column' => 'products.id', 'direction' => 'asc']]);
        $batch = $a->handle(auth()->user(), 'product', [$p->id], ['review_id' => $launch['review_id'], 'scope_token' => $launch['token'], 'source_id' => $source, 'source_version' => 1, 'operation' => 'folder_matches', 'mode' => 'fill_missing', 'recipe' => ['comparison' => $comparison, 'ignore_separators' => true]]);
        catalogReviewFinish($batch);
        $row = $batch->items()->sole();
        expect($row->match_status)->toBe('invalid')->and($row->included)->toBeFalse()->and($row->snapshot['reason'])->toBe('identifier_empty')->and($row->snapshot['chosen_entry'])->toBeNull();
        expect(app(CatalogAssetReviewQuery::class)->candidates(auth()->user(), $batch->id, 1, $row->id, 1)['total'])->toBe(0);
        expect(fn () => $a->setIncluded(auth()->user(), $batch->id, 1, [$row->id], true))->toThrow(ValidationException::class);
        $entry = collect(app(CatalogAssetSources::class)->manifest(auth()->user(), $source, 1)['entries'])->firstWhere('name', '---.png');
        $a->revise(auth()->user(), $batch->id, 1, ['override' => ['record_id' => $p->id, 'choice' => ['source_id' => $source, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']]]]);
        catalogReviewFinish($batch->fresh());
        $resolved = $batch->items()->sole();
        expect($resolved->match_status)->toBe('ready')->and($resolved->included)->toBeFalse()->and($resolved->snapshot['manual'])->toBeTrue();
    }
    expect($p->fresh()->main_image_id)->toBeNull()->and(CatalogAsset::count())->toBe(0);
});
