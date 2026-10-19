<?php

use App\Actions\AddAttributeValues;
use App\Filament\Forms\Components\WorkspaceTableSelect;
use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

test('an Attribute is created with the selected Values and exact Codes', function () {
    [$first, $second] = Value::factory()->count(2)->create()->all();
    Livewire::test(CreateAttribute::class)->fillForm([
        'key' => 'batch_material', 'label' => 'Material',
        'pending_options' => [['value_id' => $first->id, 'code' => 'Aa'], ['value_id' => $second->id, 'code' => 'aA']],
    ])->call('create')->assertHasNoFormErrors();
    $attribute = Attribute::where('key', 'batch_material')->sole();
    expect($attribute->options()->orderBy('id')->get()->map->only(['value_id', 'code'])->all())->toBe([
        ['value_id' => $first->id, 'code' => 'Aa'], ['value_id' => $second->id, 'code' => 'aA'],
    ]);
});

test('an invalid batch creates neither the Attribute nor any Options', function () {
    Option::factory()->create(['code' => 'Z9']);
    $values = Value::factory()->count(2)->create();
    Livewire::test(CreateAttribute::class)->fillForm([
        'key' => 'invalid_batch', 'label' => 'Material',
        'pending_options' => [['value_id' => $values[0]->id, 'code' => 'A1'], ['value_id' => $values[1]->id, 'code' => 'Z9']],
    ])->call('create')->assertHasFormErrors();
    expect(Attribute::where('key', 'invalid_batch')->exists())->toBeFalse()->and(Option::count())->toBe(1);
});

test('editing an Attribute saves its draft and selected Options in one batch', function () {
    $attribute = Attribute::factory()->create();
    $value = Value::factory()->create();
    Livewire::test(EditAttribute::class, ['record' => $attribute->id])->fillForm([
        'label' => 'Changed label', 'pending_options' => [['value_id' => $value->id, 'code' => 'B1']],
    ])->call('save')->assertHasNoFormErrors()->assertSet('data.pending_options', []);
    expect($attribute->fresh()->label)->toBe('Changed label')->and($attribute->options()->sole()->value_id)->toBe($value->id);
});

test('batch validation rejects duplicate Values and Codes without partial writes', function (array $duplicate) {
    $attribute = Attribute::factory()->create();
    $values = Value::factory()->count(2)->create();
    $entries = [['value_id' => $values[0]->id, 'code' => 'A1'], ['value_id' => $values[$duplicate['value']]->id, 'code' => $duplicate['code']]];
    expect(fn () => app(AddAttributeValues::class)->handle($this->actor, $attribute, $entries))->toThrow(ValidationException::class);
    expect($attribute->options()->count())->toBe(0);
})->with([
    'duplicate Value' => [['value' => 0, 'code' => 'A2']],
    'duplicate Code' => [['value' => 1, 'code' => 'A1']],
]);

test('batch additions reject an already attached Value and unauthorized actors', function () {
    $existing = Option::factory()->create(['code' => 'X1']);
    expect(fn () => app(AddAttributeValues::class)->handle($this->actor, $existing->attribute, [['value_id' => $existing->value_id, 'code' => 'X2']]))->toThrow(ValidationException::class);
    expect(fn () => app(AddAttributeValues::class)->handle(User::factory()->create(), $existing->attribute, []))->toThrow(AuthorizationException::class);
    expect($existing->attribute->options()->count())->toBe(1);
});

test('reopening Add Values excludes staged Values and preserves their entered Codes', function (bool $editing) {
    $attribute = Attribute::factory()->create();
    $value = Value::factory()->create();
    $page = Livewire::test($editing ? EditAttribute::class : CreateAttribute::class, $editing ? ['record' => $attribute->id] : []);
    $action = TestAction::make('addValues')->schemaComponent('attribute-values', 'form');
    $page->callAction($action, ['values' => [$value->id]])->assertHasNoFormErrors()
        ->set('data.pending_options.0.code', 'R1')->mountAction($action)->assertHasNoFormErrors();
    $field = collect($page->instance()->getSchema($page->instance()->getMountedActionSchemaName())->getFlatComponents())->first(fn ($component): bool => $component instanceof WorkspaceTableSelect);
    expect($field->getTableArguments()['excluded_ids'])->toContain($value->id);
    $page->call('unmountAction')->assertSet('data.pending_options.0.code', 'R1');
})->with(['create' => false, 'edit' => true]);

test('a late invalid Option rolls back an Attribute definition update', function () {
    $attribute = Attribute::factory()->create(['label' => 'Original']);
    $value = Value::factory()->create();
    expect(fn () => app(AddAttributeValues::class)->saveDefinition($this->actor, $attribute, [
        'key' => $attribute->key, 'label' => 'Changed',
        'pending_options' => [['value_id' => $value->id, 'code' => '?']],
    ]))->toThrow(ValidationException::class);
    expect($attribute->fresh()->label)->toBe('Original')->and($attribute->options()->count())->toBe(0);
});
