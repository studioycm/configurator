<?php

use App\Filament\Resources\Groups\Pages\CreateGroup;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetImportSource;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetDisplay;
use App\Services\CatalogAssetSourceFiles;
use App\Services\CatalogAssetSources;
use App\Services\CatalogCards;
use App\Services\CatalogNavigation;
use App\Services\CatalogSnapshots;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Events\RecordCreated;
use Filament\Support\Exceptions\Halt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    config(['catalog-assets.enabled' => true]);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('native product editors expose a staged main image without changing ordinary drafts', function () {
    $product = Product::factory()->create();
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])
        ->assertSee('Main image')->assertSet('data.image_intent.operation', 'keep');
    $originalIntent = $editor->get('data.image_intent');
    $editor->fillForm(['product_name' => 'An unfinished product name'])
        ->call('catalogMainImageRemove')->assertSet('data.image_intent.operation', 'remove')
        ->assertSet('data.product_name', 'An unfinished product name')
        ->call('catalogMainImageRevert')->assertSet('data.image_intent.operation', 'keep')
        ->assertSet('data.product_name', 'An unfinished product name');

    expect($editor->get('data.image_intent'))->toBe($originalIntent);
    expect($product->fresh()->main_image_id)->toBeNull();
    expect($editor->instance()->catalogMainImageHasChanges())->toBeFalse();
});

test('preparation validates the whole native draft and rejects unprepared direct saves', function () {
    $product = Product::factory()->create();
    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['product_code' => ''])
        ->call('prepareImageSave')->assertHasFormErrors(['product_code' => 'required']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['product_name' => 'Do not save me'])->call('catalogMainImageRemove')
        ->call('save')->assertHasErrors(['data.image_intent']);

    expect($product->fresh()->product_name)->toBe('Test product');
});

test('native keep preparation binds the complete draft and commits through the existing product writer', function () {
    $product = Product::factory()->create();
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['product_name' => 'Saved with image keep'])
        ->call('prepareImageSave')->assertHasNoFormErrors();
    expect($editor->get('catalogMainImageReceipt'))->toBeString()->not->toBe('');
    $editor->call('save')->assertHasNoFormErrors()->assertDispatched('catalog-record-saved');
    expect($product->fresh()->product_name)->toBe('Saved with image keep');
    expect($editor->get('catalogMainImageReceipt'))->toBeNull();
});

test('changing unrelated product fields invalidates a prepared image receipt', function () {
    $product = Product::factory()->create();
    Livewire::test(EditProduct::class, ['record' => $product->id])->call('catalogMainImageRemove')
        ->call('prepareImageSave')->assertHasNoFormErrors()
        ->fillForm(['product_name' => 'Changed after preparation'])
        ->call('save')->assertHasErrors(['data.image_intent']);
    expect($product->fresh()->product_name)->toBe('Test product');
});

test('group creation has a locked server identity and preserves embedded create behavior', function () {
    $editor = Livewire::test(CreateGroup::class, ['embedded' => true])
        ->assertSet('data.image_intent.operation', 'keep')
        ->fillForm(['name' => 'Image-aware new Group', 'sort_order' => 0])
        ->call('prepareImageSave')->assertHasNoFormErrors();
    expect(Str::isUlid($editor->get('catalogMainImageCreationSession')))->toBeTrue();
    $editor->call('create')->assertHasNoFormErrors()->assertNoRedirect()->assertDispatched('catalog-record-created');
    expect(Group::where('name', 'Image-aware new Group')->exists())->toBeTrue();
});

test('group settings are covered by the same image receipt hash', function () {
    $group = Group::factory()->create();
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id])->call('catalogMainImageRemove')
        ->call('prepareImageSave')->assertHasNoFormErrors();
    $editor->set('data.catalog_settings.result_settings.cards_per_row', 3)
        ->call('save')->assertHasErrors(['data.image_intent']);
    expect($group->fresh()->result_settings['cards_per_row'] ?? 4)->toBe(4);
});

/** @return array{0: Testable, 1: string, 2: string} */
function nativeImageUpload(Product|Group|null $product, string $filename = 'synthetic.png', string $pageClass = EditProduct::class): array
{
    $editor = Livewire::test($pageClass, $product === null ? [] : ['record' => $product->id]);
    $property = 'data.image_upload.'.Str::uuid();
    $file = UploadedFile::fake()->image($filename, 80, 40);
    $editor->call('_startUpload', $property, [['name' => $filename, 'size' => $file->getSize(), 'type' => $file->getMimeType()]], false);
    $binding = $editor->get('catalogMainImageUploadBinding');
    expect($binding)->toHaveKey('nonce');
    $url = URL::temporarySignedRoute('catalog.images.native-upload', now()->addMinutes(5), ['nonce' => $binding['nonce']], false);
    $response = test()->post($url, ['files' => [$file]])->assertOk();
    $ticket = session()->get('catalog_main_image_uploads.'.$binding['nonce']);
    expect($ticket['reference_hash'])->toBe(hash('sha256', $response->json('paths.0')));
    expect($ticket['host_id'])->toBe($editor->instance()->getId());

    return [$editor, $property, $response->json('paths.0')];
}

