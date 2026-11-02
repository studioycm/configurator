<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\ReviewCatalogAssets;
use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetImportSource;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetReviewQuery;
use App\Services\CatalogAssetSources;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('actual MySQL review commits bounded journals with exact keys replay and exclusion carryover without owner writes', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $prefix = getenv('CATALOG_IMAGES_FIXTURE_PREFIX');
    $root = getenv('CATALOG_IMAGES_TEST_ROOT');
    if (! is_string($prefix) || preg_match('/\AIMG-[a-zA-Z0-9-]{8,48}\z/', $prefix) !== 1 || ! is_string($root)
        || ! str_starts_with($root, sys_get_temp_dir().'/catalog-images-') || ! is_dir($root) || is_link($root) || realpath($root) !== $root || (new Filesystem)->allFiles($root, hidden: true) !== []) {
        throw new RuntimeException('This nonresetting proof requires its admitted prefix and empty canonical private scratch root.');
    }
    config(['catalog-assets.enabled' => true, 'catalog-assets.testing_root' => $root, 'cache.default' => 'array']);
    foreach (['catalog-asset-imports', 'review-engine-source'] as $disk) {
        catalogImageTestDisk($disk);
    }
    foreach (['a.png', 'b.png', 'b.jpg', 'c.png'] as $filename) {
        $image = imagecreatetruecolor(40, 20);
        ob_start();
        str_ends_with($filename, '.jpg') ? imagejpeg($image) : imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        Storage::disk('review-engine-source')->put($filename, $bytes);
    }
    config(['catalog-assets.sources.fixture' => ['label' => $prefix, 'path' => Storage::disk('review-engine-source')->path(''), 'read_only' => true]]);
    $tables = ['users', 'groups', 'products', 'catalog_assets', 'catalog_asset_import_sources', 'media', 'catalog_asset_batches', 'catalog_asset_batch_items'];
    $beforeCounts = collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    $matches = User::query()->whereRaw('LOWER(email) = ?', ['ycm@data4.work'])->limit(2)->get();
    if ($matches->count() > 1 || ($matches->isNotEmpty() && $matches->first()->email !== 'ycm@data4.work')) {
        throw new RuntimeException('Conflicting manager identity.');
    }
    $actor = null;
    $createdActor = $matches->isEmpty();
    $group = null;
    $productIds = [];
    $sourceIds = [];
    $reviewIds = [];
    try {
        $actor = $matches->first() ?? User::factory()->create(['email' => 'ycm@data4.work', 'name' => $prefix.'-manager']);
        $actorBefore = $actor->fresh()->getAttributes();
        $this->actingAs($actor);
        expect(Group::where('name', $prefix.'-group')->exists())->toBeFalse()->and(CatalogAssetImportSource::where('source->label', $prefix)->exists())->toBeFalse();
        $group = Group::factory()->create(['name' => $prefix.'-group']);
        $products = Product::factory()->count(101)->for($group)->sequence(fn ($s) => ['product_code' => $prefix.'-'.sprintf('%03d', $s->index), 'legacy_id' => $prefix.'-'.sprintf('%03d', $s->index), 'profile_fields' => ['product_pic' => match ($s->index) {
            1 => 'b',2 => 'c',default => 'a'
        }, 'Pic.Key' => 'a', 'pic.key' => 'a', 'keep' => ['0', '001']]])->create();
        $productIds = $products->pluck('id')->all();
        $before = $products->mapWithKeys(fn ($p) => [$p->id => $p->fresh()->getAttributes()])->all();
        $groupBefore = $group->fresh()->getAttributes();
        $sources = app(CatalogAssetSources::class);
        $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
        $sourceIds[] = $source->id;
        for ($n = 0; $n < 12; $n++) {
            $p = $sources->status($actor, $source->id);
            if ($p['stage'] === 'ready') {
                break;
            }$p['traversal_complete'] ? $sources->finalize($actor, $source->id, 1, $p['revision']) : $sources->scanNext($actor, $source->id, 1, $p['revision']);
        }
        expect($sources->status($actor, $source->id)['stage'])->toBe('ready');
        $a = app(ReviewCatalogAssets::class);
        $launch = $a->freeze($actor, 'product.main_image', [], $productIds, [['column' => 'products.id', 'direction' => 'asc']]);
        $reviewIds[] = $launch['review_id'];
        $batch = $a->handle($actor, 'product', $productIds, ['review_id' => $launch['review_id'], 'scope_token' => $launch['token'], 'source_id' => $source->id, 'source_version' => 1, 'operation' => 'folder_matches', 'mode' => 'fill_missing', 'recipe' => []]);
        $scoped = $a->freeze($actor, 'product.main_image', ['list_key' => 'group-products', 'parent_id' => $group->id], $productIds, [['column' => 'products.id', 'direction' => 'asc']]);
        $reviewIds[] = $scoped['review_id'];
        expect(fn () => $a->handle($actor, 'product', $productIds, ['review_id' => $scoped['review_id'], 'scope_token' => $scoped['token'], 'source_id' => $source->id, 'source_version' => 1, 'operation' => 'folder_matches', 'mode' => 'fill_missing', 'recipe' => []]))->toThrow(ValidationException::class);
        $query = app(CatalogAssetReviewQuery::class);
        expect($query->fieldOptions($actor, $launch['token'], 'Pic'))->toHaveKeys(['Pic.Key', 'pic.key']);
        $a->buildNext($actor, $batch->id, 1, 0);
        expect($batch->fresh()->build_position)->toBe(100)->and(DB::transactionLevel())->toBe(0);
        $a->buildNext($actor, $batch->id, 1, 0);
        expect($batch->items()->count())->toBe(100);
        $a->buildNext($actor, $batch->id, 1, 1);
        expect($batch->fresh()->status)->toBe('review')->and($batch->items()->count())->toBe(101);
        $rows = $batch->items()->orderBy('position')->get();
        $duplicate = $rows[0]->getAttributes();
        unset($duplicate['id']);
        expect(fn () => DB::table('catalog_asset_batch_items')->insert($duplicate))->toThrow(QueryException::class);
        expect($rows[1]->match_status)->toBe('ambiguous');
        $a->setIncluded($actor, $batch->id, 1, [$rows[0]->id, $rows[2]->id], false);
        $entry = collect($sources->manifest($actor, $source->id, 1)['entries'])->firstWhere('name', 'b.png');
        $a->revise($actor, $batch->id, 1, ['override' => ['record_id' => $productIds[1], 'choice' => ['source_id' => $source->id, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']]]]);
        $batch->refresh();
        $revision = $batch->build_revision;
        $a->buildNext($actor, $batch->id, 2, $revision);
        $a->buildNext($actor, $batch->id, 2, $revision);
        $a->buildNext($actor, $batch->id, 2, $revision + 1);
        $rows = $batch->items()->orderBy('position')->get();
        expect($rows->take(3)->pluck('included')->all())->toBe([false, false, false])->and($rows[1]->match_status)->toBe('ready');
        $a->setIncluded($actor, $batch->id, 2, [$rows[1]->id], true);
        expect($batch->items()->orderBy('position')->limit(3)->pluck('included')->all())->toBe([false, true, false]);
        expect(fn () => $a->setIncluded($actor, $batch->id, 1, [$rows[0]->id], true))->toThrow(ValidationException::class);
        expect($query->items($actor, $batch->id, 2, ['query' => $prefix.'-001'])->count())->toBe(1);
        expect($query->items($actor, $batch->id, 2, ['query' => '%_'])->count())->toBe(0);
        expect($products->mapWithKeys(fn ($p) => [$p->id => $p->fresh()->getAttributes()])->all())->toBe($before)->and($group->fresh()->getAttributes())->toBe($groupBefore)->and($actor->fresh()->getAttributes())->toBe($actorBefore);
    } finally {
        if ($actor !== null) {
            $sourceIds = CatalogAssetImportSource::where('actor_id', $actor->id)->where('source->label', $prefix)->where('source->alias', 'fixture')->pluck('id')->all();
        }
        $reviewIds = array_values(array_unique([...$reviewIds, ...CatalogAssetBatch::whereIn('source_id', $sourceIds)->where('actor_id', $actor?->id)->pluck('id')->all()]));
        DB::table('catalog_asset_batch_items')->whereIn('batch_id', $reviewIds)->delete();
        DB::table('catalog_asset_batches')->whereIn('id', $reviewIds)->where('actor_id', $actor?->id)->delete();
        foreach ($reviewIds as $id) {
            Storage::disk('catalog-asset-imports')->deleteDirectory('reviews/'.$id);
        }
        DB::table('catalog_asset_import_sources')->whereIn('id', $sourceIds)->where('actor_id', $actor?->id)->delete();
        foreach ($sourceIds as $id) {
            Storage::disk('catalog-asset-imports')->deleteDirectory(strtolower($id));
        }
        if ($group !== null) {
            DB::table('products')->where('group_id', $group->id)->where('product_code', 'like', $prefix.'-%')->delete();
            DB::table('groups')->where('id', $group->id)->where('name', $prefix.'-group')->delete();
        }
        if ($createdActor && $actor !== null) {
            DB::table('users')->where('id',$actor->id)->where('name',$prefix.'-manager')->where('email','ycm@data4.work')->delete();
        }
    }
    foreach ($beforeCounts as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
    expect(DB::transactionLevel())->toBe(0);
});
