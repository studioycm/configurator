<?php

use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Options\Pages\CreateOption;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\Values\Pages\CreateValue;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('the table plus opens creation in the right editor and cancel restores the selected record', function (string $listPage, string $createPage, string $model) {
    $record = $model::factory()->create();
    $list = Livewire::test($listPage)->callTableAction('select', $record)->searchTable('retained search');

    $list->callAction(TestAction::make('create')->table())->assertHasNoActionErrors()
        ->assertSet('isCreatingRecord', true)->assertSet('selectedRecord', (string) $record->getKey())
        ->assertSet('tableSearch', 'retained search')->assertNoRedirect()->assertDispatched('catalog-editor-opened');
    expect($list->instance()->editorComponent())->toBe($createPage);
    expect($list->instance()->mountedActions)->toBeEmpty();

    $list->call('cancelCreation')->assertSet('isCreatingRecord', false)
        ->assertSet('selectedRecord', (string) $record->getKey())->assertNoRedirect();
})->with([
    'master values' => [ListValues::class, CreateValue::class, Value::class],
    'options' => [ListOptions::class, CreateOption::class, Option::class],
    'attributes' => [ListAttributes::class, CreateAttribute::class, Attribute::class],
]);

test('embedded creation persists through the canonical handler and selects the new record without navigation', function (string $listPage, string $createPage, string $model, array $fields) {
    $list = Livewire::test($listPage)->call('createRecord');
    $editor = Livewire::test($createPage, ['embedded' => true])->fillForm($fields);

    $editor->call('create')->assertHasNoFormErrors()->assertNoRedirect()->assertNotified()
        ->assertDispatched('catalog-record-created')->assertDispatched('catalog-record-saved');
    $record = $model::query()->sole();
    $this->assertDatabaseHas($record->getTable(), $fields);
    $list->dispatch('catalog-record-created', resource: $listPage::getResource(), record: (string) $record->getKey())
        ->assertSet('isCreatingRecord', false)->assertSet('selectedRecord', (string) $record->getKey());
    $editor->call('create');
    $this->assertDatabaseCount($record->getTable(), 1);
})->with([
    'master values' => [ListValues::class, CreateValue::class, Value::class, ['label' => 'New shared meaning', 'description' => 'Exact description']],
    'attributes' => [ListAttributes::class, CreateAttribute::class, Attribute::class, ['key' => 'new-attribute', 'label' => 'New Attribute']],
]);

test('embedded option creation preserves exact code and rejects duplicate code without losing its draft', function () {
    $attribute = Attribute::factory()->create();
    $value = Value::factory()->create();
    Option::factory()->create(['code' => '0a']);
    $editor = Livewire::test(CreateOption::class, ['embedded' => true])
        ->fillForm(['attribute_id' => $attribute->id, 'value_id' => $value->id, 'code' => '0a']);

    $editor->call('create')->assertHasFormErrors(['code'])->assertNotDispatched('catalog-record-created')
        ->assertSet('data.code', '0a')->assertNoRedirect();
    $this->assertDatabaseCount('options', 1);

    $editor->fillForm(['code' => '0b'])->call('create')->assertHasNoFormErrors()->assertNoRedirect()
        ->assertDispatched('catalog-record-created');
    $this->assertDatabaseHas('options', ['attribute_id' => $attribute->id, 'value_id' => $value->id, 'code' => '0b']);
});

test('inline creation rechecks access when an admin loses permission', function () {
    $list = Livewire::test(ListValues::class);
    $editor = Livewire::test(CreateValue::class, ['embedded' => true])->fillForm(['label' => 'Forbidden value']);
    $this->actingAs(User::factory()->create(['email' => 'visitor@example.test']));

    $list->call('createRecord')->assertForbidden();
    $editor->call('create')->assertForbidden();
    $this->assertDatabaseCount('values', 0);
});

test('inline tags apply immediately while preserving scoped search and applied combined constraints', function () {
    $alpha = Value::factory()->create(['label' => 'Flange alpha', 'tags' => ['alpha']]);
    $beta = Value::factory()->create(['label' => 'Flange beta', 'tags' => ['beta']]);
    $other = Value::factory()->create(['label' => 'Flange outside', 'tags' => ['outside']]);
    $list = Livewire::test(ListValues::class)->assertSee('catalog-inline-tag-filter', false)
        ->set('tableSearchScope', 'label')->searchTable('Flange')
        ->filterTable('constraints', ['rules' => [['type' => 'label', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'alpha']]]]]);

    $list->set('tableFilters.tags.values', ['alpha', 'beta'])
        ->assertCanSeeTableRecords([$alpha])->assertCanNotSeeTableRecords([$beta, $other]);
    $list->filterTable('constraints', ['rules' => []])
        ->assertCanSeeTableRecords([$alpha, $beta])->assertCanNotSeeTableRecords([$other]);
    $list->call('resetTableFiltersForm')->assertCanSeeTableRecords([$alpha, $beta, $other]);
});

test('inline tag changes retain unfinished constraints until the native filter dialog applies them', function (string $path, mixed $value) {
    $alpha = Value::factory()->create(['label' => 'Flange alpha', 'tags' => ['metal', 'polymer']]);
    $beta = Value::factory()->create(['label' => 'Flange beta', 'tags' => ['metal', 'polymer']]);
    $pendingRules = [['type' => 'label', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'alpha']]]];
    $list = Livewire::test(ListValues::class)->set('tableFilters.tags.values', ['polymer'])
        ->set('tableDeferredFilters.constraints.rules', $pendingRules);

    $list->set($path, $value)
        ->assertSet('tableDeferredFilters.constraints.rules', $pendingRules)
        ->assertCanSeeTableRecords([$alpha, $beta]);
    $list->call('applyTableFilters')->assertCanSeeTableRecords([$alpha])->assertCanNotSeeTableRecords([$beta]);
})->with([
    'whole selection' => ['tableFilters.tags.values', ['metal']],
    'bundled item replacement' => ['tableFilters.tags.values.0', 'metal'],
]);
