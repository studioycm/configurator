<?php

use App\Filament\Pages\CatalogImages;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetBatch;
use App\Models\Group;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetSources;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

beforeEach(function () {
    config(['catalog-assets.enabled' => true, 'cache.default' => 'array']);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

function workspaceImageProduct(Product $product): Testable
{
    $page = Livewire::test(CatalogImages::class);

    return $page->call('inspectProductImage', $product->id, $page->instance()->imageBrowserContext());
}

function workspaceImageExpected(Testable $page): array
{
    return $page->instance()->workspaceImageEditorContext();
}

function workspaceImageSource(): void
{
    $disk = catalogImageTestDisk('workspace-image-fixture');
    $image = imagecreatetruecolor(80, 40);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    $disk->put('synthetic.png', $bytes);
    config(['catalog-assets.sources.fixture' => ['label' => 'Synthetic fixture', 'path' => $disk->path(''), 'read_only' => true]]);
}

function workspaceImageChoose(Testable $page): void
{
    $page->call('catalogMainImageChooseSource', 'alias:fixture');
    for ($step = 0; $step < 12 && $page->get('catalogMainImageSourceProgress')['stage'] !== 'ready'; $step++) {
        $page->call('catalogMainImageAdvanceSource');
    }
    $progress = $page->get('catalogMainImageSourceProgress');
    expect($progress['stage'])->toBe('ready');
    $entry = app(CatalogAssetSources::class)->manifest(auth()->user(), $progress['source_id'], $progress['scan_version'])['entries'][0];
    $page->call('catalogMainImageSelectCandidate', $entry['entry_id'])->assertSet('data.image_intent.operation', 'set');
}

test('opening the image editor fills only image data and inspecting the same owner keeps its draft session', function () {
    $product = Product::factory()->create();
    $page = workspaceImageProduct($product)->assertSet('data.image_intent.operation', 'keep')->assertSee('Save image');
    expect(array_keys($page->get('data')))->toBe(['image_intent', 'image_upload']);
    $session = $page->get('imageEditorSession');
    $page->call('catalogMainImageRemove')->call('inspectProductImage', $product->id, $page->instance()->imageBrowserContext())
        ->assertSet('imageEditorSession', $session)->assertSet('data.image_intent.operation', 'remove');
});

test('dirty record transitions stay until an explicit choice while browsing preserves the draft', function () {
    $a = Product::factory()->create(['product_code' => 'A-001']);
    $b = Product::factory()->create(['product_code' => 'B-002']);
    $page = workspaceImageProduct($a)->call('catalogMainImageRemove');
    $session = $page->get('imageEditorSession');
    $page->set('imageSearch.query', 'B')->call('setImagePageSize', '7', $page->instance()->imageBrowserContext())
        ->assertSet('imageRecordId', $a->id)->assertSet('imageEditorSession', $session)->assertSet('data.image_intent.operation', 'remove');
    $page->call('inspectProductImage', $b->id, $page->instance()->imageBrowserContext())->assertSet('imageRecordId', $a->id)->assertSee('Save & continue')->assertSee('before opening B-002');
    $token = $page->get('imagePendingTransition')['token'];
    $page->call('resolveWorkspaceImageTransition', 'stay', $token, workspaceImageExpected($page))->assertSet('imagePendingTransition', null)->assertSet('data.image_intent.operation', 'remove');
    $page->call('inspectProductImage', $b->id, $page->instance()->imageBrowserContext());
    $page->call('resolveWorkspaceImageTransition', 'discard', $page->get('imagePendingTransition')['token'], workspaceImageExpected($page))
        ->assertSet('imageRecordId', $b->id)->assertSet('data.image_intent.operation', 'keep');
});

test('only a prepared image-only draft can save and forged ordinary fields are never persisted', function () {
    $product = Product::factory()->create(['product_name' => 'Protected name']);
    $page = workspaceImageProduct($product)->call('catalogMainImageRemove');
    $page->call('saveWorkspaceImage', workspaceImageExpected($page))->assertHasErrors('imageEditor');
    $page->set('data.product_name', 'Overwrite')->call('prepareWorkspaceImageSave', workspaceImageExpected($page))->assertHasErrors('data');
    expect($product->fresh()->product_name)->toBe('Protected name')->and($product->fresh()->main_image_id)->toBeNull();
});

test('image-only source preparation and final commit preserve owner metadata and shared Group state', function () {
    workspaceImageSource();
    $group = Group::factory()->create(['catalog_attributes' => ['application' => ['0', '001']], 'description' => 'Keep me']);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['product_pic' => '/ari/picture', 'pressure' => ['0', '001']], 'parts' => [['code' => 'keep']]])->refresh();
    $before = $product->getAttributes();
    $groupBefore = $group->fresh()->getAttributes();
    $page = workspaceImageProduct($product);
    workspaceImageChoose($page);
    expect(DB::transactionLevel())->toBe(0);
    $page->call('prepareWorkspaceImageSave', workspaceImageExpected($page))->assertHasNoErrors();
    expect($product->fresh()->main_image_id)->toBeNull()->and(CatalogAsset::query()->count())->toBe(1);
    $page->call('saveWorkspaceImage', workspaceImageExpected($page))->assertHasNoErrors()->assertSet('data.image_intent.operation', 'keep');
    expect($product->fresh()->main_image_id)->toBe(CatalogAsset::query()->sole()->id)
        ->and(array_diff_key($product->fresh()->getAttributes(), array_flip(['main_image_id', 'updated_at'])))->toBe(array_diff_key($before, array_flip(['main_image_id', 'updated_at'])))
        ->and(array_diff_key($group->fresh()->getAttributes(), array_flip(['catalog_revision', 'updated_at'])))->toBe(array_diff_key($groupBefore, array_flip(['catalog_revision', 'updated_at'])));
});

