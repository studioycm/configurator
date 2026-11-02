<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\ApplyCatalogAssets;
use App\Actions\ProcessNextCatalogAssetBatchItem;
use App\Actions\ReviewCatalogAssets;
use App\Livewire\Catalog\AssetAssignment;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetImportSource;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetIntake;
use App\Services\CatalogAssetSources;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** @return array{string, string} */
function intakeCapacityBoundary(): array
{
    assertCatalogMySqlSafety(app());
    $root = getenv('CATALOG_IMAGES_TEST_ROOT');
    $prefix = getenv('CATALOG_IMAGES_FIXTURE_PREFIX');
    $files = new Filesystem;
    if (getenv('CATALOG_INTAKE_CASE') !== 'capacity' || ! is_string($prefix) || preg_match('/\AIMG-[a-zA-Z0-9-]{8,48}\z/', $prefix) !== 1
        || ! is_string($root) || ! str_starts_with($root, sys_get_temp_dir().'/catalog-images-') || ! is_dir($root)
        || is_link($root) || realpath($root) !== $root || $files->allFiles($root, hidden: true) !== []) {
        throw new RuntimeException('The admitted capacity case requires its exclusive empty canonical root and fixture prefix.');
    }
    config(['catalog-assets.enabled' => true, 'catalog-assets.testing_root' => $root, 'cache.default' => 'array',
        'media-library.temporary_directory' => $root.'/media-tmp']);
    foreach (['catalog-asset-imports', 'catalog-assets', 'tmp-for-tests'] as $disk) {
        $files->ensureDirectoryExists($root.'/'.$disk, 0700);
        $configuration = array_replace(config('filesystems.disks.'.$disk, []), ['driver' => 'local', 'root' => $root.'/'.$disk, 'throw' => true]);
        config(['filesystems.disks.'.$disk => $configuration]);
        Storage::set($disk, Storage::build($configuration));
    }
    $files->ensureDirectoryExists($root.'/input', 0700);

    return [$root, $prefix];
}

/** Valid PNG ancillary chunk, rather than an invented UploadedFile size or corrupt trailing padding. */
function intakeCapacityPng(): string
{
    $image = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
    $padding = str_repeat("\0", 262144 - strlen($png) - 12);
    $chunk = 'npAd'.$padding;

    return substr($png, 0, -12).pack('N', strlen($padding)).$chunk.pack('N', crc32($chunk)).substr($png, -12);
}