test('native image upload preserves its complete initialization handler in rendered HTML', function (string $pageClass) {
    $record = match ($pageClass) {
        EditProduct::class => Product::factory()->create(),
        EditGroup::class => Group::factory()->create(),
        default => null,
    };
    $editor = Livewire::test($pageClass, $record === null ? [] : ['record' => $record->id]);
    $field = $editor->instance()->form->getComponent(fn ($component): bool => $component instanceof FileUpload && $component->getStatePath() === 'data.image_upload');
    $configuredHandler = $field->getExtraAlpineAttributes()['x-init'];
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$editor->html());
    $uploads = (new DOMXPath($document))->query('//*[contains(@*[name()="x-data"], "fileUploadFormComponent(")]');

    expect($uploads)->toHaveCount(1);
    expect($configuredHandler)->toContain('FilePond:init', '3145728');
    expect($uploads->item(0)->getAttribute('x-init'))->toBe($configuredHandler);
    expect($document->textContent)->not->toContain('detail.pond.setOptions');
})->with([EditProduct::class, EditGroup::class, CreateGroup::class]);

test('native upload keeps the exact three MiB byte boundary', function (int $extra) {
    $product = Product::factory()->create();
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id]);
    $property = 'data.image_upload.'.Str::uuid();
    $image = UploadedFile::fake()->image('small.png', 80, 40);
    $file = UploadedFile::fake()->createWithContent('boundary.png', str_pad(file_get_contents($image->getPathname()), 3145728 + $extra, "\0"));
    $editor->call('_startUpload', $property, [['name' => 'boundary.png', 'size' => $file->getSize(), 'type' => $file->getMimeType()]], false);
    $binding = $editor->get('catalogMainImageUploadBinding');
    $url = URL::temporarySignedRoute('catalog.images.native-upload', now()->addMinutes(5), ['nonce' => $binding['nonce']], false);

    $response = $this->withHeader('Accept', 'application/json')->post($url, ['files' => [$file]]);

    if ($extra === 0) {
        $response->assertOk()->assertJsonCount(1, 'paths');
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors(['files.0']);
    }
    expect($product->fresh()->main_image_id)->toBeNull();
    $this->assertDatabaseCount('catalog_assets', 0);
})->with(['exact bytes' => 0, 'one byte over' => 1]);

test('a valid reference uploaded by another actor cannot finish this native image upload', function () {
    $product = Product::factory()->create();
    [$first, $firstProperty, $firstReference] = nativeImageUpload($product);
    $first->call('_finishUpload', $firstProperty, [$firstReference], false)->assertSuccessful()->assertHasNoErrors();
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    [$second, $secondProperty] = nativeImageUpload($product, 'second.png');
    $second->call('_finishUpload', $secondProperty, [$firstReference], false)->assertForbidden();
});

test('a native upload scans and stages before the full product Save copies and assigns it once', function () {
    catalogImageTestDisk('catalog-asset-imports');
    catalogImageTestDisk('catalog-assets');
    $product = Product::factory()->create();
    [$editor, $property, $reference] = nativeImageUpload($product);
    $editor->call('_finishUpload', $property, [$reference], false);
    $editor->assertSuccessful()->assertHasNoErrors()
        ->assertDispatched('catalog-image-source-started');
    expect($editor->get('catalogMainImageSourceProgress.stage'))->toBe('scanning');
    for ($step = 0; $step < 20 && $editor->get('catalogMainImageSourceProgress.stage') === 'scanning'; $step++) {
        $editor->call('catalogMainImageAdvanceSource')->assertHasNoErrors();
    }
    expect($editor->get('catalogMainImageSourceProgress.stage'))->toBe('ready');
    $editor->assertSet('data.image_intent.operation', 'set')
        ->assertSet('catalogMainImageUploadBinding', null)
        ->fillForm(['product_name' => 'Native upload saved'])
        ->call('prepareImageSave')->assertHasNoErrors();
    expect(CatalogAsset::count())->toBe(1)->and($product->fresh()->main_image_id)->toBeNull();
    $editor->call('save')->assertHasNoErrors()->assertDispatched('catalog-image-save-committed');
    expect($product->fresh()->main_image_id)->toBe(CatalogAsset::sole()->id)
        ->and($product->fresh()->product_name)->toBe('Native upload saved');
});

