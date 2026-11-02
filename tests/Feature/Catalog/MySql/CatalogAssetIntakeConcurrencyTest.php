<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\ApplyCatalogAssets;
use App\Actions\ProcessNextCatalogAssetBatchItem;
use App\Actions\ReviewCatalogAssets;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetImportSource;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetIntake;
use App\Services\CatalogAssetSources;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/** @return array{string, string} */
function intakeEngineBoundary(bool $empty): array
{
    assertCatalogMySqlSafety(app());
    $root = getenv('CATALOG_IMAGES_TEST_ROOT');
    $prefix = getenv('CATALOG_IMAGES_FIXTURE_PREFIX');
    $files = new Filesystem;
    if (getenv('CATALOG_INTAKE_CASE') !== 'concurrency' || ! is_string($prefix) || preg_match('/\AIMG-[a-zA-Z0-9-]{8,48}\z/', $prefix) !== 1
        || ! is_string($root) || ! str_starts_with($root, sys_get_temp_dir().'/catalog-images-') || ! is_dir($root)
        || is_link($root) || realpath($root) !== $root || ($empty && $files->allFiles($root, hidden: true) !== [])) {
        throw new RuntimeException('The admitted intake contender requires its exact canonical root, case and fixture prefix.');
    }
    config(['catalog-assets.enabled' => true, 'catalog-assets.testing_root' => $root, 'cache.default' => 'array',
        'media-library.temporary_directory' => $root.'/media-tmp']);
    foreach (['catalog-asset-imports', 'catalog-assets', 'tmp-for-tests'] as $disk) {
        $files->ensureDirectoryExists($root.'/'.$disk, 0700);
        $configuration = array_replace(config('filesystems.disks.'.$disk, []), ['driver' => 'local', 'root' => $root.'/'.$disk, 'throw' => true]);
        config(['filesystems.disks.'.$disk => $configuration]);
        Storage::set($disk, Storage::build($configuration));
    }

    return [$root, $prefix];
}

function intakeEngineWait(string $path): void
{
    $deadline = microtime(true) + 20;
    while (! is_file($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('The owned intake contender did not reach its required barrier.');
        }
        usleep(10000);
    }
}