test('saving a Missing image result continues to the frozen next record and does not skip to a new boundary', function () {
    workspaceImageSource();
    $group = Group::factory()->create();
    $a = Product::factory()->for($group)->create(['product_code' => 'A-001']);
    $b = Product::factory()->for($group)->create(['product_code' => 'B-002']);
    Product::factory()->for($group)->create(['product_code' => 'C-003']);
    $page = workspaceImageProduct($a)->set('imageSearch.image_state', 'missing');
    workspaceImageChoose($page);
    $page->call('navigateImageRecord', 'next', $page->instance()->imageBrowserContext())->assertSet('imageRecordId', $a->id);
    $token = $page->get('imagePendingTransition')['token'];
    expect($page->get('imagePendingTransition')['destination']['record'])->toBe($b->id);
    $page->call('prepareWorkspaceImageSave', workspaceImageExpected($page), $token)->assertHasNoErrors();
    $page->call('saveWorkspaceImage', workspaceImageExpected($page), $token)->assertHasNoErrors()
        ->assertSet('imageRecordId', $b->id)->assertSet('imagePendingTransition', null)->assertSet('data.image_intent.operation', 'keep');
    expect($a->fresh()->main_image_id)->not->toBeNull();
});

test('lost committed response reconciliation continues only the original destination and repeated recovery never advances again', function () {
    workspaceImageSource();
    $a = Product::factory()->create(['product_code' => 'A-001']);
    $b = Product::factory()->create(['product_code' => 'B-002']);
    Product::factory()->create(['product_code' => 'C-003']);
    $page = workspaceImageProduct($a);
    workspaceImageChoose($page);
    $page->call('navigateImageRecord', 'next', $page->instance()->imageBrowserContext());
    $token = $page->get('imagePendingTransition')['token'];
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected, $token)->assertHasNoErrors();
    $beforeResponse = $page->snapshot;
    $page->call('saveWorkspaceImage', $expected, $token)->assertHasNoErrors()->assertSet('imageRecordId', $b->id);
    $saved = $a->fresh()->getAttributes();
    for ($retry = 0; $retry < 2; $retry++) {
        $page->snapshot = $beforeResponse;
        $page->call('reconcileWorkspaceImageSave', $expected, $token)->assertHasNoErrors()->assertSet('imageRecordId', $b->id);
        expect($page->effects['returns'][0]['outcome'])->toBe('goal_matches');
    }
    expect($a->fresh()->getAttributes())->toBe($saved);
});

