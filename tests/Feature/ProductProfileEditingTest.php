<?php

use App\Actions\SaveCatalogProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductEditorData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function profileEditorPayload(Product $product): array
{
    return ['product_code' => $product->product_code, 'product_name' => $product->product_name, 'description' => $product->description,
        ...ProductEditorData::formData($product)];
}

test('explicit editor Properties selection controls order labels and blank rows while preserving the remaining profile', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create(['result_settings' => ['editor_properties' => ['Missing_Field', 'custom.key', 'pressure'], 'card_properties' => ['filter_only']]]);
    $product = Product::factory()->for($group)->create(['profile_fields' => (object) [
        'pressure' => '10', 'custom.key' => '001', 'filter_only' => 'flange', 'nested' => (object) ['empty' => (object) [], 'list' => []],
    ]]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'filter_only', 'label' => 'Filter only', 'sort_order' => 0]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'pressure', 'label' => 'Pressure label', 'sort_order' => 1]);

    $editor = Livewire::test(EditProduct::class, ['record' => $product->id]);

    expect(array_values($editor->get('data.properties')))->toBe([
        ['key' => 'Missing_Field', 'value' => ''], ['key' => 'custom.key', 'value' => '001'], ['key' => 'pressure', 'value' => '10'],
    ]);
    expect(array_column(array_values($editor->get('data.others')), 'key'))->toBe(['filter_only', 'nested']);
    $editor->assertSee('Missing Field')->assertSee('Pressure label')->fillForm(['properties' => [
        ['key' => 'Missing_Field', 'value' => ''], ['key' => 'custom.key', 'value' => '002'], ['key' => 'pressure', 'value' => '10'],
    ]])->call('save')->assertHasNoFormErrors();
    $profile = json_decode($product->fresh()->getRawOriginal('profile_fields'));
    expect($profile->{'custom.key'})->toBe('002')->and($profile->filter_only)->toBe('flange');
    expect(property_exists($profile, 'Missing_Field'))->toBeFalse();
    expect($profile->nested->empty)->toBeInstanceOf(stdClass::class)->and($profile->nested->list)->toBe([]);
    expect($group->fresh()->result_settings['card_properties'])->toBe(['filter_only']);
    expect($group->filters()->orderBy('sort_order')->pluck('property_key')->all())->toBe(['filter_only', 'pressure']);
});

test('editor Properties selection does not reclassify the read only product facts', function () {
    $group = Group::factory()->create(['result_settings' => ['editor_properties' => ['Editor_Only']]]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['Editor_Only' => 'draft value', 'Public_Field' => 'public value']]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Public_Field', 'label' => 'Public filter']);

    $component = Livewire::test(ProductConfigurator::class, ['productId' => $product->id]);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$component->html());
    $xpath = new DOMXPath($document);

    expect(collect($xpath->query('//section[@aria-label="Properties"]//dt'))->map(fn (DOMNode $node): string => trim($node->textContent))->all())->toBe(['Public filter']);
    expect(collect($xpath->query('//section[@aria-label="Others"]//dt'))->map(fn (DOMNode $node): string => trim($node->textContent))->all())->toBe(['Editor_Only']);
});

test('missing or empty editor Properties selection follows current ordered filters', function (array $settings) {
    $group = Group::factory()->create(['result_settings' => $settings]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['last' => 'value', 'other' => 'retained']]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'last', 'label' => 'Last', 'sort_order' => 2]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'first', 'label' => 'First', 'sort_order' => 1]);

    $data = ProductEditorData::formData($product);

    expect($data['properties'])->toBe([['key' => 'first', 'value' => ''], ['key' => 'last', 'value' => 'value']]);
    expect($data['others'])->toBe([['key' => 'other', 'value' => 'retained', 'original_key' => 'other']]);
})->with(['missing setting' => [[]], 'empty setting' => [['editor_properties' => []]]]);