test('native Group metadata rejection rolls back details and preserves the prepared image draft', function () {
    $group = Group::factory()->create(['name' => 'Original Group']);
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '10']]);
    Livewire::test(EditGroup::class, ['record' => $group->id])
        ->fillForm(['name' => 'Draft Group', 'catalog_settings.filters' => [
            ['id' => null, 'property_key' => 'Working_Pressure', 'label' => 'Pressure', 'values' => [['value' => 'stale', 'label' => 'Draft label']]],
        ]])
        ->call('catalogMainImageRemove')->call('prepareImageSave')->assertHasNoErrors()
        ->call('save')->assertHasErrors()->assertSet('data.name', 'Draft Group')->assertSet('data.image_intent.operation', 'remove')
        ->assertNotDispatched('catalog-record-saved')->assertNotDispatched('catalog-image-save-committed');
    expect($group->fresh()->name)->toBe('Original Group')->and($group->filters()->count())->toBe(0);
});

test('a native preparation exposes existing stale-record recovery without dropping its draft', function () {
    $product = Product::factory()->create();
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Keep my draft']);
    $product->update(['product_name' => 'Saved elsewhere']);
    $editor->call('prepareImageSave')->assertHasErrors(['data.image_intent'])
        ->assertSet('batchEditorStale', true)->assertSet('data.product_name', 'Keep my draft')
        ->call('reloadBatchEditor')->assertSet('data.product_name', 'Saved elsewhere')
        ->assertSet('data.image_intent.operation', 'keep')->assertSet('catalogMainImageReceipt', null)
        ->assertDispatched('catalog-image-draft-discarded');
});

test('image-aware Group create another preserves native parameters and issues a fresh creation session', function () {
    $editor = Livewire::test(CreateGroup::class)->fillForm(['name' => 'First new Group', 'sort_order' => 0])->call('prepareImageSave');
    $firstSession = $editor->get('catalogMainImageCreationSession');
    $editor->call('create', true)->assertHasNoErrors()->assertSet('data.image_intent.operation', 'keep')
        ->assertDispatched('catalog-editor-saved');
    expect($editor->get('catalogMainImageCreationSession'))->not->toBe($firstSession);
    expect(Group::where('name', 'First new Group')->count())->toBe(1);
});

test('the actual create-another button is registered for image preparation and saves one imaged Group', function () {
    [$editor, $property, $reference] = nativeImageUpload(null, 'group.png', CreateGroup::class);
    $editor->call('_finishUpload', $property, [$reference], false)->assertHasNoErrors();
    for ($step = 0; $step < 20 && $editor->get('catalogMainImageSourceProgress.stage') === 'scanning'; $step++) {
        $editor->call('catalogMainImageAdvanceSource')->assertHasNoErrors();
    }
    $editor->assertSet('catalogMainImageSourceProgress.code', null)->assertSet('catalogMainImageSourceProgress.stage', 'ready');
    $editor->assertSet('data.image_intent.operation', 'set');
    $session = $editor->get('catalogMainImageCreationSession');
    $imageIntent = $editor->get('data.image_intent');
    $editor->fillForm(['name' => null, 'sort_order' => 0])->call('prepareImageSave')
        ->assertHasFormErrors(['name' => 'required'])->assertNotDispatched('catalog-editor-saved')
        ->assertSet('catalogMainImageCreationSession', $session)->assertSet('data.image_intent', $imageIntent);
    $this->assertDatabaseCount('groups', 0);
    $editor->fillForm(['name' => 'Native wrapper Group', 'sort_order' => 0]);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$editor->html());
    $button = (new DOMXPath($document))->query('//*[@*[name()="wire:click"]="createAnother"]')->item(0);
    expect($button)->toBeInstanceOf(DOMElement::class);
    $method = $button->getAttribute('wire:click');
    expect($editor->instance()->catalogMainImageEditorConfig()['methods'])->toContain($method);

    $editor->call('prepareImageSave')->assertHasNoErrors()->call($method)->assertHasNoErrors()
        ->assertDispatched('catalog-editor-saved');

    $group = Group::where('name', 'Native wrapper Group')->sole();
    expect($group->main_image_id)->toBe(CatalogAsset::sole()->id);
    expect($editor->get('catalogMainImageCreationSession'))->not->toBe($session);
    expect($editor->get('data.name'))->not->toBe('Native wrapper Group');
    $editor->assertSet('data.image_intent.operation', 'keep')->assertSet('catalogMainImageReceipt', null);
});

