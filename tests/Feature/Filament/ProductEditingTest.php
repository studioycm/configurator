<?php

use App\Actions\SaveCatalogProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogSnapshots;
use App\Services\ProductEditorData;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

function productEditingPayload(Product $product): array
{
    return ['product_code' => $product->product_code, 'product_name' => $product->product_name, 'description' => $product->description,
        'properties' => ProductEditorData::rows($product->properties), 'parts' => ProductEditorData::rows($product->parts), 'extra_data' => ProductEditorData::rows($product->extra_data)];
}

test('Products open their editor in the existing right pane and save arbitrary existing JSON keys', function () {
    $product = Product::factory()->create(['product_code' => 'BEFORE', 'properties' => ['Working_Pressure' => '10', 'New.Field' => 'old'],
        'parts' => ['Part1' => 'old part'], 'extra_data' => ['<source key>' => 'old extra']])->refresh();
    $group = $product->group;
    $revision = $group->catalog_revision;
    $list = Livewire::test(ListProducts::class)->searchTable('BEFORE')->callAction(TestAction::make('select')->table($product))
        ->assertSet('selectedRecord', (string) $product->id)->assertSet('tableSearch', 'BEFORE')->assertNoRedirect();
    expect($list->instance()->editorComponent())->toBe(EditProduct::class);

    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->assertSee('New.Field')->assertSee('&lt;source key&gt;', false)
        ->fillForm(['product_code' => 'AFTER', 'product_name' => 'New name', 'description' => '',
            'properties' => [['key' => 'Working_Pressure', 'value' => '12'], ['key' => 'New.Field', 'value' => ' 001 new ']],
            'parts' => [['key' => 'Part1', 'value' => 'new part']], 'extra_data' => [['key' => '<source key>', 'value' => 'new extra']]])
        ->call('save')->assertHasNoFormErrors()->assertNotified()->assertDispatched('catalog-record-saved')->assertNoRedirect();

    $savedProperties = $product->fresh()->properties;
    ksort($savedProperties);
    expect($savedProperties)->toBe(['New.Field' => ' 001 new ', 'Working_Pressure' => '12']);
    expect($product->fresh()->parts)->toBe(['Part1' => 'new part']);
    expect($product->fresh()->extra_data)->toBe(['<source key>' => 'new extra']);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'product_code' => 'AFTER', 'product_name' => 'New name', 'group_id' => $group->id, 'legacy_id' => $product->legacy_id]);
    expect((int) $group->fresh()->catalog_revision)->toBe((int) $revision + 1);
    $editor->assertSet('data.product_code', 'AFTER')->assertSet('data.product_name', 'New name')
        ->call('save')->assertHasNoFormErrors();
    expect((int) $group->fresh()->catalog_revision)->toBe((int) $revision + 1);
});

test('Product field values have no suggestions and preserve free text units and ranges', function () {
    $product = Product::factory()->create(['properties' => ['Working_Pressure' => '10 PSI']])->refresh();
    Product::factory()->for($product->group)->create(['properties' => ['Working_Pressure' => 'XX psi (XX bar)']]);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->assertDontSee('<datalist', false)
        ->fillForm(['properties' => [['key' => 'Working_Pressure', 'value' => ' 10psi - 16 psi ']]])
        ->call('save')->assertHasNoFormErrors();

    expect($product->fresh()->properties)->toBe(['Working_Pressure' => ' 10psi - 16 psi ']);
});

test('the Product editor rejects a forged JSON key while preserving its draft and saved values', function () {
    $product = Product::factory()->create(['properties' => ['source' => 'saved']]);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['product_name' => 'Draft name', 'properties' => [['key' => 'renamed', 'value' => 'Draft value']]])
        ->call('save')->assertHasFormErrors(['properties'])->assertSet('data.product_name', 'Draft name')->assertNotNotified();

    expect($product->fresh()->product_name)->toBe('Test product');
    expect($product->fresh()->properties)->toBe(['source' => 'saved']);
});

test('clearing Product text keeps its JSON keys and unchanged empty objects survive repeated saves', function () {
    $product = Product::factory()->create(['properties' => ['001' => '001', 'empty' => 'before'], 'parts' => (object) [], 'extra_data' => (object) []])->refresh();
    $revision = $product->group->catalog_revision;
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['product_name' => null, 'description' => null, 'properties' => [['key' => '001', 'value' => '001'], ['key' => 'empty', 'value' => '']]])
        ->call('save')->assertHasNoFormErrors();

    expect($product->fresh()->properties)->toBe(['001' => '001', 'empty' => '']);
    expect($product->fresh()->product_name)->toBe('');
    expect($product->fresh()->description)->toBe('');
    expect($product->fresh()->getRawOriginal('parts'))->toBe('{}');
    expect($product->fresh()->getRawOriginal('extra_data'))->toBe('{}');
    $editor->call('save')->assertHasNoFormErrors();
    expect((int) $product->group->fresh()->catalog_revision)->toBe((int) $revision + 1);
});

test('Product edits refresh the cached catalog filter vocabulary', function () {
    $product = Product::factory()->create(['properties' => ['Working_Pressure' => '10']]);
    GroupFilter::factory()->for($product->group)->create(['property_key' => 'Working_Pressure', 'value_order' => []]);
    $snapshots = app(CatalogSnapshots::class);
    $before = $snapshots->get($product->group_id);
    expect(array_column($before->data['fields'][0]['options'], 'value'))->toBe(['10']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['properties' => [['key' => 'Working_Pressure', 'value' => '12']]])
        ->call('save')->assertHasNoFormErrors();

    $after = $snapshots->get($product->group_id);
    expect(array_column($after->data['fields'][0]['options'], 'value'))->toBe(['12']);
    expect($after->revision())->not->toBe($before->revision());
});