test('changing editor Properties selection rejects an open Product editor and preserves its draft', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create(['result_settings' => ['editor_properties' => ['before']]]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['before' => '1', 'after' => '2']]);
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Retained draft']);
    $group->update(['result_settings' => ['editor_properties' => ['after']]]);

    $editor->call('save')->assertHasFormErrors(['group_classification'])->assertSet('data.product_name', 'Retained draft')->assertNotNotified();

    expect($product->fresh()->product_name)->toBe('Test product');
    $editor->call('reloadBatchEditor')->assertHasNoErrors();
    expect(array_values($editor->get('data.properties')))->toBe([['key' => 'after', 'value' => '2']]);
});

test('unrelated filter changes do not invalidate an explicit editor Properties draft', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create(['result_settings' => ['editor_properties' => ['chosen']]]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['chosen' => '1', 'other' => '2']]);
    $filter = GroupFilter::factory()->for($group)->create(['property_key' => 'unrelated', 'label' => 'Unrelated']);
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Saved draft']);
    $filter->update(['property_key' => 'different', 'sort_order' => 7]);

    $editor->call('save')->assertHasNoFormErrors();

    expect($product->fresh()->product_name)->toBe('Saved draft');
    expect($product->fresh()->profile_fields)->toBe(['chosen' => '1', 'other' => '2']);
});

test('choosing the same effective editor Properties list does not invalidate an open draft', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create();
    $product = Product::factory()->for($group)->create(['profile_fields' => ['chosen' => '1']]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'chosen', 'label' => 'Chosen']);
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Saved draft']);
    $group->update(['result_settings' => ['editor_properties' => ['chosen']]]);

    $editor->call('save')->assertHasNoFormErrors();

    expect($product->fresh()->product_name)->toBe('Saved draft');
});

test('matching filter label changes still invalidate an explicit editor Properties draft', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create(['result_settings' => ['editor_properties' => ['chosen']]]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['chosen' => '1']]);
    $filter = GroupFilter::factory()->for($group)->create(['property_key' => 'chosen', 'label' => 'Before']);
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Retained draft']);
    $filter->update(['label' => 'After']);

    $editor->call('save')->assertHasFormErrors(['group_classification'])->assertSet('data.product_name', 'Retained draft')->assertNotNotified();

    expect($product->fresh()->product_name)->toBe('Test product');
});

test('the Product editor classifies Properties from ordered Group filters including absent keys', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $product = Product::factory()->create(['profile_fields' => ['custom.key' => '001', 'pressure' => '10']]);
    GroupFilter::factory()->for($product->group)->create(['property_key' => 'pressure', 'label' => 'Pressure label', 'sort_order' => 2]);
    GroupFilter::factory()->for($product->group)->create(['property_key' => 'missing', 'label' => 'Missing label', 'sort_order' => 1]);

    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->assertSee('Missing label')->assertSee('Pressure label');

    expect(array_values($editor->get('data.properties')))->toBe([
        ['key' => 'missing', 'value' => ''], ['key' => 'pressure', 'value' => '10'],
    ]);
    expect(array_values($editor->get('data.others')))->toBe([
        ['key' => 'custom.key', 'value' => '001', 'original_key' => 'custom.key'],
    ]);
});

