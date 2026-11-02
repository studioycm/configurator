<?php

use App\Actions\ReviewCatalogAssets;
use App\Filament\Pages\CatalogImages;
use App\Filament\Resources\CatalogAssetReviewTable;
use App\Livewire\Catalog\AssetAssignment;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetBatch;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetSources;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    config(['catalog-assets.enabled' => true, 'cache.default' => 'array']);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

function workflowReviewSource(string $variant = ''): string
{
    $disk = catalogImageTestDisk('review-ui-fixture'.$variant);
    $alias = 'ui_fixture'.$variant;
    foreach (['a.png', 'b.png', 'b.jpg', 'c.png'] as $name) {
        $image = imagecreatetruecolor(40, 20);
        if ($variant !== '') {
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        }
        ob_start();
        str_ends_with($name, '.jpg') ? imagejpeg($image) : imagepng($image);
        $disk->put($name, ob_get_clean());
        imagedestroy($image);
    }
    config(['catalog-assets.sources.'.$alias => ['label' => 'Review UI fixture', 'path' => $disk->path(''), 'read_only' => true]]);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan(auth()->user(), $alias, 'product.main_image', [], false);
    for ($n = 0; $n < 12; $n++) {
        $p = $sources->status(auth()->user(), $source->id);
        if ($p['stage'] === 'ready') {
            return $source->id;
        }
        $p['traversal_complete'] ? $sources->finalize(auth()->user(), $source->id, 1, $p['revision']) : $sources->scanNext(auth()->user(), $source->id, 1, $p['revision']);
    }
    throw new RuntimeException('Source did not finish.');
}

function workflowReviewFinish($component): void
{
    for ($n = 0; $n < 60 && $component->get('reviewProgress')['stage'] === 'building'; $n++) {
        $component->call('buildReview', $component->instance()->reviewContext(), $component->get('reviewProgress')['revision'])->assertHasNoErrors();
    }
}

test('checked scope opens a separate inline review without changing the ordinary editor', function () {
    $p = Product::factory()->create();
    $page = Livewire::test(CatalogImages::class)->set('selectedTableRecords', [(string) $p->id]);
    $page->call('openImageReview', 'folder_matches', $page->instance()->imageBrowserContext())->assertHasNoErrors();
    expect($page->get('imageReviewLaunch')['count'])->toBe(1);
    $launch = $page->get('imageReviewLaunch');
    $child = Livewire::test(AssetAssignment::class, ['launchToken' => $launch['token'], 'launchSession' => $launch['session'], 'operation' => $launch['operation'], 'parentHost' => $page->instance()->getId()]);
    $child->assertSee('Preview review')->assertDontSee('Apply images');
    expect(CatalogAssetBatch::count())->toBe(0)->and($p->fresh()->main_image_id)->toBeNull();
});