test('baseline or conflicting image state after an uncertain Save stays on the draft without replay', function () {
    workspaceImageSource();
    $a = Product::factory()->create();
    $page = workspaceImageProduct($a);
    workspaceImageChoose($page);
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected)->call('reconcileWorkspaceImageSave', $expected)
        ->assertSet('imageRecordId', $a->id)->assertSet('data.image_intent.operation', 'set');
    expect($page->effects['returns'][0]['outcome'])->toBe('baseline_matches')->and($a->fresh()->main_image_id)->toBeNull();
    $a->update(['product_name' => 'Changed independently']);
    $page->call('reconcileWorkspaceImageSave', $expected)->assertSet('data.image_intent.operation', 'set');
    expect($page->effects['returns'][0]['outcome'])->toBe('conflict')->and($a->fresh()->product_name)->toBe('Changed independently');
});

test('removed pending destination saves the current image and stays clean without selecting another record', function () {
    workspaceImageSource();
    $a = Product::factory()->create(['product_code' => 'A-001']);
    $b = Product::factory()->create(['product_code' => 'B-002']);
    $page = workspaceImageProduct($a);
    workspaceImageChoose($page);
    $page->call('navigateImageRecord', 'next', $page->instance()->imageBrowserContext());
    $token = $page->get('imagePendingTransition')['token'];
    $page->call('prepareWorkspaceImageSave', workspaceImageExpected($page), $token);
    $b->delete();
    $page->call('saveWorkspaceImage', workspaceImageExpected($page), $token)->assertHasNoErrors()
        ->assertSet('imageRecordId', $a->id)->assertSet('data.image_intent.operation', 'keep')->assertSee('requested destination changed');
    expect($a->fresh()->main_image_id)->not->toBeNull();
});

test('changed pending meaning rejects Save and Stay can recover without discarding the image choice', function () {
    $a = Product::factory()->create(['product_code' => 'A-001']);
    $b = Product::factory()->create(['product_code' => 'B-002']);
    $page = workspaceImageProduct($a)->call('catalogMainImageRemove')->call('inspectProductImage', $b->id, ['target' => 'product.main_image', 'mode' => 'results', 'record' => $a->id, 'revision' => 1]);
    $token = $page->get('imagePendingTransition')['token'];
    $page->set('imageSearch.query', 'changed')->call('prepareWorkspaceImageSave', workspaceImageExpected($page), $token)->assertHasErrors('imageEditor')
        ->call('resolveWorkspaceImageTransition', 'stay', $token, workspaceImageExpected($page))->assertSet('imagePendingTransition', null)->assertSet('data.image_intent.operation', 'remove');
});

test('failed final owner transaction rolls back the pointer and never consumes the pending destination', function () {
    workspaceImageSource();
    $a = Product::factory()->create(['product_code' => 'A-001']);
    $b = Product::factory()->create(['product_code' => 'B-002']);
    $page = workspaceImageProduct($a);
    workspaceImageChoose($page);
    $page->call('navigateImageRecord', 'next', $page->instance()->imageBrowserContext());
    $token = $page->get('imagePendingTransition')['token'];
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected, $token)->assertHasNoErrors();
    Product::updated(function (Product $product) use ($a): void {
        if ($product->id === $a->id) {
            throw new RuntimeException('Owned rollback fixture');
        }
    });
    expect(fn () => $page->call('saveWorkspaceImage', $expected, $token))->toThrow(RuntimeException::class, 'Owned rollback fixture');
    expect($a->fresh()->main_image_id)->toBeNull()->and(DB::transactionLevel())->toBe(0);
    $page->assertSet('imageRecordId', $a->id)->assertSet('imagePendingTransition.token', $token)->assertSet('data.image_intent.operation', 'set');
});