test('actual MySQL intake proves 2000 files 500 MiB 5000 review rows and one explicit 50 image Apply', function () {
    [$root, $prefix] = intakeCapacityBoundary();
    expect(DB::transactionLevel())->toBe(0);
    $tables = ['users', 'groups', 'products', 'catalog_assets', 'media', 'catalog_asset_import_sources', 'catalog_asset_batches', 'catalog_asset_batch_items'];
    $countsBefore = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $actor = $group = null;
    $createdActor = false;
    $productIds = $sourceIds = $batchIds = [];
    $metrics = ['source_files' => 0, 'source_bytes' => 0, 'scan_steps' => 0, 'build_steps' => 0, 'apply_steps' => 0, 'max_upload_ms' => 0, 'max_progress_bytes' => 0];
    $started = hrtime(true);
    try {
        $actors = User::whereRaw('LOWER(email) = ?', ['ycm@data4.work'])->limit(2)->get();
        if ($actors->count() > 1 || ($actors->isNotEmpty() && $actors[0]->email !== 'ycm@data4.work')) {
            throw new RuntimeException('Conflicting manager identity.');
        }
        $createdActor = $actors->isEmpty();
        $actor = $actors->first() ?? User::factory()->create(['email' => 'ycm@data4.work', 'name' => $prefix.'-actor']);
        $this->actingAs($actor);
        $group = Group::factory()->create(['name' => $prefix.'-group', 'legacy_id' => $prefix.'-group']);
        $group->forceFill(['catalog_attributes' => ['application' => ['capacity fixture']]])->save();
        for ($offset = 0; $offset < 5000; $offset += 100) {
            $products = DB::transaction(fn () => Product::factory()->count(100)->for($group)->sequence(fn ($sequence) => [
                'legacy_id' => $prefix.'-'.($offset + $sequence->index), 'legacy_group_id' => $prefix.'-group',
                'product_code' => $prefix.'-'.sprintf('%04d', $offset + $sequence->index),
                'profile_fields' => ['product_pic' => 'image-'.sprintf('%04d', $offset + $sequence->index), 'preserve' => ['source' => $prefix]],
            ])->create());
            array_push($productIds, ...$products->modelKeys());
        }
        $metadataBefore = Product::whereKey($productIds)->orderBy('id')->get()->mapWithKeys(fn ($product) => [$product->id => Arr::except($product->getRawOriginal(), ['main_image_id', 'updated_at'])])->all();
        $groupBefore = $group->fresh()->getRawOriginal();
        $tagRowsBefore = DB::table('group_tag_value')->where('group_id', $group->id)->get()->all();
        $review = app(ReviewCatalogAssets::class);
        $launch = $review->freeze($actor, 'product.main_image', [], $productIds, [['column' => 'products.id', 'direction' => 'asc']]);
        $batchIds[] = $launch['review_id'];
        $metadata = [];
        for ($i = 0; $i < 2000; $i++) {
            $name = 'image-'.sprintf('%04d', $i).'.png';
            $metadata[] = ['name' => $name, 'relative_path' => $name, 'bytes' => 262144];
        }
        $intake = app(CatalogAssetIntake::class);
        $issued = $intake->issue($actor, $launch['token'], $prefix, $metadata);
        $sourceIds[] = $issued['source_id'];
        $progress = $intake->begin($actor, $launch['token'], $issued['intake_token']);
        $png = intakeCapacityPng();
        expect(strlen($png))->toBe(262144);
        expect(getimagesizefromstring($png))->toMatchArray([0 => 40, 1 => 20, 'mime' => 'image/png']);
        $sources = app(CatalogAssetSources::class);
        foreach ($metadata as $index => $entry) {
            if ($index === 1999) {
                $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
                $before = $source->getRawOriginal();
                expect(fn () => $sources->reserveUpload($actor, $source->id, $issued['entry_ids'][$index], [...$entry, 'bytes' => 262145]))->toThrow(ValidationException::class);
                expect($source->fresh()->getRawOriginal())->toBe($before);
            }
            $path = $root.'/input/'.$entry['name'];
            file_put_contents($path, $png, LOCK_EX);
            $file = new UploadedFile($path, $entry['name'], 'image/png', null, true);
            expect($file->getSize())->toBe(262144);
            $capability = $intake->entry($actor, $launch['token'], $issued['source_id'], $index);
            $stepStarted = hrtime(true);
            $progress = $intake->transfer($actor, $launch['token'], $capability['token'], $file);
            expect($progress['current_entry']['committed'])->toBeTrue();
            $metrics['max_upload_ms'] = max($metrics['max_upload_ms'], (hrtime(true) - $stepStarted) / 1000000);
            $metrics['max_progress_bytes'] = max($metrics['max_progress_bytes'], strlen(json_encode($progress, JSON_THROW_ON_ERROR)));
            unlink($path);
            if (($index + 1) % 100 === 0) {
                file_put_contents($root.'/capacity-progress.json', json_encode(['files' => $index + 1, 'bytes' => $progress['byte_count']], JSON_THROW_ON_ERROR), LOCK_EX);
            }
        }
        expect($progress['file_count'])->toBe(2000)->and($progress['byte_count'])->toBe(524288000);
        $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
        $before = $source->getRawOriginal();
        expect(fn () => $sources->reserveUpload($actor, $source->id, (string) Str::ulid(), ['name' => 'extra.png', 'relative_path' => 'extra.png', 'bytes' => 0]))->toThrow(ValidationException::class);
        expect($source->fresh()->getRawOriginal())->toBe($before);
        $progress = $intake->finish($actor, $launch['token'], $source->id, $progress['revision']);
        for ($i = 0; $i < 30 && $progress['stage'] !== 'ready'; $i++) {
            $progress = $progress['traversal_complete'] ? $sources->finalize($actor, $source->id, 1, $progress['revision']) : $sources->scanNext($actor, $source->id, 1, $progress['revision']);
            $metrics['scan_steps']++;
        }
        expect($progress['stage'])->toBe('ready');
        $manifest = $sources->manifest($actor, $source->id, 1);
        expect($manifest['entries'])->toHaveCount(2000);
        expect(array_unique(array_column($manifest['entries'], 'relative_path')))->toHaveCount(2000);
        expect(array_sum(array_column($manifest['entries'], 'bytes')))->toBe(524288000);
        foreach ($manifest['entries'] as $entry) {
            expect($entry['status'])->toBe('valid')->and($entry['sha256'])->toBe(hash('sha256', $png));
        }
        $batch = $review->handle($actor, 'product', $productIds, ['review_id' => $launch['review_id'], 'scope_token' => $launch['token'],
            'source_id' => $source->id, 'source_version' => 1, 'operation' => 'folder_matches', 'mode' => 'fill_missing', 'recipe' => []]);
        for ($i = 0; $i < 60 && $batch->fresh()->status === 'building'; $i++) {
            $review->buildNext($actor, $batch->id, 1, $batch->fresh()->build_revision);
            $metrics['build_steps']++;
        }
        expect($batch->fresh()->status)->toBe('review');
        expect($batch->items()->where('match_status', 'ready')->count())->toBe(2000);
        expect($batch->items()->where('match_status', 'missing')->count())->toBe(3000);
        expect(Product::whereKey($productIds)->whereNotNull('main_image_id')->count())->toBe(0);
        $journalCount = DB::table('catalog_asset_batches')->count();
        $filesBefore = Storage::disk('catalog-asset-imports')->allFiles();
        $absentId = (int) Product::query()->max('id') + 1;
        expect(in_array($absentId, $productIds, true))->toBeFalse();
        try {
            $review->freeze($actor, 'product.main_image', [], [...$productIds, $absentId], [['column' => 'products.id', 'direction' => 'asc']]);
            $this->fail('A 5001-distinct-ID selection must fail its maximum-size rule before scope construction.');
        } catch (ValidationException $exception) {
            expect($exception->validator->failed()['ids'] ?? [])->toHaveKey('Max');
        }
        expect(DB::table('catalog_asset_batches')->count())->toBe($journalCount);
        expect(Storage::disk('catalog-asset-imports')->allFiles())->toBe($filesBefore);
        $component = Livewire::test(AssetAssignment::class, ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'capacity-parent']);
        $queries = 0;
        $measuring = true;
        DB::listen(function () use (&$queries, &$measuring): void {
            if ($measuring) {
                $queries++;
            }
        });
        $component->call('setReviewPageSize', '5', $component->instance()->reviewContext())->assertHasNoErrors();
        $smallQueries = $queries;
        $queries = 0;
        $component->call('setReviewPageSize', '500', $component->instance()->reviewContext())->assertHasNoErrors();
        expect($component->instance()->getTableRecords())->toHaveCount(500);
        $measuring = false;
        expect($smallQueries)->toBeGreaterThan(0);
        expect($queries)->toBeLessThanOrEqual($smallQueries + 3);
        $metrics['table_5_queries'] = $smallQueries;
        $metrics['table_500_queries'] = $queries;
        $rows = $batch->items()->where('match_status', 'ready')->orderBy('position')->limit(50)->get();
        $apply = app(ApplyCatalogAssets::class);
        $intent = $apply->prepareIntent($actor, $batch->id, 1, $rows->modelKeys());
        $apply->handle($actor, $batch->id, $intent);
        foreach ($rows as $row) {
            expect(app(ProcessNextCatalogAssetBatchItem::class)->handle($actor, $batch->id, $intent, $row->fresh()->request_step_id)['outcome'])->toBe('applied');
            $metrics['apply_steps']++;
        }
        expect(Product::whereKey($productIds)->whereNotNull('main_image_id')->orderBy('id')->pluck('id')->all())->toBe($rows->pluck('record_id')->sort()->values()->all());
        expect($batch->items()->whereNotNull('acceptance_id')->count())->toBe(50);
        expect($batch->items()->whereNull('acceptance_id')->where('match_status', 'ready')->count())->toBe(1950);
        expect($batch->fresh()->active_acceptance_id)->toBeNull();
        $afterMetadata = Product::whereKey($productIds)->orderBy('id')->get()->mapWithKeys(fn ($product) => [$product->id => Arr::except($product->getRawOriginal(), ['main_image_id', 'updated_at'])])->all();
        expect($afterMetadata)->toBe($metadataBefore);
        expect(Arr::except($group->fresh()->getRawOriginal(), ['catalog_revision', 'updated_at']))->toBe(Arr::except($groupBefore, ['catalog_revision', 'updated_at']));
        expect(DB::table('group_tag_value')->where('group_id', $group->id)->get()->all())->toBe($tagRowsBefore);
        $metrics['source_files'] = $progress['file_count'];
        $metrics['source_bytes'] = $progress['byte_count'];
        $metrics['elapsed_ms'] = (hrtime(true) - $started) / 1000000;
        $metrics['peak_php_bytes'] = memory_get_peak_usage(true);
        $metrics['private_file_bytes'] = array_sum(array_map(fn ($file) => $file->getSize(), (new Filesystem)->allFiles($root, hidden: true)));
        expect($metrics['private_file_bytes'])->toBeLessThanOrEqual(2147483648);
        file_put_contents($root.'/capacity-result.json', json_encode($metrics, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX);
    } finally {
        $assets = CatalogAsset::where('created_by', $actor?->id)->whereIn('source->source_id', $sourceIds)->get();
        DB::table('catalog_asset_batch_items')->whereIn('batch_id', $batchIds)->delete();
        DB::table('catalog_asset_batches')->whereIn('id', $batchIds)->where('actor_id', $actor?->id)->delete();
        DB::table('products')->whereIn('id', $productIds)->update(['main_image_id' => null]);
        foreach ($assets as $asset) {
            DB::table('catalog_assets')->where('id', $asset->id)->update(['original_media_id' => null]);
            foreach ($asset->media()->get() as $media) {
                Storage::disk('catalog-assets')->deleteDirectory((string) $media->id);
                DB::table('media')->where('id', $media->id)->where('model_id', $asset->id)->where('model_type', $asset->getMorphClass())->delete();
            }
            DB::table('catalog_assets')->where('id', $asset->id)->where('created_by', $actor?->id)->delete();
        }
        foreach ($batchIds as $id) {
            Storage::disk('catalog-asset-imports')->deleteDirectory('reviews/'.$id);
        }
        DB::table('catalog_asset_import_sources')->whereIn('id', $sourceIds)->where('actor_id', $actor?->id)->delete();
        foreach ($sourceIds as $id) {
            Storage::disk('catalog-asset-imports')->deleteDirectory($id);
            Storage::disk('catalog-asset-imports')->deleteDirectory('intakes/'.$id);
        }
        DB::table('products')->whereIn('id', $productIds)->where('product_code', 'like', $prefix.'-%')->delete();
        if ($group !== null) {
            DB::table('groups')->where('id', $group->id)->where('name', $prefix.'-group')->delete();
        }
        if ($createdActor && $actor !== null) {
            DB::table('users')->where('id', $actor->id)->where('email', 'ycm@data4.work')->where('name', $prefix.'-actor')->delete();
        }
        foreach (['tmp-for-tests', 'input', 'media-tmp'] as $directory) {
            (new Filesystem)->deleteDirectory($root.'/'.$directory);
        }
    }
    foreach ($countsBefore as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
    expect(DB::transactionLevel())->toBe(0);
})->group('catalog-images-intake-capacity');