test('owned intake contender uses only its admitted source or conflicting review', function () {
    if (getenv('CATALOG_INTAKE_CHILD') !== '1') {
        $this->markTestSkipped('Entered only by the admitted parent proof.');
    }
    [$root, $prefix] = intakeEngineBoundary(false);
    $mode = getenv('CATALOG_INTAKE_CHILD_MODE');
    if (! in_array($mode, ['same-entry', 'owner-cas'], true)) {
        throw new RuntimeException('Unsupported owned contender.');
    }
    $input = json_decode(file_get_contents($root.'/'.$mode.'.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($input['prefix'])->toBe($prefix);
    $actor = User::findOrFail($input['actor_id']);
    $this->actingAs($actor);
    $source = CatalogAssetImportSource::where('actor_id', $actor->id)->findOrFail($input['source_id']);
    expect($source->source['label'])->toBe($prefix);
    file_put_contents($root.'/'.$mode.'.ready', 'ready', LOCK_EX);
    intakeEngineWait($root.'/'.$mode.'.locked');
    if ($mode === 'same-entry') {
        $before = $source->fresh()->getRawOriginal();
        $filesBefore = Storage::disk('catalog-asset-imports')->allFiles();
        $result = app(CatalogAssetIntake::class)->transfer($actor, $input['launch_token'], $input['entry_token'], new UploadedFile($input['input_path'], 'image.png', 'image/png', null, true));
        expect($result['outcome'])->toBe('busy');
        expect($source->fresh()->getRawOriginal())->toBe($before);
        expect(Storage::disk('catalog-asset-imports')->allFiles())->toBe($filesBefore);
        file_put_contents($root.'/same-entry.busy', json_encode(['outcome' => 'busy', 'source_id' => $source->id], JSON_THROW_ON_ERROR), LOCK_EX);
        intakeEngineWait($root.'/same-entry.released');
        $result = app(CatalogAssetIntake::class)->transfer($actor, $input['launch_token'], $input['entry_token'], null);
        expect($result['current_entry']['committed'])->toBeTrue()->and($result['file_count'])->toBe(1);
    } else {
        $batch = app(ReviewCatalogAssets::class)->owned($actor, $input['batch_id']);
        $row = $batch->items()->findOrFail($input['item_id']);
        expect($batch->source_id)->toBe($source->id)->and($row->record_id)->toBe($input['record_id']);
        $owner = Product::findOrFail($row->record_id);
        expect(str_starts_with($owner->product_code, $prefix.'-'))->toBeTrue();
        $observed = null;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$observed, $root, $input): void {
            $exception = $event->context['exception'] ?? null;
            if (! $exception instanceof QueryException || (int) ($exception->errorInfo[1] ?? 0) !== 1205) {
                return;
            }
            $query = Group::query()->orderBy('id')->lockForUpdate();
            expect($exception->getSql())->toBe($query->toSql())->and($exception->getBindings())->toBe($query->getBindings());
            $observed = ['mysql_code' => 1205, 'record_id' => $input['record_id'], 'batch_id' => $input['batch_id'], 'lock_query_sha256' => hash('sha256', $exception->getSql()), 'transaction_level' => DB::transactionLevel()];
            file_put_contents($root.'/owner-cas.blocked', json_encode($observed, JSON_THROW_ON_ERROR), LOCK_EX);
        });
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $result = app(ProcessNextCatalogAssetBatchItem::class)->handle($actor, $batch->id, $row->acceptance_id, $row->request_step_id);
        expect($observed)->not->toBeNull();
        expect($observed['transaction_level'])->toBe(0)->and($result['outcome'])->toBe('paused');
        intakeEngineWait($root.'/owner-cas.released');
        $winningOwner = $owner->fresh()->getRawOriginal();
        $this->travel(61)->seconds();
        app(ApplyCatalogAssets::class)->continueAcceptance($actor, $batch->id, $row->acceptance_id);
        $result = app(ProcessNextCatalogAssetBatchItem::class)->handle($actor, $batch->id, $row->acceptance_id, $row->fresh()->request_step_id);
        expect($result['outcome'])->toBe('stale');
        expect($owner->fresh()->getRawOriginal())->toBe($winningOwner);
        expect($row->fresh()->error_code)->toBe('owner_changed');
    }
    file_put_contents($root.'/'.$mode.'.done', 'verified', LOCK_EX);
})->group('catalog-images-intake-concurrency');

