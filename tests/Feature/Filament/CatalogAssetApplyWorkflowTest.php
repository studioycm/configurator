<?php

use App\Actions\ReviewCatalogAssets;
use App\Filament\Pages\CatalogImages;
use App\Livewire\Catalog\AssetAssignment;
use App\Models\CatalogAssetBatch;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetSources;
use Livewire\Livewire;

beforeEach(function () {
    config(['catalog-assets.enabled' => true, 'cache.default' => 'array']);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

function applyWorkflowFixture(int $count = 2): array
{
    $disk = catalogImageTestDisk('apply-ui');
    $img = imagecreatetruecolor(20, 20);
    ob_start();
    imagepng($img);
    $disk->put('main.png', ob_get_clean());
    imagedestroy($img);
    config(['catalog-assets.sources.apply_ui' => ['label' => 'Apply UI', 'path' => $disk->path(''), 'read_only' => true]]);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan(auth()->user(), 'apply_ui', 'product.main_image', [], false);
    for ($n = 0; $n < 12; $n++) {
        $p = $sources->status(auth()->user(), $source->id);
        if ($p['stage'] === 'ready') {
            break;
        }$p['traversal_complete'] ? $sources->finalize(auth()->user(), $source->id, 1, $p['revision']) : $sources->scanNext(auth()->user(), $source->id, 1, $p['revision']);
    }
    $products = Product::factory()->count($count)->create(['profile_fields' => ['product_pic' => 'main']]);
    $scope = app(ReviewCatalogAssets::class)->freeze(auth()->user(), 'product.main_image', [], $products->modelKeys(), [['column' => 'products.id', 'direction' => 'asc']]);
    $props = ['launchToken' => $scope['token'], 'launchSession' => $scope['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'apply-parent'];
    $child = Livewire::test(AssetAssignment::class, $props)->set('recipeData.source_choice', 'source:'.$source->id);
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->call('previewReview', $child->instance()->reviewContext());
    for ($n = 0; $n < 60 && $child->get('reviewProgress')['stage'] === 'building'; $n++) {
        $child->call('buildReview', $child->instance()->reviewContext(), $child->get('reviewProgress')['revision']);
    }

    return [$child, CatalogAssetBatch::findOrFail($scope['review_id']), $products, $props];
}

test('native This Apply checks remain separate from inclusion and fresh return never resumes acceptance', function () {
    [$child,$batch,$products,$props] = applyWorkflowFixture();
    $rows = $batch->items()->orderBy('position')->get();
    $child->assertSee('This Apply')->call('selectReadyImageApply', 'page', $child->instance()->applyContext())->assertHasNoErrors();
    expect($child->get('selectedTableRecords'))->toHaveCount(2);
    $child->call('prepareImageApply', [$rows[0]->id], $child->instance()->applyContext())->assertHasNoErrors();
    $intent = $child->get('applyProgress')['pending_intent']['id'];
    expect($products[0]->fresh()->main_image_id)->toBeNull();
    $child->call('applyImages', $intent, $child->instance()->applyContext())->assertHasNoErrors();
    $fresh = Livewire::test(AssetAssignment::class, $props);
    expect($fresh->get('applyProgress')['acceptance_id'])->toBe($intent)->and($products[0]->fresh()->main_image_id)->toBeNull();
    $step = $fresh->get('applyProgress')['next_step_id'];
    $fresh->call('processNextImageAssignment', $step, $fresh->instance()->applyContext())->assertHasNoErrors()->assertDispatched('catalog-batch-applied');
    expect($products[0]->fresh()->main_image_id)->not->toBeNull()->and($products[1]->fresh()->main_image_id)->toBeNull();
    $fresh->call('processNextImageAssignment', $step, $fresh->instance()->applyContext())->assertNotDispatched('catalog-batch-applied');
    $fresh->set('reviewSearch', $products[1]->product_code)->call('inspectReviewItem', $rows[0]->id, $fresh->instance()->reviewContext());
    expect($fresh->instance()->inspector()['current_url'])->not->toBeNull();
    $json = json_encode($fresh->snapshot);
    expect($json)->not->toContain('execution_causes', 'processing_token', 'acceptance_source', 'manifest_path');
});

test('unpreviewed source or recipe changes cannot prepare the previously reviewed image', function () {
    [$child,$batch] = applyWorkflowFixture();
    $id = $batch->items()->first()->id;
    $child->set('recipeData.parameters.case_sensitive', true)->call('prepareImageApply', [$id], $child->instance()->applyContext())->assertHasErrors('apply');
    expect($batch->fresh()->pending_apply_intent)->toBeNull();
});

test('This Apply selection rejects over fifty and the native selection property is wired', function () {
    [$child,$batch] = applyWorkflowFixture(51);
    $child->call('setReviewPageSize', '100', $child->instance()->reviewContext())->call('selectReadyImageApply', 'page', $child->instance()->applyContext())->assertHasErrors('apply');
    $child->call('selectReadyImageApply', 'next', $child->instance()->applyContext())->assertHasNoErrors();
    expect($child->get('selectedTableRecords'))->toHaveCount(50)->and($child->instance()->getTable()->getCurrentSelectionLivewireProperty())->toBe('selectedTableRecords');
    $child->call('prepareImageApply', $batch->items()->pluck('id')->all(), $child->instance()->applyContext())->assertHasErrors();
    expect($batch->fresh()->pending_apply_intent)->toBeNull();
});

test('parent accepts only a remaining launch bound to its open review and preserves native selection', function () {
    [$child,$batch,$products,$props] = applyWorkflowFixture();
    $parent = Livewire::test(CatalogImages::class)->set('selectedTableRecords', $products->modelKeys());
    $parent->call('returnToImageReview', $batch->id, $parent->instance()->imageBrowserContext())->assertHasNoErrors();
    $fork = app(ReviewCatalogAssets::class)->freezeRemaining(auth()->user(), $batch->id, 1);
    $checks = $parent->get('selectedTableRecords');
    $parent->call('replaceImageReview', $batch->id, 'wrong-host', $fork['token'], $parent->instance()->imageBrowserContext())->assertHasErrors('review');
    $parent->call('replaceImageReview', $batch->id, $parent->instance()->getId(), $fork['token'], $parent->instance()->imageBrowserContext())->assertHasNoErrors();
    expect($parent->get('imageReviewLaunch')['session'])->toBe($fork['review_id'])->and($parent->get('selectedTableRecords'))->toBe($checks);
});

test('matching parent image editors refresh only clean drafts after committed batch events', function () {
    $product = Product::factory()->create();
    $page = Livewire::test(CatalogImages::class);
    $page->call('inspectProductImage', $product->id, $page->instance()->imageBrowserContext());
    $old = $page->get('catalogMainImageOriginal');
    $product->update(['product_name' => 'Changed saved identity']);
    $page->call('reconcileReviewImageOwner', Product::class, [$product->id])->assertHasNoErrors();
    expect($page->get('catalogMainImageOriginal'))->not->toBe($old)->toBe(CatalogAssetAssignments::fingerprint($product->fresh()));
    $page->set('data.image_intent.operation', 'remove');
    $before = $page->get('data');
    $baseline = $page->get('catalogMainImageOriginal');
    $product->update(['product_name' => 'Another saved identity']);
    $page->call('reconcileReviewImageOwner', Product::class, [$product->id]);
    expect($page->get('data'))->toBe($before)->and($page->get('catalogMainImageOriginal'))->toBe($baseline);
});

test('a completed preview enables Apply for ready checks without an intervening Refresh', function () {
    [$child, $batch, $products] = applyWorkflowFixture(3);
    expect($batch->expires_at->isFuture())->toBeTrue();
    $excluded = $batch->items()->orderBy('position')->get()->last();
    $child->call('setReviewIncluded', $excluded->id, false, $child->instance()->reviewContext())
        ->call('selectReadyImageApply', 'page', $child->instance()->applyContext())->assertHasNoErrors();
    expect($child->get('selectedTableRecords'))->toHaveCount(2);
    expect($child->get('applyProgress')['review_complete'])->toBeTrue()
        ->and($child->get('applyProgress')['review_expired'])->toBeFalse()
        ->and($child->get('applyProgress')['pending_intent'])->toBeNull()
        ->and($child->get('applyProgress')['active_acceptance_id'])->toBeNull();
    expect($products->fresh()->pluck('main_image_id')->filter())->toBeEmpty();
});

test('accepted and assigned reviews render current status without an intervening Refresh', function () {
    [$child, $batch, $products] = applyWorkflowFixture();
    $row = $batch->items()->orderBy('position')->first();
    $child->assertSee('Review ready. No images have been assigned.')
        ->set('selectedTableRecords', [(string) $row->id])
        ->call('prepareImageApply', [$row->id], $child->instance()->applyContext())->assertHasNoErrors();
    $intent = $child->get('applyProgress')['pending_intent']['id'];
    $child->call('applyImages', $intent, $child->instance()->applyContext())->assertHasNoErrors()
        ->assertDontSee('No images have been assigned.')
        ->assertSet('reviewProgress.has_acceptance', true)
        ->assertSet('reviewProgress.apply_status', 'accepted');
    expect($products[0]->fresh()->main_image_id)->toBeNull()
        ->and($row->fresh()->acceptance_id)->toBe($intent);
    $child->call('processNextImageAssignment', $child->get('applyProgress')['next_step_id'], $child->instance()->applyContext())->assertHasNoErrors()
        ->assertDontSee('No images have been assigned.')
        ->assertSet('reviewProgress.apply_status', 'completed')
        ->assertSet('applyProgress.counts.applied', 1);
    expect($products[0]->fresh()->main_image_id)->toBe($row->fresh()->prepared_asset_id)
        ->not->toBeNull()->and($products[1]->fresh()->main_image_id)->toBeNull();
});
