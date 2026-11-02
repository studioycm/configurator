<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\ApplyCatalogAssets;
use App\Actions\ProcessNextCatalogAssetBatchItem;
use App\Actions\ReviewCatalogAssets;
use App\Actions\SaveCatalogMainImage;
use App\DTO\CatalogAssetChange;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetBatch;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetSources;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

function applyEngineBoundary(bool $empty): array
{
    assertCatalogMySqlSafety(app());
    $root = getenv('CATALOG_IMAGES_TEST_ROOT');
    $prefix = getenv('CATALOG_IMAGES_FIXTURE_PREFIX');
    if (! is_string($prefix) || preg_match('/\AIMG-[a-zA-Z0-9-]{8,48}\z/', $prefix) !== 1 || ! is_string($root) || ! str_starts_with($root, sys_get_temp_dir().'/catalog-images-') || ! is_dir($root) || is_link($root) || realpath($root) !== $root || ($empty && (new Filesystem)->allFiles($root, hidden: true) !== [])) {
        throw new RuntimeException('The exact admitted Apply profile, prefix and canonical private root are required.');
    }
    config(['catalog-assets.enabled' => true, 'catalog-assets.testing_root' => $root, 'cache.default' => 'array', 'media-library.temporary_directory' => $root.'/media-tmp']);
    foreach (['catalog-asset-imports', 'catalog-assets', 'apply-engine-source'] as $disk) {
        $config = array_replace(config('filesystems.disks.'.$disk, []), ['driver' => 'local', 'root' => $root.'/'.$disk, 'throw' => true]);
        config(['filesystems.disks.'.$disk => $config]);
        Storage::set($disk, Storage::build($config));
    }
    config(['catalog-assets.sources.apply_engine' => ['label' => $prefix, 'path' => $root.'/apply-engine-source', 'read_only' => true]]);

    return [$root, $prefix];
}

function applyEngineWait(string $path): void
{
    $until = microtime(true) + 15;
    while (! is_file($path)) {
        if (microtime(true) > $until) {
            throw new RuntimeException('The owned contender did not reach its explicit barrier.');
        }
        usleep(10000);
    }
}