test('an interrupted create-another keeps its image draft without resetting the saved baseline', function (string $fault) {
    [$editor, $property, $reference] = nativeImageUpload(null, 'retained-group.png', CreateGroup::class);
    $editor->call('_finishUpload', $property, [$reference], false)->assertHasNoErrors();
    for ($step = 0; $step < 20 && $editor->get('catalogMainImageSourceProgress.stage') === 'scanning'; $step++) {
        $editor->call('catalogMainImageAdvanceSource')->assertHasNoErrors();
    }
    $editor->assertSet('data.image_intent.operation', 'set')
        ->fillForm(['name' => 'Retained interrupted Group', 'sort_order' => 0])->call('prepareImageSave')->assertHasNoErrors();
    $page = $editor->instance();
    $session = $page->catalogMainImageCreationSession;
    $draft = $page->data;
    Event::listen(RecordCreated::class, function () use ($fault): void {
        throw $fault === 'halt' ? (new Halt)->rollBackDatabaseTransaction() : new RuntimeException('Synthetic interrupted creation');
    });

    try {
        if ($fault === 'halt') {
            $page->createAnother();
        } else {
            expect(fn () => $page->createAnother())->toThrow(RuntimeException::class, 'Synthetic interrupted creation');
        }
    } finally {
        Event::forget(RecordCreated::class);
    }

    expect(Group::where('name', 'Retained interrupted Group')->exists())->toBeFalse();
    expect($page->data)->toBe($draft);
    expect($page->catalogMainImageCreationSession)->toBe($session);
    expect(collect(\Livewire\store($page)->get('dispatched', []))->map(fn ($event) => $event->serialize()['name'])->all())
        ->not->toContain('catalog-editor-saved');
})->with(['exception', 'halt']);

test('single Group creation keeps its ordinary redirect without a create-another baseline reset', function () {
    Livewire::test(CreateGroup::class)->fillForm(['name' => 'Single new Group', 'sort_order' => 0])
        ->call('prepareImageSave')->assertHasNoErrors()->call('create')->assertHasNoErrors()
        ->assertRedirect()->assertNotDispatched('catalog-editor-saved');
    expect(Group::where('name', 'Single new Group')->count())->toBe(1);
});

test('embedded Group creation does not register or admit the native create-another wrapper', function () {
    $editor = Livewire::test(CreateGroup::class, ['embedded' => true])->fillForm(['name' => 'No embedded wrapper Group', 'sort_order' => 0]);
    expect($editor->instance()->catalogMainImageEditorConfig()['methods'])->not->toContain('createAnother');

    $editor->call('prepareImageSave')->call('createAnother')->assertHasErrors(['data.image_intent'])
        ->assertNotDispatched('catalog-editor-saved');

    expect(Group::where('name', 'No embedded wrapper Group')->exists())->toBeFalse();
});

test('an embedded image-aware Group cannot invoke create another directly', function () {
    Livewire::test(CreateGroup::class, ['embedded' => true])->fillForm(['name' => 'Do not create', 'sort_order' => 0])
        ->call('prepareImageSave')->call('create', true)->assertHasErrors(['data.image_intent'])
        ->assertNotDispatched('catalog-editor-saved');
    expect(Group::where('name', 'Do not create')->exists())->toBeFalse();
});

test('an image upload in flight is dirty before its temporary file arrives and can be reverted', function () {
    $product = Product::factory()->create();
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id]);
    $baseline = $editor->get('data.image_intent');
    $editor->call('_startUpload', 'data.image_upload.'.Str::uuid(), [['name' => 'waiting.png', 'size' => 100, 'type' => 'image/png']], false)
        ->assertSet('data.image_upload', []);
    expect($editor->instance()->catalogMainImageHasChanges())->toBeTrue();
    $editor->call('prepareImageSave')->assertHasErrors(['data.image_intent'])->call('catalogMainImageRevert');
    expect($editor->instance()->catalogMainImageHasChanges())->toBeFalse()->and($editor->get('data.image_intent'))->toBe($baseline);
});