test('exclusions survive finder resolution rebuild filtering pagination and fresh component return', function () {
    $source = workflowReviewSource();
    $products = collect(['a', 'b', 'c'])->map(fn ($r) => Product::factory()->create(['product_code' => strtoupper($r), 'profile_fields' => ['product_pic' => $r, 'Pic.Key' => $r]]));
    $before = $products->mapWithKeys(fn ($p) => [$p->id => $p->fresh()->getAttributes()])->all();
    $page = Livewire::test(CatalogImages::class)->set('selectedTableRecords', $products->pluck('id')->all());
    $page->call('openImageReview', 'folder_matches', $page->instance()->imageBrowserContext());
    $launch = $page->get('imageReviewLaunch');
    $props = ['launchToken' => $launch['token'], 'launchSession' => $launch['session'], 'operation' => 'folder_matches', 'parentHost' => $page->instance()->getId()];
    $child = Livewire::test(AssetAssignment::class, $props)->set('recipeData.source_choice', 'source:'.$source);
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->assertHasNoErrors();
    $child->call('previewReview', $child->instance()->reviewContext())->assertHasNoErrors();
    workflowReviewFinish($child);
    $batch = CatalogAssetBatch::findOrFail($launch['session']);
    $rows = $batch->items()->orderBy('position')->get();
    $child->call('setReviewIncluded', $rows[0]->id, false, $child->instance()->reviewContext())->assertHasNoErrors();
    $child->call('setReviewIncluded', $rows[2]->id, false, $child->instance()->reviewContext())->assertHasNoErrors();
    $child->call('inspectReviewItem', $rows[1]->id, $child->instance()->reviewContext())->assertHasNoErrors();
    $entry = collect(app(CatalogAssetSources::class)->manifest(auth()->user(), $source, 1)['entries'])->firstWhere('name', 'b.png');
    $choice = ['source_id' => $source, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']];
    $old = $child->instance()->reviewContext();
    $child->call('resolveReviewCandidate', $choice, $old)->assertHasNoErrors();
    workflowReviewFinish($child);
    $child->set('reviewSearch', 'C')->call('setReviewPageSize', '5', $child->instance()->reviewContext())->assertHasNoErrors();
    $fresh = Livewire::test(AssetAssignment::class, $props);
    $fresh->call('refreshReviewStatus', $fresh->instance()->reviewContext())->assertHasNoErrors();
    $rows = $batch->items()->orderBy('position')->get();
    expect($rows->pluck('included')->all())->toBe([false, false, false])->and($rows[1]->match_status)->toBe('ready');
    $fresh->call('setReviewIncluded', $rows[1]->id, true, $fresh->instance()->reviewContext())->assertHasNoErrors();
    expect($batch->items()->orderBy('position')->pluck('included')->all())->toBe([false, true, false]);
    $fresh->call('setReviewIncluded', $rows[0]->id, true, $old)->assertHasErrors('review');
    expect($products->mapWithKeys(fn ($p) => [$p->id => $p->fresh()->getAttributes()])->all())->toBe($before)->and(CatalogAsset::count())->toBe(0);
    $payload = json_encode($fresh->snapshot);
    expect($payload)->not->toContain('scope_path', 'manifest_path', 'record_ids', 'review_selection', 'raw_value');
});

test('the native proposed image column serves its signed preview without assigning an image', function () {
    $source = workflowReviewSource();
    $product = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $before = $product->fresh()->getRawOriginal();
    $launch = app(ReviewCatalogAssets::class)->freeze(auth()->user(), 'product.main_image', [], [$product->id], [['column' => 'products.id', 'direction' => 'asc']]);
    $child = Livewire::test(AssetAssignment::class, ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'preview-parent'])
        ->set('recipeData.source_choice', 'source:'.$source);
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->call('previewReview', $child->instance()->reviewContext())->assertHasNoErrors();
    workflowReviewFinish($child);
    $row = CatalogAssetBatch::findOrFail($launch['review_id'])->items()->sole();
    $column = $child->instance()->getTable()->getColumn('proposed')->record($row);
    $url = $column->getImageUrl($column->getState());
    expect($url)->not->toBeNull()->toBe(CatalogAssetReviewTable::proposedUrl($row));
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
    $tampered = preg_replace('/signature=[a-f0-9]+/', 'signature=invalid', $url);
    $this->get($tampered)->assertForbidden();
    expect($product->fresh()->getRawOriginal())->toBe($before)->and(CatalogAsset::count())->toBe(0);
});

test('Group folder matching and unprivileged or disabled review callbacks are denied', function () {
    $group = Group::factory()->create();
    $page = Livewire::test(CatalogImages::class);
    $page->call('switchImageTarget', 'group.main_image', $page->instance()->imageBrowserContext())->set('selectedTableRecords', [$group->id]);
    $page->call('openImageReview', 'folder_matches', $page->instance()->imageBrowserContext())->assertHasErrors('review')->assertSet('imageReviewLaunch', null);
    $page->call('openImageReview', 'one_image', $page->instance()->imageBrowserContext())->assertHasNoErrors();
    $launch = $page->get('imageReviewLaunch');
    $props = ['launchToken' => $launch['token'], 'launchSession' => $launch['session'], 'operation' => 'one_image', 'parentHost' => $page->instance()->getId()];
    $child = Livewire::test(AssetAssignment::class, $props);
    $context = $child->instance()->reviewContext();
    Gate::define('manage-catalog', fn () => false);
    $child->call('refreshReviewStatus', $context)->assertForbidden();
    Gate::define('manage-catalog', fn () => true);
    config(['catalog-assets.enabled' => false]);
    Livewire::test(AssetAssignment::class, $props)->assertNotFound();
});

