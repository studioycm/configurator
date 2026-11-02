<?php

use App\Actions\ApplyCatalogAssets;
use App\Actions\AssignCatalogAsset;
use App\Actions\ProcessNextCatalogAssetBatchItem;
use App\Actions\ReviewCatalogAssets;
use App\Actions\SaveCatalogMainImage;
use App\DTO\CatalogAssetChange;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetBatchItem;
use App\Models\CatalogAssetImportSource;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetReviewFiles;
use App\Services\CatalogAssetReviewQuery;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['catalog-assets.enabled' => true, 'cache.default' => 'array']);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

function imageApplySource(string $target = 'product.main_image'): string
{
    $disk = catalogImageTestDisk('apply-fixture');
    $image = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($image);
    $disk->put('main.png', ob_get_clean());
    imagedestroy($image);
    config(['catalog-assets.sources.apply_fixture' => ['label' => 'Owned Apply fixture', 'path' => $disk->path(''), 'read_only' => true]]);
    $s = app(CatalogAssetSources::class);
    $source = $s->startScan(auth()->user(), 'apply_fixture', $target, [], false);
    for ($n = 0; $n < 12; $n++) {
        $p = $s->status(auth()->user(), $source->id);
        if ($p['stage'] === 'ready') {
            return $source->id;
        }$p['traversal_complete'] ? $s->finalize(auth()->user(), $source->id, 1, $p['revision']) : $s->scanNext(auth()->user(), $source->id, 1, $p['revision']);
    }
    throw new RuntimeException('Fixture scan did not finish.');
}