test('eligible image sources remain listed after newer sources from other targets and contexts', function (bool $scoped, string $fault) {
    config(['catalog-assets.sources' => []]);
    $product = Product::factory()->create();
    $foreignGroup = Group::factory()->create();
    $context = $scoped ? ['list_key' => 'group-products', 'parent_id' => $product->group_id] : [];
    $eligible = CatalogAssetImportSource::factory()->create(['actor_id' => auth()->id(), 'status' => 'ready',
        'source' => ['label' => 'Eligible older source', 'target_key' => 'product.main_image', 'owner_context' => $context],
        'created_at' => now()->subDay()]);
    $foreignIds = [];
    for ($index = 0; $index < 51; $index++) {
        $sourceContext = match ($fault) {
            'target' => $context,
            'context' => ['list_key' => 'group-products', 'parent_id' => $foreignGroup->id],
            'extra' => [...$context, 'unexpected' => true],
        };
        $foreignIds[] = CatalogAssetImportSource::factory()->create(['actor_id' => auth()->id(), 'status' => 'ready',
            'source' => ['label' => 'Ineligible newer source', 'target_key' => $fault === 'target' ? 'group.main_image' : 'product.main_image', 'owner_context' => $sourceContext],
            'created_at' => now()])->id;
    }
    $arguments = ['record' => $product->id, ...($scoped ? ['listKey' => 'group-products', 'parentId' => $product->group_id] : [])];
    $options = Livewire::test(EditProduct::class, $arguments)->instance()->catalogMainImageSourceOptions();

    expect($options)->toHaveKey('source:'.$eligible->id)->toHaveCount(1);
    foreach ($foreignIds as $id) {
        expect($options)->not->toHaveKey('source:'.$id);
    }
})->with(['global target' => [false, 'target'], 'global context' => [false, 'context'],
    'drawer parent' => [true, 'context'], 'drawer extra key' => [true, 'extra']]);

test('an actual image save preserves the drawer stale guard and its unrelated ordinary draft', function () {
    $product = Product::factory()->create();
    $drawer = Livewire::test(EditProduct::class, ['record' => $product->id, 'listKey' => 'group-products', 'parentId' => $product->group_id])
        ->fillForm(['product_name' => 'Keep this drawer draft']);
    [$writer, $property, $reference] = nativeImageUpload($product);
    $writer->call('_finishUpload', $property, [$reference], false);
    for ($step = 0; $step < 10 && $writer->get('catalogMainImageSourceProgress.stage') === 'scanning'; $step++) {
        $writer->call('catalogMainImageAdvanceSource');
    }
    $writer->call('prepareImageSave')->call('save', false, false)->assertHasNoErrors();
    $assetId = $product->fresh()->main_image_id;
    expect($assetId)->not->toBeNull();
    $drawer->call('save')->assertHasErrors(['data.record'])->assertSet('batchEditorStale', true)
        ->assertSet('data.product_name', 'Keep this drawer draft')->assertNotDispatched('item-drawer-record-saved');
    expect($product->fresh()->main_image_id)->toBe($assetId)->and($product->fresh()->product_name)->toBe('Test product');
});

test('a remounted native host resumes its saved scan identity and reviews the private upload without reupload', function (string $pageClass, string $target) {
    $record = match ($pageClass) {
        EditProduct::class => Product::factory()->create(),
        EditGroup::class => Group::factory()->create(),
        default => null,
    };
    [$original, $property, $reference] = nativeImageUpload($record, 'recover.png', $pageClass);
    $original->call('_finishUpload', $property, [$reference], false)->call('catalogMainImageAdvanceSource')->assertHasNoErrors();
    $progress = $original->get('catalogMainImageSourceProgress');
    $count = CatalogAssetImportSource::count();
    $remount = Livewire::test($pageClass, $record === null ? [] : ['record' => $record->id]);
    expect($remount->instance()->catalogMainImageSourceOptions())->toHaveKey('source:'.$progress['source_id']);
    $remount->call('catalogMainImageChooseSource', 'source:'.$progress['source_id'])->assertHasNoErrors()
        ->assertSet('catalogMainImageSourceProgress.source_id', $progress['source_id'])
        ->assertSet('catalogMainImageSourceProgress.scan_version', $progress['scan_version'])
        ->call('catalogMainImageAdvanceSource')->assertHasNoErrors()->assertSet('catalogMainImageSourceProgress.stage', 'ready');
    expect(CatalogAssetImportSource::count())->toBe($count);
    $candidate = $remount->instance()->catalogMainImageCandidates()['entries'][0];
    $remount->call('catalogMainImageSelectCandidate', $candidate['entry_id'])->assertSet('data.image_intent.operation', 'set');
    expect(CatalogAssetImportSource::findOrFail($progress['source_id'])->source['target_key'])->toBe($target);
})->with([
    'Product Edit' => [EditProduct::class, 'product.main_image'],
    'Group Edit' => [EditGroup::class, 'group.main_image'],
    'Group Create new session' => [CreateGroup::class, 'group.main_image'],
]);