test('review page size validates raw decimals and preserves ordinal without changing recipe or inclusion', function () {
    $source = workflowReviewSource();
    $products = Product::factory()->count(21)->sequence(fn ($s) => ['product_code' => sprintf('ROW-%03d', $s->index), 'profile_fields' => ['product_pic' => 'a']])->create();
    $a = app(ReviewCatalogAssets::class);
    $launch = $a->freeze(auth()->user(), 'product.main_image', [], $products->pluck('id')->all(), [['column' => 'products.id', 'direction' => 'asc']]);
    $child = Livewire::test(AssetAssignment::class, ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'test-parent'])->set('recipeData.source_choice', 'source:'.$source);
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->call('previewReview', $child->instance()->reviewContext());
    workflowReviewFinish($child);
    $batch = CatalogAssetBatch::findOrFail($launch['review_id']);
    $before = $batch->recipe;
    $pageName = $child->instance()->getTablePaginationPageName();
    $child->call('gotoPage', 3, $pageName)->call('setReviewPageSize', '15', $child->instance()->reviewContext())->assertHasNoErrors();
    expect($child->instance()->getPage($pageName))->toBe(2)->and($child->instance()->getTableRecordsPerPage())->toBe(15)->and($batch->fresh()->recipe)->toBe($before);
    foreach (['0', '501', '01', '1e2', '10.5', '-1', ' 5'] as $raw) {
        $child->call('setReviewPageSize', $raw, $child->instance()->reviewContext())->assertHasErrors('review');
    }
});

test('return to an owned review is read only and missing evidence remains unavailable', function () {
    $source = workflowReviewSource();
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $a = app(ReviewCatalogAssets::class);
    $launch = $a->freeze(auth()->user(), 'product.main_image', [], [$p->id], [['column' => 'products.id', 'direction' => 'asc']]);
    $batch = $a->handle(auth()->user(), 'product', [$p->id], ['review_id' => $launch['review_id'], 'scope_token' => $launch['token'], 'operation' => 'folder_matches', 'source_id' => $source, 'source_version' => 1, 'mode' => 'fill_missing', 'recipe' => []]);
    $props = ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'parent'];
    $child = Livewire::test(AssetAssignment::class, $props);
    expect($child->get('reviewProgress')['classified'])->toBe(0)->and($batch->items()->count())->toBe(0);
    $child->call('buildReview', $child->instance()->reviewContext(), 0)->assertHasNoErrors();
    $row = $batch->items()->sole();
    $child->call('inspectReviewItem', $row->id, $child->instance()->reviewContext());
    Storage::disk('catalog-asset-imports')->delete($batch->manifest_path);
    $fresh = Livewire::test(AssetAssignment::class, $props)->assertSee('Review evidence is unavailable');
    $page = Livewire::test(CatalogImages::class)->call('returnToImageReview', $batch->id, ['target' => 'product.main_image', 'mode' => 'results', 'record' => null, 'revision' => 0])->assertHasNoErrors();
    expect($page->get('imageReviewLaunch')['session'])->toBe($batch->id)->and($batch->fresh()->build_revision)->toBe(1)->and($batch->items()->count())->toBe(1);
});

test('an exact candidate outside the automatic recipe stays manual and clearing it rebuilds without resetting inclusion', function () {
    $source = workflowReviewSource();
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'no-match']]);
    $a = app(ReviewCatalogAssets::class);
    $launch = $a->freeze(auth()->user(), 'product.main_image', [], [$p->id], [['column' => 'products.id', 'direction' => 'asc']]);
    $props = ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'parent'];
    $child = Livewire::test(AssetAssignment::class, $props)->set('recipeData.source_choice', 'source:'.$source);
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->call('previewReview', $child->instance()->reviewContext());
    workflowReviewFinish($child);
    $batch = CatalogAssetBatch::findOrFail($launch['review_id']);
    $row = $batch->items()->sole();
    $child->call('inspectReviewItem', $row->id, $child->instance()->reviewContext())->set('allSourceFiles', true)->set('candidateSearch', 'a.png');
    $candidates = $child->instance()->candidateResults();
    expect($candidates['total'])->toBe(1);
    $child->call('resolveReviewCandidate', $candidates['entries'][0]['choice'], $child->instance()->reviewContext())->assertHasNoErrors();
    workflowReviewFinish($child);
    $row = $batch->items()->sole();
    expect($row->match_status)->toBe('ready')->and($row->included)->toBeFalse()->and($row->snapshot['raw_display'])->toBe('no-match')->and($row->snapshot['manual'])->toBeTrue();
    $child->call('inspectReviewItem', $row->id, $child->instance()->reviewContext())->call('clearReviewOverride', $child->instance()->reviewContext())->assertHasNoErrors();
    workflowReviewFinish($child);
    expect($batch->items()->sole()->match_status)->toBe('missing')->and($batch->items()->sole()->included)->toBeFalse();
});