test('Group image editing preserves attributes hierarchy settings and the native full-save recovery remains strict', function () {
    workspaceImageSource();
    $parent = Group::factory()->create();
    $group = Group::factory()->create(['parent_id' => $parent->id, 'catalog_attributes' => ['pressure' => ['0', '001']], 'description' => 'Keep Group description'])->refresh();
    $tag = GroupTagValue::factory()->create();
    $group->tagValues()->attach($tag->id, ['position' => 3]);
    $pivots = DB::table('group_tag_value')->where('group_id', $group->id)->get()->map(fn ($row) => (array) $row)->all();
    $before = $group->getAttributes();
    $page = Livewire::test(CatalogImages::class);
    $page->call('switchImageTarget', 'group.main_image', $page->instance()->imageBrowserContext())
        ->call('inspectGroupImage', $group->id, $page->instance()->imageBrowserContext());
    workspaceImageChoose($page);
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected)->call('saveWorkspaceImage', $expected)->assertHasNoErrors();
    expect(array_diff_key($group->fresh()->getAttributes(), array_flip(['main_image_id', 'updated_at', 'catalog_revision'])))->toBe(array_diff_key($before, array_flip(['main_image_id', 'updated_at', 'catalog_revision'])));
    expect(DB::table('group_tag_value')->where('group_id', $group->id)->get()->map(fn ($row) => (array) $row)->all())->toBe($pivots);
    $page->call('reconcileImageSave');
    expect($page->effects['returns'][0]['outcome'])->toBe('review_required');
});

test('a stale prepared owner or draft cannot overwrite newer metadata or a changed image choice', function () {
    $product = Product::factory()->create();
    $page = workspaceImageProduct($product)->call('catalogMainImageRemove');
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected)->assertHasNoErrors();
    $product->update(['product_name' => 'Keep newer metadata']);
    $page->call('saveWorkspaceImage', $expected)->assertHasErrors('record')->assertSet('data.image_intent.operation', 'remove');
    expect($product->fresh()->product_name)->toBe('Keep newer metadata')->and($product->fresh()->main_image_id)->toBeNull();
    $page->call('catalogMainImageRevert')->call('catalogMainImageRemove');
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected)->call('catalogMainImageRemove')
        ->call('saveWorkspaceImage', $expected)->assertHasErrors('imageEditor');
});

test('prepared receipt expiry and actor changes do not assign an image', function () {
    $product = Product::factory()->create();
    $page = workspaceImageProduct($product)->call('catalogMainImageRemove');
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected)->assertHasNoErrors();
    $this->travel(25)->hours();
    $page->call('saveWorkspaceImage', $expected)->assertHasErrors();
    $this->travelBack();
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    $page->call('saveWorkspaceImage', $expected)->assertHasErrors();
    expect($product->fresh()->main_image_id)->toBeNull();
});

test('image-only no-op saves do not advance revisions and an outer transaction cannot finalize', function () {
    $product = Product::factory()->create()->refresh();
    $before = $product->getAttributes();
    $groupBefore = $product->group->fresh()->getAttributes();
    $page = workspaceImageProduct($product)->call('catalogMainImageRemove');
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected)->call('saveWorkspaceImage', $expected)->assertHasNoErrors();
    expect($product->fresh()->getAttributes())->toBe($before)->and($product->group->fresh()->getAttributes())->toBe($groupBefore);
    $page->call('catalogMainImageRemove');
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected);
    DB::beginTransaction();
    try {
        expect(fn () => $page->call('saveWorkspaceImage', $expected))->toThrow(LogicException::class, 'own their commit');
    } finally {
        DB::rollBack();
    }
});

test('dirty target switch is guarded and discarded callbacks cannot cross overlapping Product and Group IDs', function () {
    $group = Group::factory()->create(['id' => 40]);
    $product = Product::factory()->for($group)->create(['id' => 40]);
    $page = workspaceImageProduct($product)->call('catalogMainImageRemove');
    $old = workspaceImageExpected($page);
    $page->call('switchImageTarget', 'group.main_image', $page->instance()->imageBrowserContext())->assertSet('imageTarget', 'product.main_image');
    $page->call('resolveWorkspaceImageTransition', 'discard', $page->get('imagePendingTransition')['token'], $old)->assertSet('imageTarget', 'group.main_image')->assertSet('imageRecordId', null);
    $page->call('inspectGroupImage', $group->id, $page->instance()->imageBrowserContext())->call('prepareWorkspaceImageSave', $old)->assertHasErrors('imageEditor');
    expect($group->fresh()->main_image_id)->toBeNull()->and($product->fresh()->main_image_id)->toBeNull();
});