test('an owned source selected by stable identity remains reachable beyond the latest fifty list results', function () {
    $product = Product::factory()->create();
    [$original, $property, $reference] = nativeImageUpload($product, 'older.png');
    $original->call('_finishUpload', $property, [$reference], false);
    for ($step = 0; $step < 10 && $original->get('catalogMainImageSourceProgress.stage') === 'scanning'; $step++) {
        $original->call('catalogMainImageAdvanceSource');
    }
    $id = $original->get('catalogMainImageSourceProgress.source_id');
    CatalogAssetImportSource::factory()->count(51)->create(['actor_id' => auth()->id(), 'status' => 'ready',
        'source' => ['target_key' => 'product.main_image', 'owner_context' => [], 'label' => 'Newer source'], 'created_at' => now()->addMinute()]);
    $remount = Livewire::test(EditProduct::class, ['record' => $product->id]);
    expect($remount->instance()->catalogMainImageSourceOptions())->not->toHaveKey('source:'.$id);
    $remount->call('catalogMainImageChooseSource', 'source:'.$id)->assertSuccessful()->assertHasNoErrors()
        ->assertSet('catalogMainImageSourceProgress.source_id', $id)->assertSet('catalogMainImageSourceProgress.stage', 'ready');
    expect($remount->instance()->catalogMainImageCandidates()['entries'])->toHaveCount(1);
});

test('a remount can inspect failed and stale journals without restarting or rewriting them', function (string $status) {
    $product = Product::factory()->create();
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload(auth()->user(), 'product.main_image', [], 'Saved source');
    $source->forceFill(['status' => $status, 'error_code' => 'source_stale'])->save();
    $before = $source->fresh()->getAttributes();
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id]);
    expect($editor->instance()->catalogMainImageSourceOptions())->toHaveKey('source:'.$source->id);
    $editor->call('catalogMainImageChooseSource', 'source:'.$source->id)->assertSuccessful()
        ->assertSet('catalogMainImageSourceProgress.stage', $status)->assertSet('catalogMainImageSourceProgress.next_action', 'review_again')
        ->call('catalogMainImageSourceStatus')->call('catalogMainImageAdvanceSource')->assertHasNoErrors();
    expect($source->fresh()->getAttributes())->toBe($before)->and(CatalogAssetImportSource::count())->toBe(1);
})->with(['failed', 'stale']);

test('a stable source identity cannot bypass actor target or exact drawer scope', function (string $fault) {
    $product = Product::factory()->create();
    $actor = $fault === 'actor' ? User::factory()->create(['email' => 'beny.cr@gmail.com']) : auth()->user();
    $target = $fault === 'target' ? 'group.main_image' : 'product.main_image';
    $context = $fault === 'context' ? ['list_key' => 'group-products', 'parent_id' => $product->group_id] : [];
    $source = app(CatalogAssetSources::class)->startUpload($actor, $target, $context, 'Ineligible source');
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id]);
    $editor->call('catalogMainImageChooseSource', 'source:'.$source->id)->assertNotFound();
    expect($source->fresh()->status)->toBe('uploading');
})->with(['actor', 'target', 'context']);

test('source status cannot silently adopt a different selected version', function () {
    $product = Product::factory()->create();
    $source = app(CatalogAssetSources::class)->startUpload(auth()->user(), 'product.main_image', [], 'Original version');
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->call('catalogMainImageChooseSource', 'source:'.$source->id);
    $source->forceFill(['scan_version' => 2])->save();
    $editor->call('catalogMainImageSourceStatus')->assertStatus(409);
});