test('an individual Group uses the one image review path with no invented reference field', function () {
    $disk = catalogImageTestDisk('group-review-ui');
    $image = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($image);
    $disk->put('group.png', ob_get_clean());
    imagedestroy($image);
    config(['catalog-assets.sources.group_ui' => ['label' => 'Group UI', 'path' => $disk->path(''), 'read_only' => true]]);
    $group = Group::factory()->create();
    $before = $group->fresh()->getAttributes();
    $a = app(ReviewCatalogAssets::class);
    $launch = $a->freeze(auth()->user(), 'group.main_image', [], [$group->id], [['column' => 'groups.id', 'direction' => 'asc']]);
    $child = Livewire::test(AssetAssignment::class, ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'one_image', 'parentHost' => 'parent'])->set('recipeData.source_choice', 'alias:group_ui');
    $child->call('chooseReviewSource', $child->instance()->reviewContext());
    for ($n = 0; $n < 12 && $child->get('sourceProgress')['stage'] !== 'ready'; $n++) {
        $child->call('advanceReviewSource', $child->instance()->reviewContext(), $child->get('sourceProgress')['revision'])->assertHasNoErrors();
    }
    $candidates = $child->instance()->candidateResults();
    expect($candidates['total'])->toBe(1);
    $child->call('chooseOneImage', $candidates['entries'][0]['choice'], $child->instance()->reviewContext())->call('previewReview', $child->instance()->reviewContext())->assertHasNoErrors();
    workflowReviewFinish($child);
    expect(CatalogAssetBatch::findOrFail($launch['review_id'])->items()->sole()->match_status)->toBe('ready')->and($group->fresh()->getAttributes())->toBe($before)->and(CatalogAsset::count())->toBe(0);
});

test('recipe preferences contain only validated controls and do not create or change a review', function () {
    $p = Product::factory()->create();
    $launch = app(ReviewCatalogAssets::class)->freeze(auth()->user(), 'product.main_image', [], [$p->id], [['column' => 'products.id', 'direction' => 'asc']]);
    $child = Livewire::test(AssetAssignment::class, ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'parent']);
    $child->call('restoreRecipePreferences', ['mode' => 'replace_existing', 'parameters' => ['source_field' => 'product_code', 'positions' => [1, 2, 4]]], $child->instance()->reviewContext())->assertHasNoErrors()->assertSet('recipeData.positions', '1, 2, 4')->assertSet('recipeData.mode', 'replace_existing');
    expect(CatalogAssetBatch::count())->toBe(0)->and($child->get('sourceId'))->toBeNull();
    $child->call('restoreRecipePreferences', ['mode' => 'fill_missing', 'parameters' => [], 'source_id' => 'forged'], $child->instance()->reviewContext())->assertHasErrors('preferences');
});

test('Preview rejects an unopened source selection and explicit Open uses the confirmed source', function () {
    $a = workflowReviewSource();
    $b = workflowReviewSource('-b');
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $action = app(ReviewCatalogAssets::class);
    $launch = $action->freeze(auth()->user(), 'product.main_image', [], [$p->id], [['column' => 'products.id', 'direction' => 'asc']]);
    $child = Livewire::test(AssetAssignment::class, ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'parent']);
    $child->set('recipeData.source_choice', 'source:'.$a)->call('chooseReviewSource', $child->instance()->reviewContext())->assertHasNoErrors();
    $child->set('recipeData.source_choice', 'source:'.$b)->call('previewReview', $child->instance()->reviewContext())->assertHasErrors('review');
    expect(CatalogAssetBatch::count())->toBe(0);
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->call('previewReview', $child->instance()->reviewContext())->assertHasNoErrors();
    workflowReviewFinish($child);
    $batch = CatalogAssetBatch::findOrFail($launch['review_id']);
    $entryB = collect(app(CatalogAssetSources::class)->manifest(auth()->user(), $b, 1)['entries'])->firstWhere('name', 'a.png');
    expect($batch->source_id)->toBe($b)->and($batch->items()->sole()->snapshot['chosen_entry']['entry_id'])->toBe($entryB['entry_id']);
    $version = $batch->preview_version;
    $child->set('recipeData.source_choice', 'source:'.$a)->call('previewReview', $child->instance()->reviewContext())->assertHasErrors('review');
    expect($batch->fresh()->source_id)->toBe($b)->and($batch->fresh()->preview_version)->toBe($version);
});