test('image commands reauthorize the live feature and manager gate before preparation', function () {
    $product = Product::factory()->create();
    $page = workspaceImageProduct($product);
    $expected = workspaceImageExpected($page);
    config(['catalog-assets.enabled' => false]);
    $page->call('prepareWorkspaceImageSave', $expected)->assertForbidden();
    config(['catalog-assets.enabled' => true]);
    $this->actingAs(User::factory()->create(['email' => 'viewer@example.test']));
    Livewire::test(CatalogImages::class)->assertForbidden();
    expect($product->fresh()->main_image_id)->toBeNull()->and(CatalogAsset::query()->count())->toBe(0);
});

test('the dedicated Images host accepts the native upload and assigns only after its own final Save', function () {
    $product = Product::factory()->create(['product_name' => 'Keep ordinary data']);
    $page = workspaceImageProduct($product);
    $property = 'data.image_upload.'.Str::uuid();
    $file = UploadedFile::fake()->image('workspace.png', 80, 40);
    $page->call('_startUpload', $property, [['name' => 'workspace.png', 'size' => $file->getSize(), 'type' => $file->getMimeType()]], false);
    $binding = $page->get('catalogMainImageUploadBinding');
    $url = URL::temporarySignedRoute('catalog.images.native-upload', now()->addMinutes(5), ['nonce' => $binding['nonce']], false);
    $response = $this->post($url, ['files' => [$file]])->assertOk();
    $page->call('_finishUpload', $property, [$response->json('paths.0')], false)->assertSuccessful()->assertHasNoErrors();
    for ($step = 0; $step < 12 && $page->get('catalogMainImageSourceProgress.stage') !== 'ready'; $step++) {
        $page->call('catalogMainImageAdvanceSource')->assertHasNoErrors();
    }
    $page->assertSet('data.image_intent.operation', 'set');
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected)->assertHasNoErrors();
    expect($product->fresh()->main_image_id)->toBeNull();
    $page->call('saveWorkspaceImage', $expected)->assertHasNoErrors();
    expect($product->fresh()->main_image_id)->toBe(CatalogAsset::sole()->id)
        ->and($product->fresh()->product_name)->toBe('Keep ordinary data');
});

test('rendered workspace Save and leave controls execute the exact inline image intent', function () {
    $a = Product::factory()->create(['product_code' => 'A-001']);
    $b = Product::factory()->create(['product_code' => 'B-002']);
    $page = workspaceImageProduct($a);
    $bindings = function () use ($page): DOMXPath {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$page->html());

        return new DOMXPath($document);
    };
    $run = function (string $binding): array {
        $javascript = <<<'JS'
const calls = [];
new Function('save', 'choose', process.argv[1])((...args) => calls.push(['save', ...args]), (...args) => calls.push(['choose', ...args]));
process.stdout.write(JSON.stringify(calls[0]));
JS;
        $process = new Process(['node', '-e', $javascript, $binding]);
        $process->run();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    };
    $form = $bindings()->query('//form[@class="catalog-images-edit-form"]')->item(0);
    expect($run($form->getAttribute('x-on:submit.prevent')))->toBe(['save']);
    $page->call('catalogMainImageRemove')->call('inspectProductImage', $b->id, $page->instance()->imageBrowserContext());
    $token = $page->get('imagePendingTransition.token');
    $intents = [];
    foreach ($bindings()->query('//section[@class="catalog-images-leave-guard"]//button') as $button) {
        $intents[] = $run($button->getAttribute('x-on:click'));
    }
    expect($intents)->toBe([['choose', 'stay'], ['choose', 'discard'], ['save', $token]]);
    $page->call('resolveWorkspaceImageTransition', $intents[0][1], $token, workspaceImageExpected($page))->assertSet('imagePendingTransition', null);
});

