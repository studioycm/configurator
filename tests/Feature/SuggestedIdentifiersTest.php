<?php

use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\SuggestedIdentifier;
use App\Filament\Resources\Values\Pages\CreateValue;
use App\Filament\Resources\Values\Pages\EditValue;
use App\Models\Attribute;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('new identifiers fill once and can be explicitly regenerated from the current label', function (string $page, string $field) {
    $editor = Livewire::test($page)->set('data.label', 'Valve seal');
    $editor->assertSet('data.'.$field, null)
        ->assertDispatched('identifier-suggested', field: 'data.'.$field, candidate: 'valve_seal', explicit: false);
    /** Simulate accepted browser state; PHP does not execute the Alpine candidate guard. */
    $editor->set('data.'.$field, $editor->get('data._identifier_'.$field.'_candidate.value'));
    $editor->set('data.label', 'Revised seal')->assertSet('data.'.$field, 'valve_seal');
    $editor->callAction(TestAction::make('regenerateIdentifier')->schemaComponent($field, 'form'))
        ->assertHasNoFormErrors()->assertSet('data.'.$field, 'valve_seal')
        ->assertDispatched('identifier-suggested', candidate: 'revised_seal', explicit: true);
    $editor->set('data.'.$field, $editor->get('data._identifier_'.$field.'_candidate.value'));
    $editor->set('data.'.$field, '')->set('data.label', 'Another seal')->assertSet('data.'.$field, '');
    $this->assertDatabaseCount($field === 'key' ? 'attributes' : 'values', 0);
})->with([[CreateValue::class, 'semantic_id'], [CreateAttribute::class, 'key']]);

test('manual identifiers and existing records are never automatically regenerated', function (string $createPage, string $editPage, string $model, string $field) {
    Livewire::test($createPage)->set('data.'.$field, 'manual')->set('data.label', 'Changed')->assertSet('data.'.$field, 'manual');
    $record = $model::factory()->create([$field => 'original']);
    Livewire::test($editPage, ['record' => $record->id])->set('data.label', 'Changed')->assertSet('data.'.$field, 'original')
        ->assertActionHidden(TestAction::make('regenerateIdentifier')->schemaComponent($field, 'form'));
    expect($record->fresh()->{$field})->toBe('original');
})->with([[CreateValue::class, EditValue::class, Value::class, 'semantic_id'], [CreateAttribute::class, EditAttribute::class, Attribute::class, 'key']]);

test('suggestions avoid collisions without changing existing identifiers', function () {
    Value::factory()->create(['semantic_id' => 'valve_seal']);
    Attribute::factory()->create(['key' => 'valve_seal']);
    Livewire::test(CreateValue::class)->set('data.label', 'Valve seal')->assertSet('data.semantic_id', null)->assertDispatched('identifier-suggested', candidate: 'valve_seal_2');
    Livewire::test(CreateAttribute::class)->set('data.label', 'Valve seal')->assertSet('data.key', null)->assertDispatched('identifier-suggested', candidate: 'valve_seal_2');
});

test('V02 reservations include persisted and pending identifiers within one hundred characters', function () {
    Value::factory()->create(['semantic_id' => 'light_blue']);
    expect(Value::suggestSemanticId('Light Blue', ['light_blue_2']))->toBe('light_blue_3');
    $label = str_repeat('a', 100);
    expect(Value::suggestSemanticId($label, [$label]))->toBe(str_repeat('a', 98).'_2');
    expect(SuggestedIdentifier::unique('Light Blue', Attribute::query(), 'key', ['light_blue']))->toBe('light_blue_2');
});

test('candidate reservations expire with native source or intent changes and preserve manual clear authority', function () {
    $row = ['label' => 'Name', 'semantic_id' => null, '_suggested_semantic_id' => false, '_identifier_semantic_id_intent' => 'current', '_identifier_semantic_id_candidate' => ['value' => 'name', 'label' => 'Name', 'intent' => 'current']];
    expect(SuggestedIdentifier::reservation($row, 'semantic_id'))->toBe('name');
    expect(SuggestedIdentifier::reservation([...$row, 'label' => 'New name'], 'semantic_id'))->toBeNull();
    expect(SuggestedIdentifier::reservation([...$row, '_identifier_semantic_id_intent' => 'new'], 'semantic_id'))->toBeNull();
    expect(SuggestedIdentifier::reservation([...$row, 'semantic_id' => '', '_suggested_semantic_id' => true], 'semantic_id'))->toBeNull();
    expect(SuggestedIdentifier::reservation([...$row, 'semantic_id' => 'manual'], 'semantic_id'))->toBe('manual');
});

test('a same-request manual clear prevents automatic suggestions and is not filled during create', function (string $page, string $field) {
    Livewire::test($page)->update(updates: ['data.label' => 'New label', 'data.'.$field => '', 'data._suggested_'.$field => true])
        ->assertSet('data.'.$field, '')->assertNotDispatched('identifier-suggested')
        ->call('create')->assertHasFormErrors([$field => 'required']);
    $this->assertDatabaseCount($field === 'key' ? 'attributes' : 'values', 0);
})->with([[CreateValue::class, 'semantic_id'], [CreateAttribute::class, 'key']]);
