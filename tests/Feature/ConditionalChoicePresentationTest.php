<?php

use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Models\Group;
use App\Models\Product;
use App\Models\SubGroup;
use App\Models\User;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function conditionalChoiceField(Testable $editor, string $path, string $schema = 'form'): Field
{
    $field = collect($editor->instance()->{$schema}->getFlatFields(withHidden: true))->first(fn (Field $field): bool => $field->getStatePath() === $path);
    expect($field)->toBeInstanceOf(Field::class);

    return $field;
}

test('preset choices use buttons for short sets and retain searchable selection for larger or long sets', function (array $values, string $expected) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create();
    foreach ($values as $value) {
        Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => $value]]);
    }
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'allowed_values' => [$values[0]]]);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $key = array_key_first($editor->get('data.catalog_settings.sub_groups'));

    $field = conditionalChoiceField($editor, 'data.catalog_settings.sub_groups.'.$key.'.allowed_values');
    expect($field)->toBeInstanceOf($expected);
    expect($field->isMultiple())->toBeTrue();
    expect($field->getOptions())->toHaveCount(count($values));
    expect($field instanceof Select ? $field->isSearchable() : ! $field->isGrouped())->toBeTrue();

    expect($preset->fresh()->allowed_values)->toBe([$values[0]]);
})->with([
    'small set' => [['10', '16', '25'], ToggleButtons::class],
    'six short values' => [['1', '2', '3', '4', '5', '6'], ToggleButtons::class],
    'more than six values' => [['1', '2', '3', '4', '5', '6', '7'], Select::class],
    'long source label' => [[str_repeat('Long pressure label ', 4)], Select::class],
]);

test('preset selection keeps exact strings, saved stale values and order through representation changes and save', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '001', 'Other' => '9']]);
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'allowed_values' => ['stale', '001']]);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $key = array_key_first($editor->get('data.catalog_settings.sub_groups'));
    $path = 'catalog_settings.sub_groups.'.$key.'.allowed_values';

    expect(conditionalChoiceField($editor, 'data.'.$path))->toBeInstanceOf(ToggleButtons::class);
    $editor->set('data.catalog_settings.sub_groups.'.$key.'.property_key', 'Other');
    $field = conditionalChoiceField($editor, 'data.'.$path);
    expect($field)->toBeInstanceOf(Select::class);
    expect($field->getState())->toBe(['stale', '001']);
    expect($field->getOptionLabels())->toBe(['stale' => 'Unavailable choice: stale', '001' => 'Unavailable choice: 001']);
    expect($field->getOptions())->toBe([9 => '9']);
    $editor->call('save')->assertHasErrors(['data.'.$path.'.0' => 'in']);
    expect($preset->fresh()->property_key)->toBe('Working_Pressure');
    $editor->set('data.catalog_settings.sub_groups.'.$key.'.property_key', 'Working_Pressure');
    expect(conditionalChoiceField($editor, 'data.'.$path))->toBeInstanceOf(ToggleButtons::class);
    $editor->call('save')->assertHasNoFormErrors();

    expect($preset->fresh()->allowed_values)->toBe(['stale', '001']);
});

test('preset choices save additions and removals with exact strings and selection order', function (array $values) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create();
    foreach ($values as $value) {
        Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => $value]]);
    }
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'allowed_values' => [$values[0]]]);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $key = array_key_first($editor->get('data.catalog_settings.sub_groups'));
    $path = 'data.catalog_settings.sub_groups.'.$key.'.allowed_values';

    $editor->set($path, ['003', '002'])->call('save')->assertHasNoFormErrors();

    expect($preset->fresh()->allowed_values)->toBe(['003', '002']);
    $fresh = Livewire::test(EditGroup::class, ['record' => $group->id]);
    expect(collect($fresh->get('data.catalog_settings.sub_groups'))->sole()['allowed_values'])->toBe(['003', '002']);
})->with([
    'buttons' => [['001', '002', '003']],
    'searchable select' => [['001', '002', '003', '004', '005', '006', '007']],
]);

test('invalid preset choices reject save and preserve the saved preset and group', function (array $selection) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create(['name' => 'Original']);
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '001']]);
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'allowed_values' => ['001']]);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $key = array_key_first($editor->get('data.catalog_settings.sub_groups'));

    $editor->set('data.name', 'Draft')->set('data.catalog_settings.sub_groups.'.$key.'.allowed_values', $selection)
        ->call('save')->assertHasErrors()->assertSet('data.name', 'Draft');

    expect($preset->fresh()->allowed_values)->toBe(['001']);
    expect($group->fresh()->name)->toBe('Original');
})->with([
    'empty choices' => [[]],
    'unavailable choice' => [['unknown']],
    'duplicate choice' => [['001', '001']],
]);