test('a deleted current owner keeps local Stay and Discard available without creating or saving a record', function () {
    $a = Product::factory()->create(['product_code' => 'A-001']);
    $b = Product::factory()->create(['product_code' => 'B-002']);
    $page = workspaceImageProduct($a)->call('catalogMainImageRemove');
    $page->call('inspectProductImage', $b->id, $page->instance()->imageBrowserContext());
    $expected = workspaceImageExpected($page);
    $token = $page->get('imagePendingTransition.token');
    $a->delete();
    $page->call('$refresh')->assertSeeHtml('catalog-images-leave-guard');
    $page->call('resolveWorkspaceImageTransition', 'stay', $token, $expected)->assertSet('data.image_intent.operation', 'remove');
    $page->call('inspectProductImage', $b->id, $page->instance()->imageBrowserContext());
    $page->call('resolveWorkspaceImageTransition', 'discard', $page->get('imagePendingTransition.token'), $expected)
        ->assertSet('imageRecordId', $b->id)->assertSet('data.image_intent.operation', 'keep');
    expect(Product::whereKey($a->id)->exists())->toBeFalse()->and($b->fresh()->main_image_id)->toBeNull()->and(CatalogAsset::count())->toBe(0);
});

test('opening a checked image review uses the existing dirty guard and invalidates changed checked meaning', function () {
    $a = Product::factory()->create(['product_code' => 'REVIEW-A']);
    $b = Product::factory()->create(['product_code' => 'REVIEW-B']);
    $page = workspaceImageProduct($a)->call('catalogMainImageRemove')->set('selectedTableRecords', [$a->id, $b->id]);
    $page->call('openImageReview', 'folder_matches', $page->instance()->imageBrowserContext())->assertSet('imageReviewLaunch', null)->assertSee(__('catalog-images.match_folder').' · 2');
    $pending = $page->get('imagePendingTransition');
    $page->call('resolveWorkspaceImageTransition', 'stay', $pending['token'], workspaceImageExpected($page))->assertSet('imageReviewLaunch', null)->assertSet('data.image_intent.operation', 'remove');
    $page->call('openImageReview', 'folder_matches', $page->instance()->imageBrowserContext());
    $pending = $page->get('imagePendingTransition');
    $page->set('selectedTableRecords', [$a->id])->call('resolveWorkspaceImageTransition', 'discard', $pending['token'], workspaceImageExpected($page))->assertSet('imageReviewLaunch', null);
    $page->call('openImageReview', 'folder_matches', $page->instance()->imageBrowserContext());
    if ($page->get('imagePendingTransition') !== null) {
        $page->call('resolveWorkspaceImageTransition', 'discard', $page->get('imagePendingTransition')['token'], workspaceImageExpected($page))->assertHasNoErrors();
    }
    $launch = $page->get('imageReviewLaunch');
    expect($launch['count'])->toBe(1)->and($page->get('imageRecordId'))->toBe($a->id)->and($page->get('selectedTableRecords'))->toBe([$a->id]);
    $page->call('closeImageReview', $launch['session'], $page->instance()->getId())->assertSet('imageReviewLaunch', null)->assertSet('imageRecordId', $a->id);
});

test('Save and continue opens the frozen review only after the existing individual image save is confirmed', function () {
    workspaceImageSource();
    $p = Product::factory()->create();
    $page = workspaceImageProduct($p);
    workspaceImageChoose($page);
    $page->set('selectedTableRecords', [$p->id])->call('openImageReview', 'folder_matches', $page->instance()->imageBrowserContext())->assertSet('imageReviewLaunch', null);
    $token = $page->get('imagePendingTransition')['token'];
    $expected = workspaceImageExpected($page);
    $page->call('prepareWorkspaceImageSave', $expected, $token)->assertHasNoErrors()->assertSet('imageReviewLaunch', null);
    $page->call('saveWorkspaceImage', $expected, $token)->assertHasNoErrors();
    expect($page->get('imageReviewLaunch')['count'])->toBe(1)->and($p->fresh()->main_image_id)->not->toBeNull()->and(CatalogAssetBatch::count())->toBe(0);
});
