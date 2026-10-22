<?php

use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
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
    $editor->assertSet('data.'.$field, 'valve_seal')->assertDispatched('identifier-suggested');
    $editor->set('data.label', 'Revised seal')->assertSet('data.'.$field, 'valve_seal');
    $editor->callAction(TestAction::make('regenerateIdentifier')->schemaComponent($field, 'form'))
        ->assertHasNoFormErrors()->assertSet('data.'.$field, 'revised_seal');
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
    Livewire::test(CreateValue::class)->set('data.label', 'Valve seal')->assertSet('data.semantic_id', 'valve_seal_2');
    Livewire::test(CreateAttribute::class)->set('data.label', 'Valve seal')->assertSet('data.key', 'valve_seal_2');
});
