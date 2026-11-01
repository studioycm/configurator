<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\SaveCatalogMainImage;
use App\DTO\CatalogAssetChange;
use App\Models\CatalogAsset;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetStorage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

test('independent image owner writers serialize and reject the stale contender without an extra revision', function () {
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
    $group = null;
    $product = null;
    try {
        Gate::forUser($actor)->authorize('manage-catalog');
        $disk = catalogImageTestDisk('engine-owner-source');
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
        expect(Group::where('name', $prefix.'-group')->exists())->toBeFalse();
        $group = Group::factory()->create(['name' => $prefix.'-group']);
        $product = Product::factory()->for($group)->create(['product_code' => $prefix.'-product'])->refresh();
        $prepared = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $importKey);
        expect($prepared['outcome'])->toBe('ready');
        $assetId = $prepared['asset']->id;
        $fingerprint = CatalogAssetAssignments::fingerprint($product);
        $revision = (int) $group->fresh()->catalog_revision;
        $before = $product->getAttributes();
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $product, $fingerprint, $assetId): array {
            $blocked = false;
            DB::listen(function (QueryExecuted $query) use ($channel, &$blocked): void {
                if (! $blocked && str_contains($query->sql, 'from `products`') && str_contains($query->sql, 'for update')) {
                    $blocked = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'owner_locked']);
                    expect(readCatalogContentionMessage($channel))->toBe(['phase' => 'release']);
                }
            });

            return catalogContentionResult(fn () => app(SaveCatalogMainImage::class)->handle(User::findOrFail($actor->id), 'product.main_image', $product->id, [], $fingerprint, new CatalogAssetChange('set', $assetId))->id);
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'owner_locked']);
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $product, $fingerprint): array {
            $blocked = false;
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$blocked): void {
                if (! $blocked && str_contains($sql, 'from `groups`') && str_contains($sql, 'for update')) {
                    $blocked = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'attempting_owner_lock']);
                }
            });

            return catalogContentionResult(fn () => app(SaveCatalogMainImage::class)->handle(User::findOrFail($actor->id), 'product.main_image', $product->id, [], $fingerprint, new CatalogAssetChange('remove'))->id);
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting_owner_lock']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            DB::transaction(fn () => Product::whereKey($product->id)->lockForUpdate()->firstOrFail());
            throw new RuntimeException('The independent image writer did not retain its owner lock.');
        } catch (QueryException $exception) {
            expect((int) $exception->errorInfo[1])->toBe(1205);
        }
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        foreach ($workers as $worker) {
            expect(pcntl_waitpid($worker['pid'], $status))->toBe($worker['pid'])
                ->and(pcntl_wifexited($status))->toBeTrue()->and(pcntl_wexitstatus($status))->toBe(0);
        }
        expect($first['status'])->toBe('committed')->and($second['status'])->toBe('rejected')->and($second['errors'])->toHaveKey('record');
        expect($product->fresh()->main_image_id)->toBe($assetId)->and((int) $group->fresh()->catalog_revision)->toBe($revision + 1)
            ->and(array_diff_key($product->fresh()->getAttributes(), array_flip(['main_image_id', 'updated_at'])))
            ->toBe(array_diff_key($before, array_flip(['main_image_id', 'updated_at'])));
    } finally {
        stopCatalogContentionWorkers($workers);
        $workers = [];
        if ($product !== null) {
            DB::table('products')->where('id', $product->id)->where('product_code', $prefix.'-product')->delete();
        }
        if ($group !== null) {
            DB::table('groups')->where('id', $group->id)->where('name', $prefix.'-group')->delete();
        }
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