test('owned Apply contender obeys its exact admitted fixture and barrier', function () {
    if (getenv('CATALOG_APPLY_WORKER') !== '1') {
        $this->markTestSkipped('Entered only by the admitted parent proof.');
    }
    [$root, $prefix] = applyEngineBoundary(false);
    $mode = getenv('CATALOG_APPLY_WORKER_MODE');
    if (! in_array($mode, ['same-step', 'group-cas'], true)) {
        throw new RuntimeException('Unsupported owned contender.');
    }
    $input = json_decode(file_get_contents($root.'/'.$mode.'.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($input['prefix'])->toBe($prefix);
    $actor = User::findOrFail($input['actor_id']);
    $batch = app(ReviewCatalogAssets::class)->owned($actor, $input['batch_id']);
    expect($batch->source->source['label'])->toBe($prefix);
    $row = $batch->items()->findOrFail($input['item_id']);
    expect($row->record_id)->toBe($input['record_id'])->and($row->request_step_id)->toBe($input['step']);
    file_put_contents($root.'/'.$mode.'.ready', 'ready', LOCK_EX);
    applyEngineWait($root.'/'.$mode.'.locked');
    file_put_contents($root.'/'.$mode.'.calling', 'calling', LOCK_EX);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
    $operation = fn () => $mode === 'same-step'
        ? app(ProcessNextCatalogAssetBatchItem::class)->handle($actor, $batch->id, $row->acceptance_id, $row->request_step_id)
        : app(SaveCatalogMainImage::class)->handle($actor, 'group.main_image', $row->record_id, [], $input['fingerprint'], new CatalogAssetChange('remove'));
    $expectedQuery = $mode === 'same-step'
        ? CatalogAssetBatch::query()->where('actor_id', $actor->id)->whereKey($batch->id)->limit(1)->lockForUpdate()
        : Group::query()->orderBy('id')->lockForUpdate();
    $blocked = false;
    try {
        $operation();
    } catch (QueryException $exception) {
        expect((int) ($exception->errorInfo[1] ?? 0))->toBe(1205)
            ->and($exception->getSql())->toBe($expectedQuery->toSql())
            ->and($exception->getBindings())->toBe($expectedQuery->getBindings())
            ->and(DB::transactionLevel())->toBe(0);
        $blocked = true;
        file_put_contents($root.'/'.$mode.'.blocked', json_encode(['mode' => $mode, 'batch_id' => $batch->id, 'record_id' => $row->record_id,
            'mysql_code' => 1205, 'lock_query_sha256' => hash('sha256', $exception->getSql()), 'transaction_level' => DB::transactionLevel()], JSON_THROW_ON_ERROR), LOCK_EX);
    }
    expect($blocked)->toBeTrue();
    applyEngineWait($root.'/'.$mode.'.released');
    if ($mode === 'same-step') {
        $result = $operation();
        expect($result['outcome'])->toBe('applied')->and($result['replay'])->toBeTrue();
    } else {
        expect($operation)->toThrow(ValidationException::class);
    }
    file_put_contents($root.'/'.$mode.'.done', 'verified', LOCK_EX);
})->group('catalog-images-apply-engine');

test('actual MySQL Apply commits once with causal Group revisions and conflicting owner locks', function () {
    if (getenv('CATALOG_APPLY_WORKER') === '1') {
        $this->markTestSkipped('The contender cannot start a parent proof.');
    }
    [$root, $prefix] = applyEngineBoundary(true);
    expect(DB::transactionLevel())->toBe(0);
    $tables = ['users', 'groups', 'products', 'catalog_assets', 'media', 'catalog_asset_import_sources', 'catalog_asset_batches', 'catalog_asset_batch_items'];
    $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $actor = null;
    $createdActor = false;
    $groupIds = $productIds = $sourceIds = $batchIds = [];
    $workers = [];
    $armed = null;
    $barrierRan = [];
    DB::listen(function ($query) use (&$armed, &$barrierRan, $root): void {
        if ($armed === null || ! preg_match('/^update [`"]?(products|groups)[`"]? set /i', $query->sql)) {
            return;
        }
        $mode = $armed;
        $armed = null;
        expect(DB::transactionLevel())->toBeGreaterThan(0);
        file_put_contents($root.'/'.$mode.'.locked', 'locked', LOCK_EX);
        applyEngineWait($root.'/'.$mode.'.blocked');
        $blocked = json_decode(file_get_contents($root.'/'.$mode.'.blocked'), true, flags: JSON_THROW_ON_ERROR);
        $owned = json_decode(file_get_contents($root.'/'.$mode.'.json'), true, flags: JSON_THROW_ON_ERROR);
        expect($blocked['mode'])->toBe($mode)->and($blocked['mysql_code'])->toBe(1205)
            ->and($blocked['batch_id'])->toBe($owned['batch_id'])->and($blocked['record_id'])->toBe($owned['record_id'])
            ->and($blocked['transaction_level'])->toBe(0)->and(is_file($root.'/'.$mode.'.released'))->toBeFalse()
            ->and(is_file($root.'/'.$mode.'.done'))->toBeFalse();
        $barrierRan[$mode] = true;
    });
    try {
        $actors = User::whereRaw('LOWER(email) = ?', ['ycm@data4.work'])->limit(2)->get();
        if ($actors->count() > 1 || ($actors->isNotEmpty() && $actors[0]->email !== 'ycm@data4.work')) {
            throw new RuntimeException('Conflicting manager identity.');
        }
        $createdActor = $actors->isEmpty();
        $actor = $actors->first() ?? User::factory()->create(['email' => 'ycm@data4.work', 'name' => $prefix.'-actor']);
        $this->actingAs($actor);
        $parent = Group::factory()->create(['name' => $prefix.'-parent']);
        $groupIds[] = $parent->id;
        foreach (['a', 'b'] as $suffix) {
            $g = Group::factory()->create(['name' => $prefix.'-'.$suffix, 'parent_id' => $parent->id]);
            $groupIds[] = $g->id;
        }
        $products = Product::factory()->count(2)->for($parent)->sequence(fn ($s) => ['product_code' => $prefix.'-'.$s->index, 'legacy_id' => $prefix.'-'.$s->index, 'profile_fields' => ['product_pic' => 'main']])->create();
        $productIds = $products->modelKeys();
        $image = imagecreatetruecolor(40, 20);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        Storage::disk('apply-engine-source')->put('main.png', $bytes);
        $review = app(ReviewCatalogAssets::class);
        $sourceService = app(CatalogAssetSources::class);
        $make = function (string $target, array $ids) use ($actor, $review, $sourceService, &$sourceIds, &$batchIds): CatalogAssetBatch {
            $source = $sourceService->startScan($actor, 'apply_engine', $target, [], false);
            $sourceIds[] = $source->id;
            for ($n = 0; $n < 12; $n++) {
                $p = $sourceService->status($actor, $source->id);
                if ($p['stage'] === 'ready') {
                    break;
                }
                $p['traversal_complete'] ? $sourceService->finalize($actor, $source->id, 1, $p['revision']) : $sourceService->scanNext($actor, $source->id, 1, $p['revision']);
            }
            $entry = $sourceService->manifest($actor, $source->id, 1)['entries'][0];
            $entity = $target === 'product.main_image' ? 'product' : 'group';
            $table = $entity === 'product' ? 'products' : 'groups';
            $launch = $review->freeze($actor, $target, [], $ids, [['column' => $table.'.id', 'direction' => 'asc']]);
            $batchIds[] = $launch['review_id'];
            $batch = $review->handle($actor, $entity, $ids, ['review_id' => $launch['review_id'], 'scope_token' => $launch['token'], 'source_id' => $source->id, 'source_version' => 1, 'operation' => $entity === 'product' ? 'folder_matches' : 'one_image', 'mode' => 'fill_missing', 'recipe' => [], 'entry_choice' => ['source_id' => $source->id, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']]]);
            $review->buildNext($actor, $batch->id, 1, 0);
            expect($batch->fresh()->status)->toBe('review');
            $apply = app(ApplyCatalogAssets::class);
            $intent = $apply->prepareIntent($actor, $batch->id, 1, $batch->items()->orderBy('position')->pluck('id')->all());
            $apply->handle($actor, $batch->id, $intent);
            $apply->handle($actor, $batch->id, $intent);

            return $batch->fresh();
        };
        $contender = function (string $mode, CatalogAssetBatch $batch, $row) use ($root, $prefix, $actor, &$workers): Process {
            $owner = ($batch->target_type === 'product' ? Product::class : Group::class)::findOrFail($row->record_id);
            file_put_contents($root.'/'.$mode.'.json', json_encode(['prefix' => $prefix, 'actor_id' => $actor->id, 'batch_id' => $batch->id, 'item_id' => $row->id, 'record_id' => $row->record_id, 'step' => $row->request_step_id, 'fingerprint' => CatalogAssetAssignments::fingerprint($owner)], JSON_THROW_ON_ERROR), LOCK_EX);
            $worker = new Process([PHP_BINARY, '-d', 'sys_temp_dir='.sys_get_temp_dir(), 'vendor/bin/pest', '--configuration=phpunit.reconciliation-mysql.xml', __FILE__, '--filter=owned Apply contender', '--compact', '--colors=never'], base_path(), ['CATALOG_APPLY_WORKER' => '1', 'CATALOG_APPLY_WORKER_MODE' => $mode], timeout: 25);
            $workers[] = $worker;
            $worker->start();
            applyEngineWait($root.'/'.$mode.'.ready');

            return $worker;
        };
        $batch = $make('product.main_image', $productIds);
        $rows = $batch->items()->orderBy('position')->get();
        $worker = $contender('same-step', $batch, $rows[0]);
        $armed = 'same-step';
        $process = app(ProcessNextCatalogAssetBatchItem::class);
        expect($process->handle($actor, $batch->id, $batch->active_acceptance_id, $rows[0]->request_step_id)['outcome'])->toBe('applied');
        expect(DB::transactionLevel())->toBe(0);
        file_put_contents($root.'/same-step.released', 'committed', LOCK_EX);
        $worker->wait();
        file_put_contents($root.'/same-step.log', $worker->getOutput().$worker->getErrorOutput());
        expect($worker->isSuccessful())->toBeTrue()->and(is_file($root.'/same-step.done'))->toBeTrue()->and($rows[0]->fresh()->attempts)->toBe(1)->and($barrierRan['same-step'] ?? false)->toBeTrue();
        $process->handle($actor, $batch->id, $batch->active_acceptance_id, $rows[1]->request_step_id);
        expect($products[0]->fresh()->main_image_id)->toBe($products[1]->fresh()->main_image_id);
        $p = $products[0]->fresh();
        $beforeOwner = $p->getAttributes();
        $beforeGroup = $parent->fresh()->getAttributes();
        expect(fn () => app(SaveCatalogMainImage::class)->handle($actor, 'product.main_image', $p->id, [], CatalogAssetAssignments::fingerprint($p), new CatalogAssetChange('remove'), null, function () {
            throw new RuntimeException('Owned finalization rollback');
        }))->toThrow(RuntimeException::class);
        expect($p->fresh()->getAttributes())->toBe($beforeOwner)->and($parent->fresh()->getAttributes())->toBe($beforeGroup);
        $groups = $make('group.main_image', array_slice($groupIds, 1));
        $groupRows = $groups->items()->orderBy('position')->get();
        $worker = $contender('group-cas', $groups, $groupRows[1]);
        $armed = 'group-cas';
        expect($process->handle($actor, $groups->id, $groups->active_acceptance_id, $groupRows[0]->request_step_id)['outcome'])->toBe('applied');
        expect(DB::transactionLevel())->toBe(0);
        file_put_contents($root.'/group-cas.released', 'committed', LOCK_EX);
        $worker->wait();
        file_put_contents($root.'/group-cas.log', $worker->getOutput().$worker->getErrorOutput());
        expect($worker->isSuccessful())->toBeTrue()->and(is_file($root.'/group-cas.done'))->toBeTrue()->and($barrierRan['group-cas'] ?? false)->toBeTrue()->and($groupRows[1]->fresh()->snapshot['execution_causes'])->toHaveCount(1);
        expect($process->handle($actor, $groups->id, $groups->active_acceptance_id, $groupRows[1]->request_step_id)['outcome'])->toBe('applied')->and($groups->fresh()->status)->toBe('completed');
    } finally {
        $armed = null;
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop(1);
            }
        }
        $assets = CatalogAsset::where('created_by', $actor?->id)->whereIn('source->source_id', $sourceIds)->get();
        DB::table('catalog_asset_batch_items')->whereIn('batch_id', $batchIds)->delete();
        DB::table('catalog_asset_batches')->whereIn('id', $batchIds)->where('actor_id', $actor?->id)->delete();
        DB::table('products')->whereIn('id', $productIds)->update(['main_image_id' => null]);
        DB::table('groups')->whereIn('id', $groupIds)->update(['main_image_id' => null]);
        foreach ($assets as $asset) {
            $media = $asset->media()->get();
            DB::table('catalog_assets')->where('id', $asset->id)->update(['original_media_id' => null]);
            foreach ($media as $m) {
                Storage::disk('catalog-assets')->deleteDirectory((string) $m->id);
                DB::table('media')->where('id', $m->id)->where('model_id', $asset->id)->where('model_type', $asset->getMorphClass())->delete();
            }
            DB::table('catalog_assets')->where('id', $asset->id)->where('created_by', $actor?->id)->delete();
        }
        foreach ($batchIds as $id) {
            Storage::disk('catalog-asset-imports')->deleteDirectory('reviews/'.$id);
        }
        DB::table('catalog_asset_import_sources')->whereIn('id', $sourceIds)->where('actor_id', $actor?->id)->delete();
        foreach ($sourceIds as $id) {
            Storage::disk('catalog-asset-imports')->deleteDirectory(strtolower($id));
        }
        DB::table('products')->whereIn('id', $productIds)->where('product_code', 'like', $prefix.'-%')->delete();
        DB::table('groups')->whereIn('id', $groupIds)->update(['parent_id' => null]);
        DB::table('groups')->whereIn('id', $groupIds)->where('name', 'like', $prefix.'-%')->delete();
        if ($createdActor && $actor !== null) {
            DB::table('users')->where('id', $actor->id)->where('email', 'ycm@data4.work')->where('name', $prefix.'-actor')->delete();
        }
    }
    foreach ($before as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
    expect(DB::transactionLevel())->toBe(0);
})->group('catalog-images-apply-engine');
