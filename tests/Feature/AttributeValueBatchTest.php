<?php

use App\Actions\AddAttributeValues;
use App\Filament\Forms\Components\WorkspaceTableSelect;
use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Attributes\Schemas\AttributeForm;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
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

test('reopening the inline chooser excludes staged Values and preserves their entered Codes', function (bool $editing) {
    $attribute = Attribute::factory()->create();
    $value = Value::factory()->create();
    $page = Livewire::test($editing ? EditAttribute::class : CreateAttribute::class, $editing ? ['record' => $attribute->id] : []);
    $open = TestAction::make('openValues')->schemaComponent('attribute-values', 'form');
    $page->callAction($open);
    $page->set('data._selected_value_ids', [$value->id])
        ->callAction(TestAction::make('useSelected')->schemaComponent('attribute-values', 'form'))->assertHasNoFormErrors();
    $key = array_key_first($page->get('data.pending_options'));
    $page->set("data.pending_options.$key.code", 'R1')->callAction($open)->assertHasNoFormErrors();
    $field = collect($page->instance()->form->getFlatComponents())->first(fn ($component): bool => $component instanceof WorkspaceTableSelect);
    expect($field->getTableArguments()['excluded_ids'])->toContain($value->id);
    $page->assertSet("data.pending_options.$key.code", 'R1');
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

test('V13 an existing definition and ref-based pending Master save atomically through the optional pool', function () {
    $attribute = Attribute::factory()->create(['label' => 'Original']);
    $ref = 'new:'.Str::uuid();
    $master = ['ref' => $ref, 'label' => 'Pool Blue', 'semantic_id' => 'pool_blue', 'tags' => [' tag '], 'description' => 'Exact specification'];
    $association = ['ref' => 'new:'.Str::uuid(), 'value_ref' => $ref, 'code' => 'P1'];
    app(AddAttributeValues::class)->saveDefinition($this->actor, $attribute, ['label' => 'Changed', 'key' => $attribute->key, 'pending_options' => [$association]], [$master]);
    expect($attribute->fresh()->label)->toBe('Changed')->and($attribute->options()->sole()->value->only(['label', 'semantic_id', 'tags', 'description']))->toBe(['label' => 'Pool Blue', 'semantic_id' => 'pool_blue', 'tags' => ['tag'], 'description' => 'Exact specification']);
});

test('V06 V13 optional pool errors retain source indices and definition rollback', function () {
    $attribute = Attribute::factory()->create(['label' => 'Original']);
    $ref = 'new:'.Str::uuid();
    $pool = [['ref' => 'new:'.Str::uuid(), 'label' => '', 'semantic_id' => ''], ['ref' => $ref, 'label' => 'Bad', 'semantic_id' => 'Bad ID']];
    try {
        app(AddAttributeValues::class)->saveDefinition($this->actor, $attribute, ['label' => 'Changed', 'key' => $attribute->key, 'pending_options' => [['ref' => 'new:'.Str::uuid(), 'value_ref' => $ref, 'code' => 'X1']]], $pool);
        $this->fail('Expected indexed Master error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('master_values.1.semantic_id');
    }
    expect($attribute->fresh()->label)->toBe('Original')->and(Value::count())->toBe(0)->and(Option::count())->toBe(0);
});

test('V13 a late association conflict rolls back the optional Master pool and canonical edit', function () {
    Option::factory()->create(['code' => 'Z9']);
    $attribute = Attribute::factory()->create(['label' => 'Original']);
    $ref = 'new:'.Str::uuid();
    expect(fn () => app(AddAttributeValues::class)->saveDefinition($this->actor, $attribute, ['label' => 'Changed', 'key' => $attribute->key, 'pending_options' => [['ref' => 'new:'.Str::uuid(), 'value_ref' => $ref, 'code' => 'Z9']]], [['ref' => $ref, 'label' => 'New', 'semantic_id' => 'new_master']]))->toThrow(ValidationException::class);
    expect($attribute->fresh()->label)->toBe('Original')->and(Value::where('semantic_id', 'new_master')->exists())->toBeFalse()->and(Option::count())->toBe(1);
});

test('V05 V13 direct Attribute editors reuse the native composer without a parent unique exception', function (bool $editing) {
    $existing = Value::factory()->create(['semantic_id' => 'occupied']);
    $attribute = Attribute::factory()->create(['id' => $existing->id]);
    $page = Livewire::test($editing ? EditAttribute::class : CreateAttribute::class, $editing ? ['record' => $attribute->id] : []);
    if (! $editing) {
        $page->fillForm(['label' => 'Direct new', 'key' => 'direct_new']);
    }
    $page->callAction(TestAction::make('openValues')->schemaComponent('attribute-values', 'form'));
    $key = array_key_first($page->get('data._master_composer'));
    $page->set("data._master_composer.$key.label", 'Direct Blue')->assertSet("data._master_composer.$key.semantic_id", null)->assertDispatched('identifier-suggested', field: "data._master_composer.$key.semantic_id", candidate: 'direct_blue')
        ->set("data._master_composer.$key.semantic_id", 'occupied')
        ->callAction(TestAction::make('createAndUse')->schemaComponent('attribute-values', 'form'))->assertHasErrors(["data._master_composer.$key.semantic_id"]);
    $page->set("data._master_composer.$key.semantic_id", 'direct_blue')->callAction(TestAction::make('createAndUse')->schemaComponent('attribute-values', 'form'))->assertHasNoFormErrors();
    $association = array_key_first($page->get('data.pending_options'));
    $page->set("data.pending_options.$association.code", 'D1')->call($editing ? 'save' : 'create')->assertHasNoFormErrors();
    expect(Value::where('semantic_id', 'direct_blue')->sole()->options()->sole()->code)->toBe('D1');
    if ($editing) {
        $page->assertSet('data.pending_options', [])->assertSet('data.master_values', [])->assertSet('data._master_composer', []);
    }
})->with(['new' => false, 'existing' => true]);

test('contextual footer buttons use their rendered native context in direct and batch workspaces', function (bool $batch) {
    $attribute = Attribute::factory()->create();
    $page = $batch ? Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table()) : Livewire::test(EditAttribute::class, ['record' => $attribute->id]);
    $schemaName = $batch ? 'mountedActionSchema0' : 'form';
    $row = $batch ? array_key_first($page->get('mountedActions.0.data.attributes')) : null;
    $scope = $batch ? "attributes.$row.attribute-values" : 'attribute-values';
    $root = $batch ? "mountedActions.0.data.attributes.$row" : 'data';
    $page->callAction(TestAction::make('openValues')->schemaComponent($scope, $schemaName));
    $key = array_key_first($page->get("$root._master_composer"));
    $page->set("$root._master_composer.$key.label", 'Footer Blue')
        ->assertSet("$root._master_composer.$key.semantic_id", null)
        ->assertDispatched('identifier-suggested', field: "$root._master_composer.$key.semantic_id", candidate: 'footer_blue');
    /** Simulate the browser accepting the scoped candidate; PHP does not execute Alpine. */
    $page->set("$root._master_composer.$key.semantic_id", $page->get("$root._master_composer.$key._identifier_semantic_id_candidate.value"));
    $section = $page->instance()->getSchema($schemaName)->getComponent($scope);
    $action = $section->getAction('createAndUse');
    $page->call('mountAction', $action->getName(), [], $action->getContext())->assertHasNoFormErrors();
    expect($page->get("$root._master_composer"))->toBe([])->and($page->get($batch ? 'mountedActions.0.data.master_values' : 'data.master_values'))->toHaveCount(1);
    expect(Value::count())->toBe(0)->and(Option::count())->toBe(0);
    $page->set("$root._selected_value_ids", [Value::factory()->create()->id]);
    $section = $page->instance()->getSchema($schemaName)->getComponent($scope);
    $action = $section->getAction('useSelected');
    $page->call('mountAction', $action->getName(), [], $action->getContext())->assertHasNoFormErrors();
    expect($page->get($root.($batch ? '.values' : '.pending_options')))->toHaveCount(2);
    expect(Option::count())->toBe(0);
})->with(['direct edit' => false, 'mounted batch' => true]);

test('Attribute draft comparison preserves exact native identities and fields without changing raw helpers', function () {
    $state = ['label' => 'Exact Label ', 'key' => '', 'pending_options' => ['association-uuid' => ['ref' => 'new:association', 'value_ref' => 'new:master', 'code' => ' A', 'name' => 'Derived']], 'master_values' => ['master-uuid' => ['ref' => 'new:master', 'label' => 'Name', 'semantic_id' => '', 'tags' => ['0'], 'description' => ' ', '_suggested_semantic_id' => true]], '_master_composer' => ['blank-uuid' => ['ref' => 'new:blank', 'label' => '', 'semantic_id' => null, 'tags' => [], 'description' => ' ']], '_selected_value_ids' => [19], '_selected_pending' => ['new:master'], '_workspace_open' => true];
    $before = $state;
    expect(AttributeForm::editorDraft($state))->toBe([
        'label' => 'Exact Label ', 'key' => '',
        'pending_options' => [['association-uuid', ['ref' => 'new:association', 'value_id' => null, 'value_ref' => 'new:master', 'code' => ' A']]],
        'master_values' => [['master-uuid', ['ref' => 'new:master', 'label' => 'Name', 'semantic_id' => '', 'tags' => ['0'], 'description' => ' ']]],
        '_master_composer' => [], '_selected_value_ids' => [19], '_selected_pending' => ['new:master'],
    ])->and($state)->toBe($before);
});

test('Attribute composer and pool population retain PHP trim semantics and exact raw text', function (string $value, bool $meaningful) {
    $state = ['label' => 'Label', 'key' => 'key', '_master_composer' => ['uuid' => ['ref' => 'new:composer', 'description' => $value]], 'master_values' => ['pool-uuid' => ['ref' => 'new:master', 'tags' => [$value]]]];
    $before = $state;
    $draft = AttributeForm::editorDraft($state);
    expect($draft['_master_composer'])->toHaveCount($meaningful ? 1 : 0)
        ->and($draft['master_values'])->toHaveCount($meaningful ? 1 : 0)
        ->and($state)->toBe($before);
    if ($meaningful) {
        expect($draft['_master_composer'][0][1]['description'])->toBe($value)
            ->and($draft['master_values'][0][1]['tags'])->toBe([$value]);
    }
})->with(['nonbreaking space' => ["\u{00a0}", true], 'EM SPACE' => ["\u{2003}", true], 'NUL' => ["\0", false], 'PHP trim characters' => [" \t\n\r\0\x0B", false]]);