test('the same native host resumes a busy or paused intake using its exact source entry and upload binding', function (string $fault) {
    $this->freezeTime();
    $product = Product::factory()->create();
    [$editor, $property, $reference] = nativeImageUpload($product, 'resume-intake.png');
    if ($fault === 'busy') {
        $blocked = false;
        Event::listen('eloquent.updated: '.CatalogAssetImportSource::class, function (CatalogAssetImportSource $source) use (&$blocked, $reference) {
            $ledger = $source->upload_ledger;
            $id = array_key_first($ledger);
            if ($blocked || $id === null || $ledger[$id]['status'] !== 'reserved') {
                return;
            }
            $blocked = true;
            $ledger[$id] = [...$ledger[$id], 'status' => 'uploading', 'token' => (string) Str::ulid(),
                'reference_hash' => hash('sha256', $reference), 'expires_at' => now()->addSeconds(60)->timestamp];
            DB::table('catalog_asset_import_sources')->where('id', $source->id)->update(['upload_ledger' => json_encode($ledger), 'scan_revision' => $source->scan_revision + 1]);
        });
    } else {
        $real = new CatalogAssetSourceFiles;
        $files = Mockery::mock(CatalogAssetSourceFiles::class)->makePartial();
        $calls = 0;
        $files->shouldReceive('inspect')->andReturnUsing(function (...$args) use ($real, &$calls) {
            if ($calls++ === 0) {
                throw new RuntimeException('Synthetic temporary filesystem outage');
            }

            return $real->inspect(...$args);
        });
        $this->instance(CatalogAssetSourceFiles::class, $files);
    }
    $editor->call('_finishUpload', $property, [$reference], false)->assertSuccessful()->assertHasNoErrors()
        ->assertSet('catalogMainImageSourceProgress.outcome', $fault)->assertSee('Resume upload');
    $binding = $editor->get('catalogMainImageUploadBinding');
    $id = $binding['source_id'];
    $entryId = $binding['entry_id'];
    $editor->call('prepareImageSave')->assertHasErrors(['data.image_intent']);
    $this->travel(61)->seconds();
    $editor->call('catalogMainImageStageUpload')->assertHasNoErrors()
        ->assertSet('catalogMainImageSourceProgress.source_id', $id)->assertSet('catalogMainImageSourceProgress.scan_version', 1);
    for ($step = 0; $step < 10 && $editor->get('catalogMainImageSourceProgress.stage') === 'scanning'; $step++) {
        $editor->call('catalogMainImageAdvanceSource');
    }
    $editor->assertSet('data.image_intent.operation', 'set')->call('prepareImageSave')->call('save', false, false)->assertHasNoErrors();
    $source = CatalogAssetImportSource::findOrFail($id);
    expect(CatalogAssetImportSource::count())->toBe(1)->and(array_keys($source->upload_ledger))->toBe([$entryId])
        ->and($source->upload_ledger[$entryId]['reference_hash'])->toBe(hash('sha256', $reference))
        ->and($product->fresh()->main_image_id)->toBe(CatalogAsset::sole()->id);
})->with(['busy', 'paused']);

test('a remounted settled upload seals and continues its own journal without a new scan identity', function () {
    $product = Product::factory()->create();
    [$original, $property, $reference] = nativeImageUpload($product, 'settled.png');
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload(auth()->user(), 'product.main_image', [], 'Settled before interrupted seal');
    $entry = (string) Str::ulid();
    $sources->reserveUpload(auth()->user(), $source->id, $entry, ['name' => 'settled.png', 'relative_path' => 'settled.png', 'bytes' => 1]);
    $sources->commitUpload(auth()->user(), $source->id, $entry, $reference);
    $remount = Livewire::test(EditProduct::class, ['record' => $product->id]);
    $remount->call('catalogMainImageChooseSource', 'source:'.$source->id)->assertSet('catalogMainImageSourceProgress.stage', 'uploading')
        ->call('catalogMainImageAdvanceSource')->assertSet('catalogMainImageSourceProgress.stage', 'scanning');
    for ($step = 0; $step < 10 && $remount->get('catalogMainImageSourceProgress.stage') === 'scanning'; $step++) {
        $remount->call('catalogMainImageAdvanceSource');
    }
    $remount->assertSet('catalogMainImageSourceProgress.stage', 'ready')->assertSet('catalogMainImageSourceProgress.source_id', $source->id)
        ->assertSet('catalogMainImageSourceProgress.scan_version', 1);
    expect(CatalogAssetImportSource::count())->toBe(1)->and($remount->instance()->catalogMainImageCandidates()['entries'])->toHaveCount(1);
});

function k7NativeImageSave(Product|Group $record, string $filename, string $pageClass): Testable
{
    [$editor, $property, $reference] = nativeImageUpload($record, $filename, $pageClass);
    $editor->call('_finishUpload', $property, [$reference], false)->assertHasNoErrors();
    for ($step = 0; $step < 20 && $editor->get('catalogMainImageSourceProgress.stage') === 'scanning'; $step++) {
        $editor->call('catalogMainImageAdvanceSource')->assertHasNoErrors();
    }
    $editor->assertSet('catalogMainImageSourceProgress.stage', 'ready')
        ->assertSet('data.image_intent.operation', 'set')
        ->call('prepareImageSave')->assertHasNoErrors()
        ->call('save', false, false)->assertHasNoErrors()->assertDispatched('catalog-image-save-committed');

    return $editor;
}