test('Others supports local adds renames and deletions while preserving nested object and list types', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $product = Product::factory()->create(['profile_fields' => (object) [
        'nested' => (object) ['empty' => (object) [], 'list' => []], 'nullable' => null, 'remove' => 'old',
    ], 'parts' => (object) ['Part1' => '001']])->refresh();
    $data = profileEditorPayload($product);
    $data['others'] = [
        ['key' => 'renamed.object', 'value' => '{"empty":{},"list":[]}', 'original_key' => 'nested'],
        ['key' => 'nullable', 'value' => '', 'original_key' => 'nullable'],
        ['key' => 'צבע."חדש"', 'value' => ' 001 mm '],
    ];

    app(SaveCatalogProduct::class)->handle($actor, $product, $data, SaveCatalogProduct::fingerprint($product), ProductEditorData::groupFingerprint($product));

    $saved = json_decode($product->fresh()->getRawOriginal('profile_fields'));
    expect(get_object_vars($saved))->toHaveKeys(['renamed.object', 'nullable', 'צבע."חדש"'])->not->toHaveKeys(['nested', 'remove']);
    expect($saved->{'renamed.object'}->empty)->toBeInstanceOf(stdClass::class);
    expect($saved->{'renamed.object'}->list)->toBe([]);
    expect($saved->nullable)->toBeNull();
    expect($saved->{'צבע."חדש"'})->toBe(' 001 mm ');
    expect($product->fresh()->parts)->toBe(['Part1' => '001']);
});

test('profile view validation rejects missing forged or duplicate selected keys atomically', function (string $kind) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $product = Product::factory()->create(['profile_fields' => ['selected' => '1', 'other' => '2']])->refresh();
    GroupFilter::factory()->for($product->group)->create(['property_key' => 'selected']);
    $before = $product->getAttributes();
    $data = profileEditorPayload($product);
    $data['product_name'] = 'Must stay a draft';
    match ($kind) {
        'missing' => $data['properties'] = [],
        'forged' => $data['properties'] = [['key' => 'forged', 'value' => '1']],
        'duplicate property' => $data['properties'][] = ['key' => 'selected', 'value' => '2'],
        'cross view duplicate' => $data['others'][] = ['key' => 'selected', 'value' => '2'],
        'duplicate other' => $data['others'][] = ['key' => 'other', 'value' => '3'],
        'invalid key' => $data['others'][] = ['key' => '', 'value' => '3'],
        'forged origin' => $data['others'][0]['original_key'] = 'absent',
        'selected origin' => $data['others'][0]['original_key'] = 'selected',
        'duplicate origin' => $data['others'][] = ['key' => 'new', 'value' => '2', 'original_key' => 'other'],
    };

    expect(fn () => app(SaveCatalogProduct::class)->handle($actor, $product, $data, SaveCatalogProduct::fingerprint($product), ProductEditorData::groupFingerprint($product)))
        ->toThrow(ValidationException::class);
    expect($product->fresh()->getAttributes())->toBe($before);
})->with(['missing', 'forged', 'duplicate property', 'cross view duplicate', 'duplicate other', 'invalid key', 'forged origin', 'selected origin', 'duplicate origin']);

test('Group filter changes reject an open Product editor and keep its draft until reload', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $product = Product::factory()->create(['profile_fields' => ['selected' => '1', 'other' => '2']]);
    $filter = GroupFilter::factory()->for($product->group)->create(['property_key' => 'selected']);
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Retained draft']);
    $filter->update(['property_key' => 'other', 'label' => 'Changed filter']);

    $editor->call('save')->assertHasFormErrors(['group_classification'])->assertSet('data.product_name', 'Retained draft')->assertNotNotified()
        ->assertSee(__('catalog.products.validation.group_filters_changed_after_your_editor_was'));
    expect($product->fresh()->product_name)->toBe('Test product');
    expect($product->fresh()->profile_fields)->toBe(['selected' => '1', 'other' => '2']);
    $editor->call('reloadBatchEditor')->assertHasNoErrors()->assertSee('Changed filter');
    expect(array_values($editor->get('data.properties')))->toBe([['key' => 'other', 'value' => '2']]);
});