test('read only refresh makes the visible source coherent after a lost Open response without reverting same version draft controls', function () {
    $a = workflowReviewSource();
    $b = workflowReviewSource('-b');
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $launch = app(ReviewCatalogAssets::class)->freeze(auth()->user(), 'product.main_image', [], [$p->id], [['column' => 'products.id', 'direction' => 'asc']]);
    $child = Livewire::test(AssetAssignment::class, ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'parent'])->set('recipeData.source_choice', 'source:'.$a);
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->call('previewReview', $child->instance()->reviewContext())->assertHasNoErrors();
    workflowReviewFinish($child);
    $batch = CatalogAssetBatch::findOrFail($launch['review_id']);
    $before = $batch->fresh()->getAttributes();
    $child->set('recipeData.source_choice', 'source:'.$b)->set('recipeData.parameters.prefix', 'draft');
    $lost = $child->snapshot;
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->assertHasNoErrors()->assertSet('sourceId', $b);
    $child->snapshot = $lost;
    $child->call('refreshReviewStatus', $child->instance()->reviewContext())->assertHasNoErrors()->assertSet('recipeData.source_choice', 'source:'.$a)->assertSet('recipeData.parameters.prefix', 'draft');
    expect($child->get('sourceId'))->toBe($a)->and($batch->fresh()->getAttributes())->toBe($before);
    $child->assertSee('Source selection restored to the confirmed opened source');
    $child->set('recipeData.source_choice', 'source:'.$b)->call('chooseReviewSource', $child->instance()->reviewContext())->assertHasNoErrors();
    $child->call('refreshReviewStatus', $child->instance()->reviewContext())->assertHasNoErrors()->assertSet('sourceId', $b)->assertSet('recipeData.source_choice', 'source:'.$b)->assertSet('recipeData.parameters.prefix', 'draft');
    expect($batch->fresh()->getAttributes())->toBe($before);
});

test('first review Back and Return reopen the same journal and cursor without new work', function () {
    $source = workflowReviewSource();
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $page = Livewire::test(CatalogImages::class)->set('selectedTableRecords', [$p->id]);
    $page->call('openImageReview', 'folder_matches', $page->instance()->imageBrowserContext())->assertHasNoErrors();
    $launch = $page->get('imageReviewLaunch');
    $host = $page->instance()->getId();
    $props = ['launchToken' => $launch['token'], 'launchSession' => $launch['session'], 'operation' => 'folder_matches', 'parentHost' => $host];
    $child = Livewire::test(AssetAssignment::class, $props)->set('recipeData.source_choice', 'source:'.$source);
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->call('previewReview', $child->instance()->reviewContext())->assertHasNoErrors();
    workflowReviewFinish($child);
    $batch = CatalogAssetBatch::findOrFail($launch['session']);
    $before = $batch->fresh()->getAttributes();
    $items = $batch->items()->get()->map->getAttributes()->all();
    $child->call('backToRecords', $child->instance()->reviewContext())->assertDispatched('catalog-image-review-closed', session: $launch['session'], host: $host);
    $page->call('closeImageReview', $launch['session'], $host)->assertHasNoErrors()->assertSet('imageReviewLaunch', null);
    $page->call('returnToImageReview', $launch['session'], $page->instance()->imageBrowserContext())->assertHasNoErrors();
    expect($page->get('imageReviewLaunch')['session'])->toBe($launch['session']);
    $returned = Livewire::test(AssetAssignment::class, $props)->assertSet('reviewProgress.classified', 1);
    expect($batch->fresh()->getAttributes())->toBe($before)->and($batch->items()->get()->map->getAttributes()->all())->toBe($items)->and(CatalogAssetBatch::count())->toBe(1)->and(CatalogAsset::count())->toBe(0);
});

test('read only review refresh includes Apply controls but never prepares or accepts a subset', function () {
    $source = workflowReviewSource();
    $p = Product::factory()->create(['profile_fields' => ['product_pic' => 'a']]);
    $scope = app(ReviewCatalogAssets::class)->freeze(auth()->user(), 'product.main_image', [], [$p->id], [['column' => 'products.id', 'direction' => 'asc']]);
    $child = Livewire::test(AssetAssignment::class, ['launchToken' => $scope['token'], 'launchSession' => $scope['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'test-parent'])->set('recipeData.source_choice', 'source:'.$source);
    $child->call('chooseReviewSource', $child->instance()->reviewContext())->call('previewReview', $child->instance()->reviewContext());
    workflowReviewFinish($child);
    $child->call('refreshReviewStatus', $child->instance()->reviewContext())->assertHasNoErrors()->assertSee('This Apply')->assertSee('Apply checked images');
    $batch = CatalogAssetBatch::findOrFail($scope['review_id']);
    expect($batch->pending_apply_intent)->toBeNull()->and($batch->active_acceptance_id)->toBeNull()->and($batch->items()->whereNotNull('accepted_at')->count())->toBe(0)->and($p->fresh()->main_image_id)->toBeNull();
});
