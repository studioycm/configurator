<?php

use App\Livewire\Catalog\AttributeValueAssignment;
use App\Livewire\Catalog\ItemListDrawer;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('using a master value in another attribute fixes the parent and preserves exact code validation', function () {
    $parent = Value::factory()->create();
    $foreign = Value::factory()->create();
    $attribute = Attribute::factory()->create();
    $editor = Livewire::test(AttributeValueAssignment::class, ['listKey' => 'value-options', 'parentId' => $parent->id])
        ->assertFormFieldIsDisabled('value_id')
        ->fillForm(['attribute_id' => $attribute->id, 'value_id' => $foreign->id, 'code' => 'a']);
    $editor->call('save')->assertHasFormErrors(['code'])->assertNotDispatched('item-drawer-assignment-saved');
    $this->assertDatabaseCount('options', 0);
    $editor->fillForm(['code' => 'a1'])->call('save')->assertHasNoFormErrors()->assertDispatched('item-drawer-assignment-saved');
    $this->assertDatabaseHas('options', ['attribute_id' => $attribute->id, 'value_id' => $parent->id, 'code' => 'a1']);
});

test('adding master values to an attribute uses batch validation atomically', function () {
    $attribute = Attribute::factory()->create();
    $values = Value::factory()->count(2)->create();
    $editor = Livewire::test(AttributeValueAssignment::class, ['listKey' => 'attribute-options', 'parentId' => $attribute->id])
        ->fillForm(['pending_options' => $values->map(fn ($value) => ['value_id' => $value->id, 'name' => $value->label, 'code' => 'a1'])->all()]);
    $editor->call('save')->assertHasFormErrors();
    $this->assertDatabaseCount('options', 0);
    $editor->fillForm(['pending_options' => $values->map(fn ($value, $index) => ['value_id' => $value->id, 'name' => $value->label, 'code' => 'a'.($index + 1)])->all()])
        ->call('save')->assertHasNoFormErrors();
    $this->assertDatabaseCount('options', 2);
    expect(Option::pluck('attribute_id')->unique()->all())->toBe([$attribute->id]);
});

test('assignment remains inline and access is rechecked before saving', function () {
    $parent = Value::factory()->create();
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'value-options', 'parentId' => $parent->id, 'allowLocalEditing' => true])->call('beginAssignment')
        ->assertSet('isAssigning', true)->assertSeeLivewire(AttributeValueAssignment::class);
    expect($drawer->instance()->mountedActions)->toBeEmpty();
    $editor = Livewire::test(AttributeValueAssignment::class, ['listKey' => 'value-options', 'parentId' => $parent->id]);
    $this->actingAs(User::factory()->create(['email' => 'visitor@example.test']));
    $editor->call('save')->assertForbidden();
    $this->assertDatabaseCount('options', 0);
});

test('another attribute choices exclude existing usage and a concurrent duplicate is reported at the editable attribute', function () {
    $parent = Value::factory()->create();
    $used = Attribute::factory()->create(['label' => 'Seal used']);
    $available = Attribute::factory()->create(['label' => 'Seal available']);
    Option::factory()->create(['attribute_id' => $used->id, 'value_id' => $parent->id, 'code' => 'A1']);
    $editor = Livewire::test(AttributeValueAssignment::class, ['listKey' => 'value-options', 'parentId' => $parent->id]);
    $choices = $editor->instance()->form->getComponent('attribute_id')->getSearchResults('Seal');
    expect($choices)->toHaveKey($available->id)->not->toHaveKey($used->id);
    Option::factory()->create(['attribute_id' => $available->id, 'value_id' => $parent->id, 'code' => 'A2']);
    $editor->fillForm(['attribute_id' => $available->id, 'code' => 'A3'])->call('save')->assertHasFormErrors(['attribute_id']);
    $this->assertDatabaseCount('options', 2);
});

test('the inline Attribute picker exposes live search through its rendered schema key', function () {
    $parent = Value::factory()->create();
    Attribute::factory()->create(['label' => 'Fresh available Attribute']);
    $editor = Livewire::test(AttributeValueAssignment::class, ['listKey' => 'value-options', 'parentId' => $parent->id]);
    $field = $editor->instance()->form->getComponent('attribute_id');
    $results = $field->getSearchResultsForJs('Fresh');
    expect($results)->not->toBeEmpty();
    $editor->call('callSchemaComponentMethod', $field->getKey(), 'getSearchResultsForJs', ['search' => 'Fresh'])->assertReturned($results);
});
