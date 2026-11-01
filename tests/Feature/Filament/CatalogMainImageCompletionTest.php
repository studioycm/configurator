<?php

use App\Filament\Resources\Groups\Pages\CreateGroup;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\CatalogAsset;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Events\RecordCreated;
use Filament\Resources\Events\RecordSaved;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    config(['catalog-assets.enabled' => true]);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    expect(DB::transactionLevel())->toBe(0);
});

function prepareCompletionImage(Testable $editor): int
{
    $file = UploadedFile::fake()->image('synthetic.png', 40, 20);
    $property = 'data.image_upload.'.Str::uuid();
    $editor->call('_startUpload', $property, [['name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'type' => $file->getMimeType()]], false);
    $binding = $editor->get('catalogMainImageUploadBinding');
    $url = URL::temporarySignedRoute('catalog.images.native-upload', now()->addMinutes(5), ['nonce' => $binding['nonce']], false);
    $response = test()->post($url, ['files' => [$file]])->assertOk();
    $editor->call('_finishUpload', $property, [$response->json('paths.0')], false)->assertHasNoErrors();
    for ($step = 0; $step < 10 && $editor->get('catalogMainImageSourceProgress.stage') === 'scanning'; $step++) {
        $editor->call('catalogMainImageAdvanceSource')->assertHasNoErrors();
    }
    expect($editor->get('catalogMainImageSourceProgress.stage'))->toBe('ready');
    $editor->assertSet('data.image_intent.operation', 'set')->call('prepareImageSave')->assertHasNoErrors();

    return CatalogAsset::sole()->id;
}

test('native image Save commits before completion and retains both false native flags', function (string $pageClass, string $modelClass, string $field) {
    $record = $modelClass::factory()->create()->refresh();
    $editor = Livewire::test($pageClass, ['record' => $record->id])->fillForm([$field => 'Committed draft']);
    $assetId = prepareCompletionImage($editor);
    $levels = [];
    Event::listen(RecordSaved::class, function ($saved, array $data, $page) use (&$levels, $record, $assetId) {
        $levels[] = DB::transactionLevel();
        expect($record->fresh()->main_image_id)->toBe($assetId);
        expect(collect(\Livewire\store($page)->get('dispatched', []))->map(fn ($event) => $event->serialize()['name'])->all())->not->toContain('catalog-image-save-committed');
    });
    $editor->call('save', false, false)->assertHasNoErrors()->assertNoRedirect()->assertNotNotified()
        ->assertDispatched('catalog-record-saved')->assertDispatched('catalog-image-save-committed')
        ->assertSet('data.image_intent.operation', 'keep');
    expect($levels)->toHaveCount(1)->and($levels[0])->toBeGreaterThan(0)
        ->and(DB::transactionLevel())->toBe(0)->and($record->fresh()->main_image_id)->toBe($assetId)
        ->and($record->fresh()->{$field})->toBe('Committed draft');
})->with([
    'Product' => [EditProduct::class, Product::class, 'product_name'],
    'Group' => [EditGroup::class, Group::class, 'name'],
]);

test('a native precommit failure rolls back image and ordinary fields without completion', function (string $pageClass, string $modelClass, string $field) {
    $record = $modelClass::factory()->create()->refresh();
    $before = $record->getAttributes();
    $editor = Livewire::test($pageClass, ['record' => $record->id])->fillForm([$field => 'Retained failed draft']);
    $assetId = prepareCompletionImage($editor);
    $page = $editor->instance();
    $draft = $page->data;
    Event::listen(RecordSaved::class, function ($saved, array $data, $page) use ($record, $assetId) {
        expect(DB::transactionLevel())->toBeGreaterThan(0)->and($record->fresh()->main_image_id)->toBe($assetId);
        throw new RuntimeException('Synthetic precommit failure');
    });
    expect(fn () => $page->save())->toThrow(RuntimeException::class, 'Synthetic precommit failure');
    expect($record->fresh()->getAttributes())->toBe($before)->and($page->data)->toBe($draft)
        ->and(DB::transactionLevel())->toBe(0);
    expect(collect(\Livewire\store($page)->get('dispatched', []))->map(fn ($event) => $event->serialize()['name'])->all())
        ->not->toContain('catalog-image-save-committed', 'catalog-record-saved', 'catalog-editor-saved');
    Notification::assertNotNotified();
    expect(\Livewire\store($page)->get('redirect'))->toBeNull();
})->with([
    'Product' => [EditProduct::class, Product::class, 'product_name'],
    'Group' => [EditGroup::class, Group::class, 'name'],
]);

test('actual create another commits its image before resetting the form and creation identity', function () {
    $editor = Livewire::test(CreateGroup::class)->fillForm(['name' => 'Created with image', 'sort_order' => 0]);
    $assetId = prepareCompletionImage($editor);
    $session = $editor->get('catalogMainImageCreationSession');
    $editor->call('create', true)->assertHasNoErrors()->assertNotified()->assertNoRedirect()
        ->assertSet('data.name', null)->assertSet('data.image_intent.operation', 'keep')
        ->assertDispatched('catalog-image-save-committed');
    expect(Group::where('name', 'Created with image')->sole()->main_image_id)->toBe($assetId)
        ->and($editor->get('catalogMainImageCreationSession'))->not->toBe($session)
        ->and(DB::transactionLevel())->toBe(0);
});

test('precommit creation failure keeps its draft session and makes no success notification or reset', function () {
    $editor = Livewire::test(CreateGroup::class)->fillForm(['name' => 'Uncommitted Group', 'sort_order' => 0]);
    prepareCompletionImage($editor);
    $page = $editor->instance();
    $session = $page->catalogMainImageCreationSession;
    $draft = $page->data;
    Event::listen(RecordCreated::class, function () {
        expect(DB::transactionLevel())->toBeGreaterThan(0);
        throw new RuntimeException('Synthetic creation failure');
    });
    expect(fn () => $page->create(true))->toThrow(RuntimeException::class, 'Synthetic creation failure');
    expect(Group::where('name', 'Uncommitted Group')->exists())->toBeFalse()
        ->and($page->data)->toBe($draft)->and($page->catalogMainImageCreationSession)->toBe($session)
        ->and(DB::transactionLevel())->toBe(0);
    expect(collect(\Livewire\store($page)->get('dispatched', []))->map(fn ($event) => $event->serialize()['name'])->all())->not->toContain('catalog-image-save-committed');
    Notification::assertNotNotified();
    expect($page->getRecord())->toBeNull();
    Event::forget(RecordCreated::class);
    $page->prepareImageSave();
    $page->create(true);
    expect(Group::where('name', 'Uncommitted Group')->count())->toBe(1)
        ->and(CatalogAsset::count())->toBe(1)->and($page->catalogMainImageCreationSession)->not->toBe($session);
});

test('ordinary keep saves retain existing ambient-transaction behavior while image saves reject it', function () {
    $product = Product::factory()->create();
    $ordinary = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Ordinary draft']);
    DB::beginTransaction();
    try {
        $ordinary->call('save', false, false)->assertHasNoErrors();
        expect($product->fresh()->product_name)->toBe('Ordinary draft')->and(DB::transactionLevel())->toBe(1);
    } finally {
        DB::rollBack();
    }
    $image = Livewire::test(EditProduct::class, ['record' => $product->id])->call('catalogMainImageRemove')->call('prepareImageSave');
    DB::beginTransaction();
    try {
        expect(fn () => $image->instance()->save())->toThrow(LogicException::class, 'must own their database commit');
        expect($product->fresh()->product_name)->toBe('Test product');
    } finally {
        DB::rollBack();
    }
});

test('embedded create another is denied without creating a Group or replacing the image draft', function () {
    $editor = Livewire::test(CreateGroup::class, ['embedded' => true])->fillForm(['name' => 'No embedded duplicate', 'sort_order' => 0]);
    prepareCompletionImage($editor);
    $draft = $editor->get('data');
    $editor->call('create', true)->assertHasErrors(['data.image_intent'])->assertNotNotified()->assertNoRedirect();
    expect(Group::where('name', 'No embedded duplicate')->exists())->toBeFalse()->and($editor->get('data'))->toBe($draft);
});

test('an inherited component-only save cannot bypass image preparation and invocation guards', function () {
    $product = Product::factory()->create()->refresh();
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Uncommitted component draft']);
    prepareCompletionImage($editor);
    $page = $editor->instance();
    $component = $page->form->getComponent(fn ($field): bool => method_exists($field, 'getStatePath') && $field->getStatePath() === 'data.product_name');
    expect($component)->not->toBeNull();
    expect(fn () => $page->saveFormComponentOnly($component))->toThrow(ValidationException::class);
    expect($product->fresh()->main_image_id)->toBeNull()->and($product->fresh()->product_name)->toBe('Test product')
        ->and($page->data['image_intent']['operation'])->toBe('set');
    Notification::assertNotNotified();
});