test('actual MySQL intake locks one entry and separate reviews cannot overwrite the same owner', function () {
    if (getenv('CATALOG_INTAKE_CHILD') === '1') {
        $this->markTestSkipped('The contender cannot start a parent proof.');
    }
    [$root, $prefix] = intakeEngineBoundary(true);
    expect(DB::transactionLevel())->toBe(0);
    $tables = ['users', 'groups', 'products', 'catalog_assets', 'media', 'catalog_asset_import_sources', 'catalog_asset_batches', 'catalog_asset_batch_items'];
    $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $actor = $group = null;
    $createdActor = false;
    $productIds = $sourceIds = $batchIds = $reviewIds = $workers = [];
    $armed = false;
    DB::listen(function ($query) use (&$armed, $root): void {
        if (! $armed || ! preg_match('/^update [`"]?products[`"]? set /i', $query->sql)) {
            return;
        }
        $armed = false;
        expect(DB::transactionLevel())->toBeGreaterThan(0);
        file_put_contents($root.'/owner-cas.locked', 'locked', LOCK_EX);
        intakeEngineWait($root.'/owner-cas.blocked');
        $proof = json_decode(file_get_contents($root.'/owner-cas.blocked'), true, flags: JSON_THROW_ON_ERROR);
        expect($proof['mysql_code'])->toBe(1205)->and($proof['transaction_level'])->toBe(0);
        expect(is_file($root.'/owner-cas.released'))->toBeFalse();
    });
    try {
        $actors = User::whereRaw('LOWER(email) = ?', ['ycm@data4.work'])->limit(2)->get();
        if ($actors->count() > 1 || ($actors->isNotEmpty() && $actors[0]->email !== 'ycm@data4.work')) {
            throw new RuntimeException('Conflicting manager identity.');
        }
        $createdActor = $actors->isEmpty();
        $actor = $actors->first() ?? User::factory()->create(['email' => 'ycm@data4.work', 'name' => $prefix.'-actor']);
        $this->actingAs($actor);
        $group = Group::factory()->create(['name' => $prefix.'-group']);
        $products = DB::transaction(fn () => Product::factory()->count(2)->for($group)->sequence(fn ($sequence) => ['product_code' => $prefix.'-'.$sequence->index, 'legacy_id' => $prefix.'-'.$sequence->index])->create());
        $productIds = $products->modelKeys();
        $unrelated = $products[1]->fresh()->getRawOriginal();
        $review = app(ReviewCatalogAssets::class);
        $launch = $review->freeze($actor, 'product.main_image', [], [$products[0]->id], [['column' => 'products.id', 'direction' => 'asc']]);
        $reviewIds[] = $launch['review_id'];
        $intake = app(CatalogAssetIntake::class);
        $sources = app(CatalogAssetSources::class);
        $image = imagecreatetruecolor(40, 20);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);
        $otherPng = ob_get_clean();
        imagedestroy($image);
        $inputPath = $root.'/image.png';
        file_put_contents($inputPath, $png, LOCK_EX);
        $file = new UploadedFile($inputPath, 'image.png', 'image/png', null, true);
        $issued = $intake->issue($actor, $launch['token'], $prefix, [['name' => 'image.png', 'relative_path' => 'image.png', 'bytes' => $file->getSize()]]);
        $sourceIds[] = $issued['source_id'];
        $intake->begin($actor, $launch['token'], $issued['intake_token']);
        $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
        $start = function (string $mode, array $data) use ($root, $prefix, $actor, &$workers): Process {
            file_put_contents($root.'/'.$mode.'.json', json_encode(['prefix' => $prefix, 'actor_id' => $actor->id, ...$data], JSON_THROW_ON_ERROR), LOCK_EX);
            chmod($root.'/'.$mode.'.json', 0600);
            $worker = new Process([PHP_BINARY, '-d', 'sys_temp_dir='.sys_get_temp_dir(), '-d', 'zend.exception_ignore_args=1', 'vendor/bin/pest', '--configuration=phpunit.reconciliation-mysql.xml', __FILE__, '--filter=owned intake contender', '--compact', '--colors=never'], base_path(),
                ['CATALOG_INTAKE_CHILD' => '1', 'CATALOG_INTAKE_CHILD_MODE' => $mode], timeout: 50);
            $workers[] = $worker;
            $worker->start();
            intakeEngineWait($root.'/'.$mode.'.ready');

            return $worker;
        };
        $worker = $start('same-entry', ['source_id' => $issued['source_id'], 'launch_token' => $launch['token'], 'entry_token' => $entry['token'], 'input_path' => $inputPath]);
        $blockingFile = new class($inputPath, $root) extends UploadedFile
        {
            public function __construct(string $path, private string $barrierRoot)
            {
                parent::__construct($path, 'image.png', 'image/png', null, true);
            }

            public function storeAs(mixed $path, mixed $name = null, mixed $options = []): mixed
            {
                expect(DB::transactionLevel())->toBe(0);
                file_put_contents($this->barrierRoot.'/same-entry.locked', 'locked', LOCK_EX);
                intakeEngineWait($this->barrierRoot.'/same-entry.busy');

                return parent::storeAs($path, $name, $options);
            }
        };
        $progress = $intake->transfer($actor, $launch['token'], $entry['token'], $blockingFile);
        expect($progress['file_count'])->toBe(1)->and($progress['current_entry']['committed'])->toBeTrue();
        file_put_contents($root.'/same-entry.released', 'committed', LOCK_EX);
        $worker->wait();
        file_put_contents($root.'/same-entry.log', $worker->getOutput().$worker->getErrorOutput());
        expect($worker->isSuccessful())->toBeTrue()->and(is_file($root.'/same-entry.done'))->toBeTrue();
        $ready = function (string $sourceId, array $progress) use ($actor, $launch, $intake, $sources): string {
            $progress = $intake->finish($actor, $launch['token'], $sourceId, $progress['revision']);
            for ($i = 0; $i < 5 && $progress['stage'] !== 'ready'; $i++) {
                $progress = $progress['traversal_complete'] ? $sources->finalize($actor, $sourceId, 1, $progress['revision']) : $sources->scanNext($actor, $sourceId, 1, $progress['revision']);
            }
            expect($progress['stage'])->toBe('ready');

            return $sourceId;
        };
        $firstSource = $ready($issued['source_id'], $progress);
        file_put_contents($root.'/other.png', $otherPng, LOCK_EX);
        $other = new UploadedFile($root.'/other.png', 'image.png', 'image/png', null, true);
        $second = $intake->issue($actor, $launch['token'], $prefix, [['name' => 'image.png', 'relative_path' => 'image.png', 'bytes' => $other->getSize()]]);
        $sourceIds[] = $second['source_id'];
        $intake->begin($actor, $launch['token'], $second['intake_token']);
        $cap = $intake->entry($actor, $launch['token'], $second['source_id'], 0);
        $secondSource = $ready($second['source_id'], $intake->transfer($actor, $launch['token'], $cap['token'], $other));
        $makeReview = function (string $sourceId, ?array $scope = null) use ($actor, $review, $sources, $products, &$batchIds, &$reviewIds): CatalogAssetBatch {
            $scope ??= $review->freeze($actor, 'product.main_image', [], [$products[0]->id], [['column' => 'products.id', 'direction' => 'asc']]);
            if (! in_array($scope['review_id'], $reviewIds, true)) {
                $reviewIds[] = $scope['review_id'];
            }
            $batchIds[] = $scope['review_id'];
            $candidate = $sources->manifest($actor, $sourceId, 1)['entries'][0];
            $batch = $review->handle($actor, 'product', [$products[0]->id], ['review_id' => $scope['review_id'], 'scope_token' => $scope['token'], 'source_id' => $sourceId, 'source_version' => 1, 'operation' => 'one_image', 'mode' => 'fill_missing', 'recipe' => [],
                'entry_choice' => ['source_id' => $sourceId, 'scan_version' => 1, 'entry_id' => $candidate['entry_id'], 'sha256' => $candidate['sha256']]]);
            $review->buildNext($actor, $batch->id, 1, 0);
            expect($batch->fresh()->status)->toBe('review');
            $apply = app(ApplyCatalogAssets::class);
            $intent = $apply->prepareIntent($actor, $batch->id, 1, $batch->items()->pluck('id')->all());
            $apply->handle($actor, $batch->id, $intent);

            return $batch->fresh();
        };
        $winner = $makeReview($firstSource, $launch);
        $loser = $makeReview($secondSource);
        $row = $loser->items()->firstOrFail();
        $worker = $start('owner-cas', ['source_id' => $secondSource, 'batch_id' => $loser->id, 'item_id' => $row->id, 'record_id' => $row->record_id]);
        $winningRow = $winner->items()->firstOrFail();
        $armed = true;
        $result = app(ProcessNextCatalogAssetBatchItem::class)->handle($actor, $winner->id, $winner->active_acceptance_id, $winningRow->request_step_id);
        expect($result['outcome'])->toBe('applied')->and($armed)->toBeFalse()->and(DB::transactionLevel())->toBe(0);
        $winningOwner = $products[0]->fresh()->getRawOriginal();
        file_put_contents($root.'/owner-cas.released', 'committed', LOCK_EX);
        $worker->wait();
        file_put_contents($root.'/owner-cas.log', $worker->getOutput().$worker->getErrorOutput());
        expect($worker->isSuccessful())->toBeTrue()->and(is_file($root.'/owner-cas.done'))->toBeTrue();
        expect($products[0]->fresh()->getRawOriginal())->toBe($winningOwner);
        expect($products[1]->fresh()->getRawOriginal())->toBe($unrelated);
        expect($loser->items()->firstOrFail()->apply_status)->toBe('stale');
    } finally {
        $armed = false;
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop(1);
            }
        }
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
        foreach ($reviewIds as $id) {
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
        foreach (['tmp-for-tests', 'media-tmp'] as $directory) {
            (new Filesystem)->deleteDirectory($root.'/'.$directory);
        }
        foreach ([$root.'/image.png', $root.'/other.png'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    foreach ($before as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
    expect(DB::transactionLevel())->toBe(0);
})->group('catalog-images-intake-concurrency');
