<?php

use App\Actions\SaveCatalogGroup;
use App\Actions\SaveCatalogMainImage;
use App\Actions\SaveCatalogProduct;
use App\DTO\CatalogAssetChange;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetImportSource;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetSources;
use App\Services\CatalogMainImageEdits;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: Product, 2: array<string, mixed>, 3: array<string, mixed>} */
function imageEditSource(): array
{
    catalogImageTestDisk('catalog-asset-imports');
    catalogImageTestDisk('catalog-assets');
    catalogImageTestDisk('image-edit-fixtures');
    config(['catalog-assets.enabled' => true, 'catalog-assets.sources' => ['fixture' => ['label' => 'Synthetic image', 'path' => Storage::disk('image-edit-fixtures')->path(''), 'read_only' => true]]]);
    $image = imagecreatetruecolor(80, 40);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    Storage::disk('image-edit-fixtures')->put('synthetic.png', $bytes);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $product = Product::factory()->create()->refresh();
    $context = imageEditContext($product);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    do {
        $status = $sources->status($actor, $source->id);
        if (! $status['traversal_complete']) {
            $sources->scanNext($actor, $source->id, 1, $status['revision']);
        }
    } while (! $sources->status($actor, $source->id)['traversal_complete']);
    $sources->finalize($actor, $source->id, 1, $sources->status($actor, $source->id)['revision']);
    $entry = $sources->manifest($actor, $source->id, 1)['entries'][0];

    return [$actor, $product, $context, ['source_id' => $source->id, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']]];
}

/** @return array<string, mixed> */
function imageEditContext(Product $product): array
{
    return ['target_key' => 'product.main_image', 'record_id' => $product->id, 'creation_session' => null, 'owner_context' => [], 'expected_fingerprint' => SaveCatalogProduct::fingerprint($product), 'expected_group_fingerprint' => null];
}

test('draft hashes sort associative keys preserve types and lists and exclude only upload transport', function () {
    $service = app(CatalogMainImageEdits::class);
    $data = ['name' => ' x ', 'catalog_settings' => ['b' => 2, 'a' => 1], 'image_intent' => ['operation' => 'keep', 'draft_intent_id' => 'same', 'source_token' => null]];
    expect($service->draftHash($data))->toBe($service->draftHash(['image_upload' => new stdClass, ...array_reverse($data, true)]))
        ->and($service->draftHash(['v' => [1, 2]]))->not->toBe($service->draftHash(['v' => [2, 1]]))
        ->and($service->draftHash(['v' => 1]))->not->toBe($service->draftHash(['v' => 1.0]))
        ->and($service->draftHash(['v' => 1]))->not->toBe($service->draftHash(['v' => '1']))
        ->and($service->draftHash($data))->not->toBe($service->draftHash([...$data, 'name' => 'x']));
});

test('set preparation returns an opaque bound receipt and final resolution never probes source files', function () {
    [$actor, $product, $context, $choice] = imageEditSource();
    expect(DB::transactionLevel())->toBe(0);
    $edits = app(CatalogMainImageEdits::class);
    $intent = $edits->stageSource($actor, $context, $choice);
    expect(array_keys($intent))->toBe(['operation', 'source_token', 'draft_intent_id'])->and($intent['operation'])->toBe('set');
    $hash = $edits->draftHash(['image_intent' => $intent, 'name' => 'full draft']);
    $prepared = $edits->prepare($actor, $context, $intent, $hash);
    expect($prepared['outcome'])->toBe('ready')->and($prepared['receipt'])->toBeString()->and($prepared)->not->toHaveKey('asset');
    $again = $edits->prepare($actor, $context, $intent, $hash);
    expect($again['outcome'])->toBe('ready')->and(CatalogAsset::query()->count())->toBe(1);
    Storage::disk('image-edit-fixtures')->delete('synthetic.png');
    $change = DB::transaction(fn () => $edits->resolve($actor, $context, $intent, $hash, $prepared['receipt']));
    $saved = app(SaveCatalogMainImage::class)->handle($actor, 'product.main_image', $product->id, [], $context['expected_fingerprint'], $change);
    expect($saved->main_image_id)->toBe(CatalogAsset::query()->sole()->id);
});

test('all receipt operations bind the complete ordinary draft and reject changed fields', function (string $operation) {
    [$actor, $product, $context, $choice] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $intent = $operation === 'set' ? $edits->stageSource($actor, $context, $choice) : ['operation' => $operation, 'source_token' => null, 'draft_intent_id' => (string) Str::ulid()];
    $hash = $edits->draftHash(['image_intent' => $intent, 'catalog_settings' => ['editor_properties' => ['a', 'b']]]);
    $prepared = $edits->prepare($actor, $context, $intent, $hash);
    expect($prepared['receipt'])->toBeString();
    expect($edits->resolve($actor, $context, $intent, $hash, $prepared['receipt'])->operation)->toBe($operation);
    expect(fn () => $edits->resolve($actor, $context, $intent, $edits->draftHash(['changed' => true]), $prepared['receipt']))->toThrow(ValidationException::class);
    expect($product->fresh()->main_image_id)->toBeNull();
})->with(['keep', 'remove', 'set']);

test('tokens and receipts reject a different actor context intent expiry or forged payload', function (string $fault) {
    [$actor, $product, $context, $choice] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $intent = $edits->stageSource($actor, $context, $choice);
    $hash = $edits->draftHash(['image_intent' => $intent]);
    $prepared = $edits->prepare($actor, $context, $intent, $hash);
    $receipt = $prepared['receipt'];
    if ($fault === 'actor') {
        $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    } elseif ($fault === 'context') {
        $context['owner_context'] = ['list_key' => 'group-products', 'parent_id' => $product->group_id];
    } elseif ($fault === 'intent') {
        $intent['draft_intent_id'] = (string) Str::ulid();
    } elseif ($fault === 'expiry') {
        $this->travel(25)->hours();
    } else {
        $receipt = str_repeat('x', 33000);
    }
    expect(fn () => $edits->resolve($actor, $context, $intent, $hash, $receipt))->toThrow(ValidationException::class);
})->with(['actor', 'context', 'intent', 'expiry', 'forged']);

test('preparation rejects ambient transactions for keep and set before publishing', function (string $operation) {
    [$actor, $product, $context, $choice] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $intent = $operation === 'set' ? $edits->stageSource($actor, $context, $choice) : ['operation' => 'keep', 'source_token' => null, 'draft_intent_id' => (string) Str::ulid()];
    expect(fn () => DB::transaction(fn () => $edits->prepare($actor, $context, $intent, $edits->draftHash(['image_intent' => $intent]))))->toThrow(LogicException::class);
    expect(CatalogAsset::query()->count())->toBe(0);
})->with(['keep', 'set']);

test('new Group receipts bind one creation session without accepting arbitrary owner identities', function () {
    config(['catalog-assets.enabled' => true]);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $context = ['target_key' => 'group.main_image', 'record_id' => null, 'creation_session' => (string) Str::ulid(), 'owner_context' => [], 'expected_fingerprint' => null, 'expected_group_fingerprint' => null];
    $intent = ['operation' => 'keep', 'source_token' => null, 'draft_intent_id' => (string) Str::ulid()];
    $edits = app(CatalogMainImageEdits::class);
    $hash = $edits->draftHash(['name' => 'New group', 'image_intent' => $intent]);
    $receipt = $edits->prepare($actor, $context, $intent, $hash)['receipt'];
    expect($edits->resolve($actor, $context, $intent, $hash, $receipt)->operation)->toBe('keep');
    expect(fn () => $edits->resolve($actor, [...$context, 'creation_session' => (string) Str::ulid()], $intent, $hash, $receipt))->toThrow(ValidationException::class);
    expect(fn () => $edits->prepare($actor, [...$context, 'record_id' => 999], $intent, $hash))->toThrow(ValidationException::class);
});

test('a final receipt rejects source journal changes without reading its manifest file', function () {
    [$actor, $product, $context, $choice] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $intent = $edits->stageSource($actor, $context, $choice);
    $hash = $edits->draftHash(['image_intent' => $intent]);
    $receipt = $edits->prepare($actor, $context, $intent, $hash)['receipt'];
    CatalogAssetImportSource::query()->whereKey($choice['source_id'])->update(['status' => 'stale']);
    expect(fn () => $edits->resolve($actor, $context, $intent, $hash, $receipt))->toThrow(ValidationException::class);
});

test('unknown image Save reconciliation confirms desired state without replay and detects conflicting owners', function () {
    [$actor, $product, $context, $choice] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $intent = $edits->stageSource($actor, $context, $choice);
    $hash = $edits->draftHash(['image_intent' => $intent]);
    $receipt = $edits->prepare($actor, $context, $intent, $hash)['receipt'];
    expect($edits->reconcile($actor, $context, $intent, $hash, $receipt)['outcome'])->toBe('baseline_matches');
    $change = $edits->resolve($actor, $context, $intent, $hash, $receipt);
    app(SaveCatalogMainImage::class)->handle($actor, 'product.main_image', $product->id, [], $context['expected_fingerprint'], $change);
    $afterSave = $product->fresh()->getAttributes();
    $result = $edits->reconcile($actor, $context, $intent, $hash, $receipt);
    expect($result['outcome'])->toBe('goal_matches')->and($result['expected_fingerprint'])->toBe(SaveCatalogProduct::fingerprint($product->fresh()))
        ->and($product->fresh()->getAttributes())->toBe($afterSave);
    $product->refresh()->forceFill(['main_image_id' => null, 'description' => 'Someone else changed this owner'])->save();
    $beforeConflict = $product->fresh()->getAttributes();
    expect($edits->reconcile($actor, $context, $intent, $hash, $receipt)['outcome'])->toBe('conflict')
        ->and($product->fresh()->getAttributes())->toBe($beforeConflict);
    expect(fn () => $edits->resolve($actor, $context, $intent, $hash, $receipt))->toThrow(ValidationException::class);
});

test('independently prepared image survives an outer owner rollback with its draft receipt reusable', function () {
    [$actor, $product, $context, $choice] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $intent = $edits->stageSource($actor, $context, $choice);
    $hash = $edits->draftHash(['image_intent' => $intent]);
    $receipt = $edits->prepare($actor, $context, $intent, $hash)['receipt'];
    $change = $edits->resolve($actor, $context, $intent, $hash, $receipt);
    $before = $product->getAttributes();
    $revision = $product->group->fresh()->catalog_revision;
    expect(fn () => DB::transaction(function () use ($actor, $product, $context, $change) {
        app(SaveCatalogMainImage::class)->handle($actor, 'product.main_image', $product->id, [], $context['expected_fingerprint'], $change);
        throw new RuntimeException('owner rollback');
    }))->toThrow(RuntimeException::class, 'owner rollback');
    $asset = CatalogAsset::query()->sole();
    expect($product->fresh()->getAttributes())->toBe($before)->and($product->group->fresh()->catalog_revision)->toBe($revision)
        ->and($asset->preparation_status)->toBe('ready')->and(Storage::disk('catalog-assets')->exists($asset->originalMedia->getPathRelativeToRoot()))->toBeTrue()
        ->and($edits->resolve($actor, $context, $intent, $hash, $receipt)->assetId)->toBe($asset->id);
});

test('source selection cannot widen an exact native drawer context or resolve an unprepared set', function () {
    [$actor, $product, $context, $choice] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $drawerContext = [...$context, 'owner_context' => ['list_key' => 'group-products', 'parent_id' => $product->group_id]];
    expect(fn () => $edits->stageSource($actor, $drawerContext, $choice))->toThrow(ValidationException::class);
    $intent = $edits->stageSource($actor, $context, $choice);
    expect(fn () => $edits->resolve($actor, $context, $intent, $edits->draftHash(['image_intent' => $intent]), ''))->toThrow(ValidationException::class);
    expect(CatalogAsset::query()->count())->toBe(0)->and($product->fresh()->main_image_id)->toBeNull();
});

test('Group creation attaches a real prepared image only through its bound new Group save', function () {
    [$actor] = imageEditSource();
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'group.main_image', [], false);
    $sources->scanNext($actor, $source->id, 1, 0);
    $sources->finalize($actor, $source->id, 1, $sources->status($actor, $source->id)['revision']);
    $entry = $sources->manifest($actor, $source->id, 1)['entries'][0];
    $choice = ['source_id' => $source->id, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']];
    $context = ['target_key' => 'group.main_image', 'record_id' => null, 'creation_session' => (string) Str::ulid(), 'owner_context' => [], 'expected_fingerprint' => null, 'expected_group_fingerprint' => null];
    $edits = app(CatalogMainImageEdits::class);
    $intent = $edits->stageSource($actor, $context, $choice);
    $hash = $edits->draftHash(['name' => 'New image group', 'image_intent' => $intent]);
    $receipt = $edits->prepare($actor, $context, $intent, $hash)['receipt'];
    $group = app(SaveCatalogGroup::class)->handle($actor, null, ['name' => 'New image group'], $edits->resolve($actor, $context, $intent, $hash, $receipt));
    expect($group->fresh()->main_image_id)->toBe(CatalogAsset::query()->sole()->id);
    expect(fn () => $edits->resolve($actor, [...$context, 'creation_session' => (string) Str::ulid()], $intent, $hash, $receipt))->toThrow(ValidationException::class);
});

test('no Save keep and already empty remove report only image goals without performing writes', function (string $operation) {
    [$actor, $product, $context] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $intent = ['operation' => $operation, 'source_token' => null, 'draft_intent_id' => (string) Str::ulid()];
    $hash = $edits->draftHash(['image_intent' => $intent]);
    $receipt = $edits->prepare($actor, $context, $intent, $hash)['receipt'];
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $result = $edits->reconcile($actor, $context, $intent, $hash, $receipt);
    expect($result['outcome'])->toBe('goal_matches')->and($result['message'])->toBe('Image matches your choice.')
        ->and($result)->not->toHaveKey('operation_id')->and($writes)->toBe([])
        ->and(CatalogAsset::count())->toBe(0)->and($product->fresh()->main_image_id)->toBeNull();
})->with(['keep', 'remove']);

test('a different request may meet the image goal and a second fresh inspection detects intervening edits', function () {
    [$actor, $product, $context, $choice] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $intent = $edits->stageSource($actor, $context, $choice);
    $hash = $edits->draftHash(['image_intent' => $intent]);
    $receipt = $edits->prepare($actor, $context, $intent, $hash)['receipt'];
    $asset = CatalogAsset::sole();
    app(SaveCatalogMainImage::class)->handle($actor, 'product.main_image', $product->id, [], $context['expected_fingerprint'], new CatalogAssetChange('set', $asset->id));
    $first = $edits->reconcile($actor, $context, $intent, $hash, $receipt);
    expect($first['outcome'])->toBe('goal_matches');
    $this->travel(2)->seconds();
    app(SaveCatalogMainImage::class)->handle($actor, 'product.main_image', $product->id, [], $first['expected_fingerprint'], new CatalogAssetChange('remove'));
    $second = $edits->reconcile($actor, $context, $intent, $hash, $receipt);
    expect($second['outcome'])->toBe('conflict')->and($product->fresh()->main_image_id)->toBeNull();
    expect(fn () => app(SaveCatalogMainImage::class)->handle($actor, 'product.main_image', $product->id, [], $first['expected_fingerprint'], new CatalogAssetChange('set', $asset->id)))->toThrow(ValidationException::class);
});

test('recovery reauthorizes a revoked manager and rejects a deleted image owner', function () {
    [$actor, $product, $context] = imageEditSource();
    $edits = app(CatalogMainImageEdits::class);
    $intent = ['operation' => 'remove', 'source_token' => null, 'draft_intent_id' => (string) Str::ulid()];
    $hash = $edits->draftHash(['image_intent' => $intent]);
    $receipt = $edits->prepare($actor, $context, $intent, $hash)['receipt'];
    $actor->update(['email' => 'revoked@example.test']);
    expect(fn () => $edits->reconcile($actor, $context, $intent, $hash, $receipt))->toThrow(AuthorizationException::class);
    $actor->update(['email' => 'ycm@data4.work']);
    $product->delete();
    expect(fn () => $edits->reconcile($actor, $context, $intent, $hash, $receipt))->toThrow(ValidationException::class);
});
