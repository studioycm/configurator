<?php

use App\Actions\ReviewCatalogAssets;
use App\Livewire\Catalog\AssetAssignment;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetImportSource;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetIntake;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    config(['catalog-assets.enabled' => true, 'cache.default' => 'array']);
    catalogImageTestDisk('tmp-for-tests');
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

/** @return Testable */
function intakeWorkflowComponent(Product $product): mixed
{
    $launch = app(ReviewCatalogAssets::class)->freeze(auth()->user(), 'product.main_image', [], [$product->id], [['column' => 'products.id', 'direction' => 'asc']]);

    return Livewire::test(AssetAssignment::class, ['launchToken' => $launch['token'], 'launchSession' => $launch['review_id'], 'operation' => 'folder_matches', 'parentHost' => 'intake-parent']);
}

test('native intake component copies finishes scans and previews without assigning a Product', function () {
    $product = Product::factory()->create(['product_code' => 'VALVE', 'profile_fields' => ['product_pic' => 'valve']]);
    $before = $product->fresh()->getRawOriginal();
    $file = UploadedFile::fake()->image('valve.png', 40, 20);
    $component = intakeWorkflowComponent($product);
    $component->call('issueReviewUpload', [['name' => 'valve.png', 'relative_path' => 'folder/valve.png', 'bytes' => $file->getSize()]], 'Folder upload', $component->instance()->reviewContext())->assertHasNoErrors();
    $issued = $component->effects['returns'][0];
    $this->assertDatabaseCount('catalog_asset_import_sources', 0);

    $component->call('beginReviewUpload', $issued['intake_token'], $component->instance()->reviewContext())->assertHasNoErrors();
    $component->assertSet('sourceId', $issued['source_id'])->assertSet('recipeData.source_choice', 'source:'.$issued['source_id']);
    $component->call('advanceReviewSource', $component->instance()->reviewContext(), $component->get('sourceProgress')['revision'])->assertHasNoErrors()->assertSet('sourceProgress.stage', 'uploading');
    $component->call('issueReviewUploadEntry', 0, $component->instance()->reviewContext())->assertHasNoErrors();
    $entry = $component->effects['returns'][0];
    $this->post($entry['url'], ['launch_token' => $component->get('launchToken'), 'files' => [$file]], ['Accept' => 'application/json'])->assertJsonPath('current_entry.committed', true);
    $component->call('refreshReviewUpload', $entry['entry_id'], $component->instance()->reviewContext())->assertHasNoErrors()->assertSet('intakeProgress.file_count', 1);
    $component->call('finishReviewUpload', $component->get('intakeProgress')['revision'], $component->instance()->reviewContext())->assertHasNoErrors();
    for ($i = 0; $i < 4 && $component->get('sourceProgress')['stage'] !== 'ready'; $i++) {
        $component->call('advanceReviewSource', $component->instance()->reviewContext(), $component->get('sourceProgress')['revision'])->assertHasNoErrors();
    }
    $component->assertSet('sourceProgress.stage', 'ready');
    $component->set('recipeData.parameters.include_subfolders', true);
    $component->call('previewReview', $component->instance()->reviewContext())->assertHasNoErrors();
    for ($i = 0; $i < 4 && $component->get('reviewProgress')['stage'] === 'building'; $i++) {
        $component->call('buildReview', $component->instance()->reviewContext(), $component->get('reviewProgress')['revision'])->assertHasNoErrors();
    }

    expect($component->get('reviewProgress')['counts']['ready'])->toBe(1);
    expect($product->fresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('catalog_assets', 0);
    $this->assertDatabaseCount('media', 0);
    expect(json_encode($component->snapshot))->not->toContain('binding.json', 'reference_hash', 'intake_token', 'private_path');
});

test('stale component intake commands are rejected before source creation', function () {
    $component = intakeWorkflowComponent(Product::factory()->create());
    $context = $component->instance()->reviewContext();
    $context['session'] = 'stale-session';

    $component->call('issueReviewUpload', [['name' => 'a.png', 'relative_path' => 'a.png', 'bytes' => 100]], 'Stale selection', $context)->assertHasErrors('review');

    $this->assertDatabaseCount('catalog_asset_import_sources', 0);
});

test('a later same context launch can finish an owned fully copied intake without browser File handles', function () {
    $first = intakeWorkflowComponent(Product::factory()->create());
    $file = UploadedFile::fake()->image('saved.png', 40, 20);
    $service = app(CatalogAssetIntake::class);
    $issued = $service->issue(auth()->user(), $first->get('launchToken'), 'Saved copies', [['name' => 'saved.png', 'relative_path' => 'saved.png', 'bytes' => $file->getSize()]]);
    $service->begin(auth()->user(), $first->get('launchToken'), $issued['intake_token']);
    $entry = $service->entry(auth()->user(), $first->get('launchToken'), $issued['source_id'], 0);
    $service->transfer(auth()->user(), $first->get('launchToken'), $entry['token'], $file);
    $component = intakeWorkflowComponent(Product::factory()->create());

    $component->set('recipeData.source_choice', 'source:'.$issued['source_id'])->call('chooseReviewSource', $component->instance()->reviewContext())->assertHasNoErrors()
        ->assertSet('intakeProgress.declared_files', 1)->assertSet('intakeProgress.file_count', 1);
    $component->call('finishReviewUpload', $component->get('intakeProgress')['revision'], $component->instance()->reviewContext())->assertHasNoErrors()->assertSet('sourceProgress.stage', 'scanning');

    expect(CatalogAssetImportSource::findOrFail($issued['source_id'])->uploads_sealed)->toBeTrue();
    expect(CatalogAsset::count())->toBe(0);
});