test('K7 native Product image Save replace and remove reach cards and the real configurator child', function () {
    $group = Group::factory()->create();
    $product = Product::factory()->for($group)->create(['product_code' => 'K7-VALVE', 'profile_fields' => ['0' => '001', 'product_pic' => '/not-managed.jpg']]);
    $revision = $group->fresh()->catalog_revision;
    k7NativeImageSave($product, 'first.png', EditProduct::class);
    $firstId = $product->fresh()->main_image_id;
    $first = app(CatalogAssetDisplay::class)->forIds([$firstId])[$firstId];
    expect($group->fresh()->catalog_revision)->not->toBe($revision);
    $snapshot = app(CatalogSnapshots::class)->get($group->id);
    $cards = app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], $snapshot->revision(), 'k7-native');
    expect(implode('', $cards['htmlChunks']))->toContain($first['thumbnail_url']);
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->assertViewHas('mainImage', $first)->assertSeeHtml('src="'.$first['thumbnail_url'].'"');
    $revision = $group->fresh()->catalog_revision;

    k7NativeImageSave($product, 'replacement.png', EditProduct::class);
    $nextId = $product->fresh()->main_image_id;
    expect($nextId)->not->toBe($firstId);
    expect($group->fresh()->catalog_revision)->not->toBe($revision);
    $replacement = app(CatalogAssetDisplay::class)->forIds([$nextId])[$nextId];
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->assertViewHas('mainImage', $replacement)->assertDontSeeHtml($first['thumbnail_url']);
    $stale = app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], $snapshot->revision(), 'k7-stale');
    expect($stale['status'])->toBe('refresh_required')->and($stale['htmlChunks'])->toBe([]);
    $freshSnapshot = app(CatalogSnapshots::class)->get($group->id);
    $freshCards = app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], $freshSnapshot->revision(), 'k7-replacement');
    expect(implode('', $freshCards['htmlChunks']))->toContain($replacement['thumbnail_url'])->not->toContain($first['thumbnail_url']);
    $revision = $group->fresh()->catalog_revision;

    Livewire::test(EditProduct::class, ['record' => $product->id])->call('catalogMainImageRemove')->call('prepareImageSave')->assertHasNoErrors()->call('save', false, false)->assertHasNoErrors();
    expect($product->fresh()->main_image_id)->toBeNull()->and($product->fresh()->profile_fields)->toBe(['0' => '001', 'product_pic' => '/not-managed.jpg']);
    expect($group->fresh()->catalog_revision)->not->toBe($revision);
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->assertViewHas('mainImage', null)->assertDontSeeHtml($replacement['thumbnail_url']);
    $removedSnapshot = app(CatalogSnapshots::class)->get($group->id);
    $removedCards = app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], $removedSnapshot->revision(), 'k7-removed');
    expect(implode('', $removedCards['htmlChunks']))->not->toContain($replacement['thumbnail_url'], '/not-managed.jpg');
    expect(CatalogAsset::query()->whereKey([$firstId, $nextId])->count())->toBe(2);
});

test('K7 native Group image Save replace and remove refresh only its own card without changing topology', function () {
    $parent = Group::factory()->create();
    $group = Group::factory()->for($parent, 'parent')->create(['name' => 'K7 image Group']);
    Product::factory()->for($group)->create();
    $navigation = app(CatalogNavigation::class);
    $before = $navigation->get(auth()->user(), []);
    $revision = $group->fresh()->catalog_revision;

    k7NativeImageSave($group, 'group-first.png', EditGroup::class);
    $firstId = $group->fresh()->main_image_id;
    $first = $navigation->get(auth()->user(), []);
    expect($first['cards'][0]['mainImage']['id'])->toBe($firstId)->and($first['treeVersion'])->toBe($before['treeVersion']);
    expect($group->fresh()->catalog_revision)->not->toBe($revision);
    expect(view('components.catalog.group-card', ['card' => $first['cards'][0]])->render())->toContain($first['cards'][0]['mainImage']['thumbnail_url']);
    $revision = $group->fresh()->catalog_revision;

    k7NativeImageSave($group, 'group-next.png', EditGroup::class);
    $next = $navigation->get(auth()->user(), []);
    expect($next['cards'][0]['mainImage']['id'])->toBe($group->fresh()->main_image_id)->not->toBe($firstId);
    expect($group->fresh()->catalog_revision)->not->toBe($revision);
    expect($next['treeVersion'])->toBe($before['treeVersion']);
    $revision = $group->fresh()->catalog_revision;

    Livewire::test(EditGroup::class, ['record' => $group->id])->call('catalogMainImageRemove')->call('prepareImageSave')->assertHasNoErrors()->call('save', false, false)->assertHasNoErrors();
    $removed = $navigation->get(auth()->user(), []);
    expect($removed['cards'][0]['mainImage'])->toBeNull()->and($removed['treeVersion'])->toBe($before['treeVersion']);
    expect($group->fresh()->catalog_revision)->not->toBe($revision);
    expect($group->fresh()->name)->toBe('K7 image Group')->and($group->fresh()->parent_id)->toBe($parent->id);
    expect(CatalogAsset::count())->toBe(2);
});