function imageApplyReview(array $ids, string $target = 'product.main_image', ?string $source = null): CatalogAssetBatch
{
    $source ??= imageApplySource($target);
    $a = app(ReviewCatalogAssets::class);
    $entity = $target === 'product.main_image' ? 'product' : 'group';
    $table = $entity === 'product' ? 'products' : 'groups';
    $scope = $a->freeze(auth()->user(), $target, [], $ids, [['column' => $table.'.id', 'direction' => 'asc']]);
    $ordered = app(CatalogAssetReviewFiles::class)->openLaunch(auth()->user(), $scope['token'])['record_ids'];
    $entry = app(CatalogAssetSources::class)->manifest(auth()->user(), $source, 1)['entries'][0];
    $batch = $a->handle(auth()->user(), $entity, $ordered, ['review_id' => $scope['review_id'], 'scope_token' => $scope['token'], 'source_id' => $source, 'source_version' => 1, 'operation' => $entity === 'product' ? 'folder_matches' : 'one_image', 'mode' => 'fill_missing', 'recipe' => [], 'entry_choice' => ['source_id' => $source, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']]]);
    for ($n = 0; $n < 60 && $batch->fresh()->status === 'building'; $n++) {
        $batch->refresh();
        $a->buildNext(auth()->user(), $batch->id, $batch->preview_version, $batch->build_revision);
    }
    expect($batch->fresh()->status)->toBe('review');

    return $batch->fresh();
}

test('one explicit intent accepts exactly its ordered reviewed rows once without preparing or saving images', function () {
    $products = Product::factory()->count(3)->for(Group::factory()->create())->create(['profile_fields' => ['product_pic' => 'main']]);
    $batch = imageApplyReview($products->pluck('id')->all());
    $a = app(ApplyCatalogAssets::class);
    $rows = $batch->items()->orderBy('position')->get();
    $before = $products->mapWithKeys(fn ($p) => [$p->id => $p->fresh()->getAttributes()])->all();
    $intent = $a->prepareIntent(auth()->user(), $batch->id, 1, [$rows[2]->id, $rows[0]->id]);
    expect($a->prepareIntent(auth()->user(), $batch->id, 1, [$rows[0]->id, $rows[2]->id]))->toBe($intent);
    $a->handle(auth()->user(), $batch->id, $intent);
    $accepted = $batch->items()->whereNotNull('acceptance_id')->orderBy('position')->get();
    expect($accepted->pluck('id')->all())->toBe([$rows[0]->id, $rows[2]->id])->and($accepted->pluck('acceptance_id')->unique()->all())->toBe([$intent])->and($accepted->pluck('request_step_id')->unique())->toHaveCount(2);
    $saved = $batch->fresh()->getAttributes();
    $a->handle(auth()->user(), $batch->id, $intent);
    expect($batch->fresh()->getAttributes())->toBe($saved)->and($batch->fresh()->active_acceptance_id)->toBe($intent)->and($batch->fresh()->pending_apply_intent)->toBeNull();
    expect($products->mapWithKeys(fn ($p) => [$p->id => $p->fresh()->getAttributes()])->all())->toBe($before)->and(CatalogAsset::count())->toBe(0)->and(DB::transactionLevel())->toBe(0);
});

function imageApplyAccept(CatalogAssetBatch $batch, ?array $ids = null): string
{
    $a = app(ApplyCatalogAssets::class);
    $id = $a->prepareIntent(auth()->user(), $batch->id, $batch->preview_version, $ids ?? $batch->items()->orderBy('position')->pluck('id')->all());
    $a->handle(auth()->user(), $batch->id, $id);

    return $id;
}

test('Apply bounds and excluded rows cannot silently truncate or accept', function () {
    $products = Product::factory()->count(51)->for(Group::factory()->create())->create(['profile_fields' => ['product_pic' => 'main']]);
    $batch = imageApplyReview($products->modelKeys());
    $a = app(ApplyCatalogAssets::class);
    $ids = $batch->items()->orderBy('position')->pluck('id')->all();
    foreach ([[], $ids, [$ids[0], $ids[0]]] as $invalid) {
        expect(fn () => $a->prepareIntent(auth()->user(), $batch->id, 1, $invalid))->toThrow(ValidationException::class);
    }
    $batch->items()->whereKey($ids[0])->update(['included' => false]);
    expect(fn () => $a->prepareIntent(auth()->user(), $batch->id, 1, [$ids[0]]))->toThrow(ValidationException::class);
    $id = $a->prepareIntent(auth()->user(), $batch->id, 1, array_slice($ids, 1));
    $a->handle(auth()->user(), $batch->id, $id);
    expect($batch->items()->whereNotNull('accepted_at')->count())->toBe(50);
});

test('pending intent replacement and explicit discard reject delayed acceptance', function () {
    $p = Product::factory()->count(2)->for(Group::factory()->create())->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview($p->modelKeys());
    $a = app(ApplyCatalogAssets::class);
    $ids = $b->items()->pluck('id')->all();
    $old = $a->prepareIntent(auth()->user(), $b->id, 1, [$ids[0]]);
    $next = $a->prepareIntent(auth()->user(), $b->id, 1, [$ids[1]]);
    expect($next)->not->toBe($old);
    expect(fn () => $a->handle(auth()->user(), $b->id, $old))->toThrow(ValidationException::class);
    $a->discardPendingIntent(auth()->user(), $b->id, $next);
    expect(fn () => $a->handle(auth()->user(), $b->id, $next))->toThrow(ValidationException::class);
    expect($b->items()->whereNotNull('accepted_at')->count())->toBe(0);
});

test('one issued step commits once and read only progress never starts the following row', function () {
    $p = Product::factory()->count(2)->for(Group::factory()->create())->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview($p->modelKeys());
    $id = imageApplyAccept($b);
    $rows = $b->items()->orderBy('position')->get();
    $process = app(ProcessNextCatalogAssetBatchItem::class);
    $result = $process->handle(auth()->user(), $b->id, $id, $rows[0]->request_step_id);
    expect($result['outcome'])->toBe('applied')->and($p[0]->fresh()->main_image_id)->not->toBeNull()->and($p[1]->fresh()->main_image_id)->toBeNull();
    $before = $b->items()->get()->map->getAttributes()->all();
    $again = $process->handle(auth()->user(), $b->id, $id, $rows[0]->request_step_id);
    app(CatalogAssetReviewQuery::class)->applyProgress(auth()->user(), $b->id, $id);
    expect($again['outcome'])->toBe('applied')->and($b->items()->get()->map->getAttributes()->all())->toBe($before);
    $process->handle(auth()->user(), $b->id, $id, $rows[1]->request_step_id);
    expect($p[1]->fresh()->main_image_id)->toBe($p[0]->fresh()->main_image_id)->and(CatalogAsset::count())->toBe(1)->and($b->fresh()->status)->toBe('completed')->and($b->fresh()->active_acceptance_id)->toBeNull();
});

test('live claims are busy and expired claims require explicit recovery before their step changes', function () {
    $p = Product::factory()->count(2)->for(Group::factory()->create())->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview($p->modelKeys());
    $id = imageApplyAccept($b);
    $rows = $b->items()->orderBy('position')->get();
    $first = $rows[0];
    $old = $first->request_step_id;
    $first->forceFill(['apply_status' => 'processing', 'processing_token' => (string) Str::ulid(), 'processing_expires_at' => now()->addMinute(), 'attempts' => 1])->save();
    $process = app(ProcessNextCatalogAssetBatchItem::class);
    expect($process->handle(auth()->user(), $b->id, $id, $old)['outcome'])->toBe('busy');
    expect($process->handle(auth()->user(), $b->id, $id, $rows[1]->request_step_id)['outcome'])->toBe('out_of_order');
    $first->forceFill(['processing_expires_at' => now()->subSecond()])->save();
    expect($process->handle(auth()->user(), $b->id, $id, $old)['outcome'])->toBe('recovery_required');
    expect($first->fresh()->request_step_id)->toBe($old);
    $progress = app(ApplyCatalogAssets::class)->continueAcceptance(auth()->user(), $b->id, $id);
    expect($first->fresh()->request_step_id)->not->toBe($old)->and($progress['next_step_id'])->toBe($first->fresh()->request_step_id)->and($first->fresh()->attempts)->toBe(1);
    expect($process->handle(auth()->user(), $b->id, $id, $old)['outcome'])->toBe('stale_step');
});

test('an external edit makes only its row stale while later independent rows can succeed', function () {
    $p = Product::factory()->count(2)->for(Group::factory()->create())->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview($p->modelKeys());
    $id = imageApplyAccept($b);
    $rows = $b->items()->orderBy('position')->get();
    $p[0]->update(['product_code' => 'EXTERNAL']);
    $process = app(ProcessNextCatalogAssetBatchItem::class);
    expect($process->handle(auth()->user(), $b->id, $id, $rows[0]->request_step_id)['outcome'])->toBe('stale');
    $process->handle(auth()->user(), $b->id, $id, $rows[1]->request_step_id);
    expect($p[0]->fresh()->main_image_id)->toBeNull()->and($p[1]->fresh()->main_image_id)->not->toBeNull()->and($b->fresh()->status)->toBe('partial');
});

test('Group siblings and their ancestor keep immutable reviewed fingerprints and causal continuation', function () {
    $parent = Group::factory()->create();
    $a = Group::factory()->create(['parent_id' => $parent->id]);
    $c = Group::factory()->create(['parent_id' => $parent->id]);
    $b = imageApplyReview([$a->id, $c->id, $parent->id], 'group.main_image');
    $id = imageApplyAccept($b);
    $rows = $b->items()->orderBy('position')->get();
    $fingerprints = $rows->pluck('record_fingerprint', 'id')->all();
    $process = app(ProcessNextCatalogAssetBatchItem::class);
    foreach ($rows as $row) {
        expect($process->handle(auth()->user(), $b->id, $id, $row->request_step_id)['outcome'])->toBe('applied');
    }
    expect($b->items()->pluck('record_fingerprint', 'id')->all())->toBe($fingerprints)->and($b->fresh()->status)->toBe('completed')->and($rows[1]->fresh()->snapshot['execution_causes'])->toHaveCount(1)->and($rows[2]->fresh()->snapshot['execution_causes'])->toHaveCount(2);
});

test('Group external edits are never rebased by an earlier accepted sibling write', function () {
    $parent = Group::factory()->create();
    $a = Group::factory()->create(['parent_id' => $parent->id]);
    $c = Group::factory()->create(['parent_id' => $parent->id]);
    $b = imageApplyReview([$a->id, $c->id], 'group.main_image');
    $id = imageApplyAccept($b);
    $rows = $b->items()->orderBy('position')->get();
    $c->update(['name' => 'External edit']);
    $process = app(ProcessNextCatalogAssetBatchItem::class);
    expect($process->handle(auth()->user(), $b->id, $id, $rows[0]->request_step_id)['outcome'])->toBe('applied');
    expect($rows[1]->fresh()->snapshot['execution_causes'] ?? [])->toBe([]);
    expect($process->handle(auth()->user(), $b->id, $id, $rows[1]->request_step_id)['outcome'])->toBe('stale')->and($c->fresh()->main_image_id)->toBeNull();
});

test('native finalizer handles no op and rolls back a changed pointer and revisions on failure', function () {
    $p = Product::factory()->for(Group::factory()->create())->create();
    $before = $p->fresh()->getAttributes();
    $groupBefore = $p->group->fresh()->getAttributes();
    $called = [];
    $writer = app(SaveCatalogMainImage::class);
    $fp = CatalogAssetAssignments::fingerprint($p->fresh());
    $writer->handle(auth()->user(), 'product.main_image', $p->id, [], $fp, new CatalogAssetChange('remove'), null, function ($owner, $changed) use (&$called) {
        $called = [$owner->id, $changed, DB::transactionLevel()];
    });
    expect($called)->toBe([$p->id, false, 1]);
    $source = imageApplySource();
    $choice = app(CatalogAssetSources::class)->manifest(auth()->user(), $source, 1)['entries'][0];
    $ready = app(CatalogAssetStorage::class)->prepare(auth()->user(), ['source_id' => $source, 'scan_version' => 1, 'entry_id' => $choice['entry_id'], 'sha256' => $choice['sha256']], 'product.main_image', hash('sha256', 'rollback fixture'));
    expect($ready['outcome'])->toBe('ready');
    expect(fn () => $writer->handle(auth()->user(), 'product.main_image', $p->id, [], $fp, new CatalogAssetChange('set', $ready['asset']->id), null, function () {
        throw new RuntimeException('finalize rollback');
    }))->toThrow(RuntimeException::class, 'finalize rollback');
    expect($p->fresh()->getAttributes())->toBe($before)->and($p->group->fresh()->getAttributes())->toBe($groupBefore)->and(CatalogAsset::count())->toBe(1);
});

test('accepted rows cannot be revised or excluded and a fresh remaining review preserves private exclusions', function () {
    $p = Product::factory()->count(3)->for(Group::factory()->create())->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview($p->modelKeys());
    $rows = $b->items()->orderBy('position')->get();
    $review = app(ReviewCatalogAssets::class);
    $review->setIncluded(auth()->user(), $b->id, 1, [$rows[2]->id], false);
    $id = imageApplyAccept($b, [$rows[0]->id]);
    expect(fn () => $review->revise(auth()->user(), $b->id, 1, ['recipe' => []]))->toThrow(ValidationException::class);
    expect(fn () => $review->setIncluded(auth()->user(), $b->id, 1, [$rows[0]->id], false))->toThrow(ValidationException::class);
    expect(fn () => $review->freezeRemaining(auth()->user(), $b->id, 1))->toThrow(ValidationException::class);
    app(ProcessNextCatalogAssetBatchItem::class)->handle(auth()->user(), $b->id, $id, $rows[0]->fresh()->request_step_id);
    $fork = $review->freezeRemaining(auth()->user(), $b->id, 1);
    $scope = app(CatalogAssetReviewFiles::class)->openLaunch(auth()->user(), $fork['token']);
    expect($scope['record_ids'])->toBe([$p[1]->id, $p[2]->id])->and($scope['review_selection'][$p[2]->id])->toBe(['included' => false, 'origin' => 'explicit'])->and($b->items()->count())->toBe(3);
});

test('acceptance expiry and ownership are checked without canceling already accepted work', function () {
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview([$p->id]);
    $a = app(ApplyCatalogAssets::class);
    $row = $b->items()->first();
    $intent = $a->prepareIntent(auth()->user(), $b->id, 1, [$row->id]);
    $other = User::factory()->create(['email' => 'other@example.com']);
    expect(fn () => $a->handle($other, $b->id, $intent))->toThrow(AuthorizationException::class);
    $b->forceFill(['expires_at' => now()->subSecond()])->save();
    expect(fn () => $a->handle(auth()->user(), $b->id, $intent))->toThrow(ValidationException::class);
    $b->forceFill(['expires_at' => now()->addMinute()])->save();
    $a->handle(auth()->user(), $b->id, $intent);
    $b->forceFill(['expires_at' => now()->subSecond()])->save();
    expect(app(ProcessNextCatalogAssetBatchItem::class)->handle(auth()->user(), $b->id, $intent, $row->fresh()->request_step_id)['outcome'])->toBe('applied');
});

test('a later Group write advances a same acceptance recoverable failure without resetting its result', function () {
    $parent = Group::factory()->create();
    $a = Group::factory()->create(['parent_id' => $parent->id]);
    $c = Group::factory()->create(['parent_id' => $parent->id]);
    $b = imageApplyReview([$a->id, $c->id], 'group.main_image');
    $id = imageApplyAccept($b);
    $rows = $b->items()->orderBy('position')->get();
    $snapshot = $rows[0]->snapshot;
    $result = ['code' => 'owned_retry_fixture', 'retryable' => true];
    $snapshot['apply_result'] = $result;
    $rows[0]->forceFill(['apply_status' => 'failed', 'attempts' => 1, 'snapshot' => $snapshot])->save();
    $process = app(ProcessNextCatalogAssetBatchItem::class);
    $process->handle(auth()->user(), $b->id, $id, $rows[1]->request_step_id);
    expect($rows[0]->fresh()->snapshot['apply_result'])->toBe($result)->and($rows[0]->fresh()->snapshot['execution_causes'])->toHaveCount(1)->and($rows[0]->fresh()->attempts)->toBe(1);
    app(ApplyCatalogAssets::class)->retryFailed(auth()->user(), $b->id, $id);
    expect($process->handle(auth()->user(), $b->id, $id, $rows[0]->fresh()->request_step_id)['outcome'])->toBe('applied')->and($b->fresh()->status)->toBe('completed');
});

test('preparation sees no owner transaction and Busy does not inflate attempts or fail a row', function () {
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview([$p->id]);
    $id = imageApplyAccept($b);
    $row = $b->items()->first();
    $storage = Mockery::mock(CatalogAssetStorage::class);
    $storage->shouldReceive('prepare')->once()->andReturnUsing(function () {
        expect(DB::transactionLevel())->toBe(0);

        return ['outcome' => 'busy'];
    });
    app()->instance(CatalogAssetStorage::class, $storage);
    expect(app(ProcessNextCatalogAssetBatchItem::class)->handle(auth()->user(), $b->id, $id, $row->request_step_id)['outcome'])->toBe('busy')->and($row->fresh()->apply_status)->toBe('pending')->and($row->fresh()->attempts)->toBe(0)->and($row->fresh()->request_step_id)->toBe($row->request_step_id)->and($p->fresh()->main_image_id)->toBeNull();
});

test('physical source drift is stale and not retryable without touching saved owners', function () {
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview([$p->id]);
    $id = imageApplyAccept($b);
    $row = $b->items()->first();
    Storage::disk('apply-fixture')->put('main.png', 'changed');
    $r = app(ProcessNextCatalogAssetBatchItem::class)->handle(auth()->user(), $b->id, $id, $row->request_step_id);
    expect($r['outcome'])->toBe('stale')->and($row->fresh()->snapshot['apply_result']['retryable'])->toBeFalse()->and($p->fresh()->main_image_id)->toBeNull();
});

test('an invalidated late worker cannot commit its prepared asset or overwrite the newer claim', function () {
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview([$p->id]);
    $id = imageApplyAccept($b);
    $row = $b->items()->first();
    $oldStep = $row->request_step_id;
    $token = (string) Str::ulid();
    $row->forceFill(['apply_status' => 'processing', 'processing_token' => $token, 'processing_expires_at' => now()->subSecond(), 'attempts' => 1])->save();
    $old = $row->fresh();
    $ready = app(CatalogAssetStorage::class)->prepare(auth()->user(), $row->snapshot['chosen_entry'], 'product.main_image', ProcessNextCatalogAssetBatchItem::importKey(auth()->user(), $b, $row, app(CatalogAssetReviewFiles::class)));
    app(ApplyCatalogAssets::class)->continueAcceptance(auth()->user(), $b->id, $id);
    $new = $row->fresh()->getAttributes();
    expect(fn () => app(AssignCatalogAsset::class)->handle(auth()->user(), $old, $oldStep, $token, new CatalogAssetChange('set', $ready['asset']->id)))->toThrow(ValidationException::class);
    expect($row->fresh()->getAttributes())->toBe($new)->and($p->fresh()->main_image_id)->toBeNull()->and(CatalogAsset::count())->toBe(1);
});

test('source metadata drifting after preparation cannot publish a ready asset to an owner', function () {
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview([$p->id]);
    $id = imageApplyAccept($b);
    $row = $b->items()->first();
    $real = app(CatalogAssetStorage::class);
    $storage = Mockery::mock(CatalogAssetStorage::class);
    $storage->shouldReceive('prepare')->once()->andReturnUsing(function (...$args) use ($real, $b) {
        $result = $real->prepare(...$args);
        CatalogAssetImportSource::whereKey($b->source_id)->update(['scan_version' => 2]);

        return $result;
    });
    app()->instance(CatalogAssetStorage::class, $storage);
    $r = app(ProcessNextCatalogAssetBatchItem::class)->handle(auth()->user(), $b->id, $id, $row->request_step_id);
    expect($r['outcome'])->toBe('stale')->and($p->fresh()->main_image_id)->toBeNull()->and(CatalogAsset::count())->toBe(1);
});

test('a failed journal publication rolls back image revision and every causal expectation', function () {
    $parent = Group::factory()->create();
    $a = Group::factory()->create(['parent_id' => $parent->id]);
    $c = Group::factory()->create(['parent_id' => $parent->id]);
    $b = imageApplyReview([$a->id, $c->id], 'group.main_image');
    $id = imageApplyAccept($b);
    $rows = $b->items()->orderBy('position')->get();
    $beforeA = $a->fresh()->getAttributes();
    $beforeC = $c->fresh()->getAttributes();
    $rowBefore = $rows[1]->getAttributes();
    $fail = true;
    CatalogAssetBatchItem::updating(function ($row) use (&$fail, $rows) {
        if ($fail && $row->id === $rows[0]->id && $row->apply_status === 'applied') {
            throw new RuntimeException('Owned result publication failure');
        }
    });
    try {
        $result = app(ProcessNextCatalogAssetBatchItem::class)->handle(auth()->user(), $b->id, $id, $rows[0]->request_step_id);
        expect($result['outcome'])->toBe('paused')->and($a->fresh()->getAttributes())->toBe($beforeA)->and($c->fresh()->getAttributes())->toBe($beforeC)->and($rows[1]->fresh()->getAttributes())->toBe($rowBefore)->and(CatalogAsset::count())->toBe(1);
    } finally {
        $fail = false;
    }
});

test('an external Group edit after causal continuation still fails the following full fingerprint', function () {
    $parent = Group::factory()->create();
    $a = Group::factory()->create(['parent_id' => $parent->id]);
    $c = Group::factory()->create(['parent_id' => $parent->id]);
    $b = imageApplyReview([$a->id, $c->id], 'group.main_image');
    $id = imageApplyAccept($b);
    $rows = $b->items()->orderBy('position')->get();
    $process = app(ProcessNextCatalogAssetBatchItem::class);
    $process->handle(auth()->user(), $b->id, $id, $rows[0]->request_step_id);
    $c->update(['description' => 'External after commit']);
    expect($process->handle(auth()->user(), $b->id, $id, $rows[1]->request_step_id)['outcome'])->toBe('stale')->and($c->fresh()->main_image_id)->toBeNull()->and($rows[1]->fresh()->snapshot['execution_causes'])->toHaveCount(1);
});

test('old successful replay cannot switch a newer active subset or replay its first row', function () {
    $p = Product::factory()->count(2)->for(Group::factory()->create())->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview($p->modelKeys());
    $rows = $b->items()->orderBy('position')->get();
    $old = imageApplyAccept($b, [$rows[0]->id]);
    $process = app(ProcessNextCatalogAssetBatchItem::class);
    $oldStep = $rows[0]->fresh()->request_step_id;
    $process->handle(auth()->user(), $b->id, $old, $oldStep);
    $new = imageApplyAccept($b, [$rows[1]->id]);
    $before = $b->fresh()->getAttributes();
    $r = $process->handle(auth()->user(), $b->id, $old, $oldStep);
    expect($r['replay'])->toBeTrue()->and($r['progress']['acceptance_id'])->toBe($old)->and($r['progress']['next_step_id'])->toBeNull()->and($b->fresh()->getAttributes())->toBe($before)->and($b->fresh()->active_acceptance_id)->toBe($new)->and($p[1]->fresh()->main_image_id)->toBeNull();
});

test('a revoked persisted manager cannot prepare through an earlier actor object', function () {
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'main']]);
    $b = imageApplyReview([$p->id]);
    $actor = auth()->user();
    User::whereKey($actor->id)->update(['email' => 'revoked@example.com']);
    expect(fn () => app(ApplyCatalogAssets::class)->prepareIntent($actor, $b->id, 1, [$b->items()->first()->id]))->toThrow(AuthorizationException::class);
    expect($b->fresh()->pending_apply_intent)->toBeNull();
});