test('missing selected fields stay absent during no op saves and become text when entered', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $product = Product::factory()->create(['profile_fields' => (object) []])->refresh();
    GroupFilter::factory()->for($product->group)->create(['property_key' => 'missing']);
    $revision = $product->group->catalog_revision;
    $data = profileEditorPayload($product);

    app(SaveCatalogProduct::class)->handle($actor, $product, $data, SaveCatalogProduct::fingerprint($product), ProductEditorData::groupFingerprint($product));
    expect($product->fresh()->getRawOriginal('profile_fields'))->toBe('{}');
    expect($product->group->fresh()->catalog_revision)->toBe($revision);

    $data['properties'][0]['value'] = '000';
    app(SaveCatalogProduct::class)->handle($actor, $product, $data, SaveCatalogProduct::fingerprint($product), ProductEditorData::groupFingerprint($product));
    expect($product->fresh()->profile_fields)->toBe(['missing' => '000']);
});

test('Product field names are escaped in input accessibility labels', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $key = '\"><img src=x onerror=alert(1)>';
    $product = Product::factory()->create(['profile_fields' => [$key => 'safe']]);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertSee('aria-label="'.e($key).'"', false);
});

test('editing text preserves pre existing JSON decimal number types in profile and Parts', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $product = Product::factory()->create();
    DB::table('products')->where('id', $product->id)->update([
        'profile_fields' => '{"decimal":1.0,"nested":{"decimal":2.0},"text":"old"}',
        'parts' => '{"Part1":1.0,"Part2":"old"}',
    ]);
    $product->refresh();
    $data = profileEditorPayload($product);
    $data['others'][2]['value'] = 'new';
    $data['parts'][1]['value'] = 'new';

    app(SaveCatalogProduct::class)->handle($actor, $product, $data, SaveCatalogProduct::fingerprint($product), ProductEditorData::groupFingerprint($product));

    $profile = json_decode($product->fresh()->getRawOriginal('profile_fields'));
    $parts = json_decode($product->fresh()->getRawOriginal('parts'));
    expect($profile->decimal)->toBe(1.0);
    expect($profile->nested->decimal)->toBe(2.0);
    expect($profile->text)->toBe('new');
    expect($parts->Part1)->toBe(1.0);
    expect($parts->Part2)->toBe('new');
});

test('Product JSON assignment preserves decimal types for every writer', function () {
    $product = Product::factory()->create([
        'profile_fields' => (object) ['decimal' => 1.0, 'nested' => (object) ['decimal' => 2.0]],
        'parts' => (object) ['Part1' => 1.0],
    ])->refresh();

    expect($product->profile_fields['decimal'])->toBe(1.0);
    expect($product->profile_fields['nested']['decimal'])->toBe(2.0);
    expect($product->parts['Part1'])->toBe(1.0);
});

test('Product editing blocks numeric JSON that cannot round trip without changing its value', function (string $bucket) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $product = Product::factory()->create();
    $json = $bucket === 'profile_fields'
        ? '{"huge":123456789012345678901234567890,"text":"old"}'
        : '{"Part1":123456789012345678901234567890,"Part2":"old"}';
    DB::table('products')->where('id', $product->id)->update([$bucket => $json]);
    $product->refresh();
    $before = $product->getAttributes();
    $revision = $product->group->catalog_revision;
    $data = ['product_code' => $product->product_code, 'product_name' => 'Must not save', 'description' => '', 'properties' => [],
        'others' => $bucket === 'profile_fields' ? [['key' => 'huge', 'value' => '1.2345678901234568e+29'], ['key' => 'text', 'value' => 'new']] : [],
        'parts' => $bucket === 'parts' ? [['key' => 'Part1', 'value' => '1.2345678901234568e+29'], ['key' => 'Part2', 'value' => 'new']] : [],
    ];

    expect(fn () => app(SaveCatalogProduct::class)->handle($actor, $product, $data, SaveCatalogProduct::fingerprint($product), ProductEditorData::groupFingerprint($product)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('product.'.$bucket));
    expect($product->fresh()->getAttributes())->toBe($before);
    expect($product->group->fresh()->catalog_revision)->toBe($revision);
    expect(fn () => ProductEditorData::formData($product))->toThrow(ValidationException::class);
})->with(['profile_fields', 'parts']);
