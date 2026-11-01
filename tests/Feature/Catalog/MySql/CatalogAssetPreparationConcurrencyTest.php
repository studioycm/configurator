<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Models\CatalogAsset;
use App\Models\User;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

test('independent preparation retries fence the expired claimant and publish exactly one immutable ready winner', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $prefix = getenv('CATALOG_IMAGES_FIXTURE_PREFIX');
    $root = getenv('CATALOG_IMAGES_TEST_ROOT');
    if (! is_string($prefix) || preg_match('/\AIMG-[a-zA-Z0-9-]{8,48}\z/', $prefix) !== 1
        || ! is_string($root) || ! str_starts_with($root, sys_get_temp_dir().'/catalog-images-')
        || ! is_dir($root) || is_link($root) || realpath($root) !== $root) {
        throw new RuntimeException('Nonresetting Images proof requires an approved unique fixture prefix and isolated canonical scratch root.');
    }
    config(['catalog-assets.enabled' => true, 'catalog-assets.testing_root' => $root,
        'media-library.temporary_directory' => $root.'/media-tmp']);
    foreach (['local', 'public', 'catalog-assets', 'catalog-asset-imports'] as $disk) {
        catalogImageTestDisk($disk);
    }
    $email = 'ycm@data4.work';
    $actors = User::query()->whereRaw('LOWER(email) = ?', [$email])->limit(2)->get();
    if ($actors->count() > 1 || ($actors->isNotEmpty() && $actors->first()->email !== $email)) {
        throw new RuntimeException('Conflicting manager identity; no existing User may be changed.');
    }
    $createdActor = $actors->isEmpty();
    $actor = $actors->first() ?? User::factory()->create(['name' => $prefix.'-manager', 'email' => $email]);
    $actorBefore = $actor->fresh()->getAttributes();
    $source = null;
    $workers = [];
    try {
        Gate::forUser($actor)->authorize('manage-catalog');
        $disk = catalogImageTestDisk('engine-preparation-source');
        $image = imagecreatetruecolor(40, 20);
        ob_start();
        imagepng($image);
        $disk->put($prefix.'.png', ob_get_clean());
        imagedestroy($image);
        config(['catalog-assets.sources.fixture' => ['label' => $prefix, 'path' => $disk->path(''), 'read_only' => true]]);
        $sources = app(CatalogAssetSources::class);
        $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
        for ($step = 0; $step < 10 && ! $sources->status($actor, $source->id)['traversal_complete']; $step++) {
            $sources->scanNext($actor, $source->id, 1, $sources->status($actor, $source->id)['revision']);
        }
        $sources->finalize($actor, $source->id, 1, $sources->status($actor, $source->id)['revision']);
        $entries = $sources->manifest($actor, $source->id, 1)['entries'];
        expect($entries)->toHaveCount(1);
        $entry = $entries[0];
        $choice = ['source_id' => $source->id, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']];
        $importKey = hash('sha256', $prefix);
        expect(CatalogAsset::where('import_key', $importKey)->exists())->toBeFalse();
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $choice, $importKey): array {
            Event::listen(MediaHasBeenAddedEvent::class, function (MediaHasBeenAddedEvent $event) use ($channel): void {
                expect(DB::transactionLevel())->toBe(0);
                writeCatalogContentionMessage($channel, ['phase' => 'attempt_ready', 'asset_id' => (int) $event->media->model_id]);
                expect(readCatalogContentionMessage($channel))->toBe(['phase' => 'release']);
            });
            $result = app(CatalogAssetStorage::class)->prepare(User::findOrFail($actor->id), $choice, 'product.main_image', $importKey);

            return ['outcome' => $result['outcome'], 'asset_id' => $result['asset']?->id];
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        $barrier = readCatalogContentionMessage($workers[0]['channel']);
        expect($barrier['phase'])->toBe('attempt_ready');
        $assetId = $barrier['asset_id'];
        $busy = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $importKey);
        expect($busy['outcome'])->toBe('busy');
        // Expire only this run-owned lease to deterministically exercise a genuine retry.
        CatalogAsset::whereKey($assetId)->where('import_key', $importKey)->update(['preparation_expires_at' => now()->subSecond()]);
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $choice, $importKey): array {
            $result = app(CatalogAssetStorage::class)->prepare(User::findOrFail($actor->id), $choice, 'product.main_image', $importKey);

            return ['outcome' => $result['outcome'], 'asset_id' => $result['asset']?->id];
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        $winner = readCatalogContentionMessage($workers[1]['channel']);
        expect($winner['outcome'])->toBe('ready')->and($winner['asset_id'])->toBe($assetId);
        $before = CatalogAsset::findOrFail($assetId)->getAttributes();
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $stale = readCatalogContentionMessage($workers[0]['channel']);
        expect($stale['outcome'])->toBe('superseded');
        foreach ($workers as $worker) {
            expect(pcntl_waitpid($worker['pid'], $status))->toBe($worker['pid'])
                ->and(pcntl_wifexited($status))->toBeTrue()->and(pcntl_wexitstatus($status))->toBe(0);
        }
        expect(CatalogAsset::findOrFail($assetId)->getAttributes())->toBe($before);
        $asset = CatalogAsset::findOrFail($assetId);
        expect($asset->media()->where('collection_name', 'original')->count())->toBe(1)
            ->and($asset->media()->where('collection_name', 'preparation-attempts')->count())->toBe(1);
        $again = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $importKey);
        expect($again['outcome'])->toBe('ready')->and($again['asset']->id)->toBe($assetId)
            ->and(CatalogAsset::where('import_key', $importKey)->count())->toBe(1);
    } finally {
        stopCatalogContentionWorkers($workers);
        if ($source !== null) {
            $assets = CatalogAsset::query()->where('created_by', $actor->id)->where('source->source_id', $source->id)->pluck('id');
            DB::table('catalog_assets')->whereIn('id', $assets)->update(['original_media_id' => null]);
            DB::table('media')->where('model_type', (new CatalogAsset)->getMorphClass())->whereIn('model_id', $assets)->delete();
            DB::table('catalog_assets')->whereIn('id', $assets)->where('created_by', $actor->id)->delete();
            DB::table('catalog_asset_import_sources')->where('id', $source->id)->where('actor_id', $actor->id)->delete();
        }
        if ($createdActor) {
            DB::table('users')->where('id', $actor->id)->where('email', $email)->where('name', $prefix.'-manager')->delete();
        } else {
            expect($actor->fresh()->getAttributes())->toBe($actorBefore);
        }
    }
});