test('editing numeric JSON keys retains the original object shape and field labels', function () {
    $product = Product::factory()->create(['extra_data' => (object) ['0' => 'first', '1' => 'second']]);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['extra_data' => [['key' => '0', 'value' => 'updated'], ['key' => '1', 'value' => 'second']]])
        ->call('save')->assertHasNoFormErrors();

    expect(json_decode($product->fresh()->getRawOriginal('extra_data')))->toBeInstanceOf(stdClass::class);
    expect($product->fresh()->extra_data)->toBe([0 => 'updated', 1 => 'second']);
});

test('the Product domain save rejects renamed added removed or duplicate JSON keys atomically', function (string $kind) {
    $product = Product::factory()->create(['properties' => ['first' => '1', 'second' => '2']])->refresh();
    $before = $product->getAttributes();
    $revision = $product->group->catalog_revision;
    $data = productEditingPayload($product);
    $data['product_name'] = 'Must not save';
    $data['properties'] = match ($kind) {
        'renamed' => [['key' => 'other', 'value' => '1'], ['key' => 'second', 'value' => '2']],
        'added' => [...$data['properties'], ['key' => 'other', 'value' => '3']],
        'removed' => [['key' => 'first', 'value' => '1']],
        'duplicate' => [['key' => 'first', 'value' => '1'], ['key' => 'first', 'value' => '2']],
    };

    expect(fn () => app(SaveCatalogProduct::class)->handle(auth()->user(), $product, $data, SaveCatalogProduct::fingerprint($product)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('product.properties'));

    expect($product->fresh()->getAttributes())->toBe($before);
    expect($product->group->fresh()->catalog_revision)->toBe($revision);
})->with(['renamed', 'added', 'removed', 'duplicate']);

test('Product identity relations and status cannot be forged through the content save', function (string $field, mixed $value) {
    $product = Product::factory()->create()->refresh();
    $data = [...productEditingPayload($product), $field => $value];

    expect(fn () => app(SaveCatalogProduct::class)->handle(auth()->user(), $product, $data, SaveCatalogProduct::fingerprint($product)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('product'));

    expect($product->fresh()->getAttributes())->toBe($product->getAttributes());
})->with(['legacy identity' => ['legacy_id', 'forged'], 'legacy Group' => ['legacy_group_id', 'forged'], 'Group relation' => ['group_id', 999], 'status' => ['is_active', false]]);

test('unchanged JSON types and nulls survive a Product content save without coercion', function () {
    $product = Product::factory()->create(['properties' => ['code' => '001', 'nullable' => null, 'number' => 12, 'flag' => false, 'nested' => ['key' => 'value']]])->refresh();
    $data = productEditingPayload($product);
    $data['product_name'] = 'Changed';

    app(SaveCatalogProduct::class)->handle(auth()->user(), $product, $data, SaveCatalogProduct::fingerprint($product));

    expect($product->fresh()->properties)->toBe($product->properties);
    $data = productEditingPayload($product->fresh());
    $data['properties'][2]['value'] = '13';
    expect(fn () => app(SaveCatalogProduct::class)->handle(auth()->user(), $product, $data, SaveCatalogProduct::fingerprint($product->fresh())))->toThrow(ValidationException::class);
});

test('duplicate Product codes and stale domain baselines do not overwrite newer saved content', function () {
    $product = Product::factory()->create(['product_code' => 'ORIGINAL'])->refresh();
    Product::factory()->create(['product_code' => 'TAKEN']);
    $data = productEditingPayload($product);
    $data['product_code'] = 'TAKEN';
    expect(fn () => app(SaveCatalogProduct::class)->handle(auth()->user(), $product, $data, SaveCatalogProduct::fingerprint($product)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('product.product_code'));
    $baseline = SaveCatalogProduct::fingerprint($product);
    $product->update(['description' => 'Intervening import']);
    $data['product_code'] = 'CHANGED';
    expect(fn () => app(SaveCatalogProduct::class)->handle(auth()->user(), $product, $data, $baseline))->toThrow(ValidationException::class);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'product_code' => 'ORIGINAL', 'description' => 'Intervening import']);
});

test('stale Product editor drafts remain visible and can deliberately reload the saved JSON keys', function () {
    $product = Product::factory()->create(['properties' => ['before' => 'old']]);
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Retained draft']);
    $product->update(['properties' => ['after' => 'new']]);

    $editor->call('save')->assertHasErrors(['data.record'])->assertSet('data.product_name', 'Retained draft')->assertNotNotified();
    $editor->call('reloadBatchEditor')->assertHasNoErrors()->assertSee('after')->assertDontSee('before');
});

test('Product editing checks administration permission on mounting and saving without enabling creation or deletion', function () {
    $product = Product::factory()->create();
    $data = productEditingPayload($product);
    $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['product_name' => 'Forbidden']);
    $outsider = User::factory()->create(['email' => 'visitor@example.test']);
    $this->actingAs($outsider);

    Livewire::test(EditProduct::class, ['record' => $product->id])->assertForbidden();
    $editor->call('save')->assertForbidden();
    expect(fn () => app(SaveCatalogProduct::class)->handle($outsider, $product, $data, SaveCatalogProduct::fingerprint($product)))->toThrow(AuthorizationException::class);
    expect(ProductResource::canCreate())->toBeFalse()->and(ProductResource::canDelete($product))->toBeFalse();
    expect($product->fresh()->product_name)->toBe($product->product_name);
});
