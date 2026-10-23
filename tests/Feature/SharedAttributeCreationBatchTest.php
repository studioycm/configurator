<?php

use App\Actions\CreateSharedAttributes;
use App\Filament\Forms\Components\WorkspaceTableSelect;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Components\Tabs;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

function sharedAttributeRow(array $overrides = []): array
{
    return [...['ref' => 'new:'.Str::uuid(), 'label' => 'Material', 'key' => 'material', 'values' => []], ...$overrides];
}

function sharedMasterRow(array $overrides = []): array
{
    return [...['ref' => 'new:'.Str::uuid(), 'label' => 'Blue', 'semantic_id' => 'blue', 'tags' => [], 'description' => null], ...$overrides];
}

function sharedAssociation(array $overrides = []): array
{
    return [...['ref' => 'new:'.Str::uuid(), 'code' => 'Aa'], ...$overrides];
}

test('V11 native create and use validates only its composer and retains asymmetric drafts without writes', function () {
    $list = Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table());
    $attributes = $list->get('mountedActions.0.data.attributes');
    $a = array_key_first($attributes);
    $b = (string) Str::uuid();
    $refA = $attributes[$a]['ref'];
    $list->set("mountedActions.0.data.attributes.$a.label", 'First')
        ->set("mountedActions.0.data.attributes.$a.key", 'first')
        ->set("mountedActions.0.data.attributes.$b", sharedAttributeRow(['label' => 'Participating unfinished', 'key' => '', '_suggested_key' => true]))
        ->callAction(TestAction::make('openValues')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0'));
    $composerKey = (string) Str::uuid();
    $row = sharedMasterRow();
    $list->set("mountedActions.0.data.attributes.$a._master_composer", [$composerKey => $row])
        ->callAction(TestAction::make('createAndUse')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0'))
        ->assertHasNoFormErrors();
    expect(Value::count())->toBe(0)->and(Attribute::count())->toBe(0)->and(Option::count())->toBe(0);
    $pool = $list->get('mountedActions.0.data.master_values');
    expect(array_column($pool, 'ref'))->toBe([$row['ref']]);
    $values = $list->get("mountedActions.0.data.attributes.$a.values");
    expect(array_values($values)[0]['value_ref'])->toBe($row['ref']);
    expect($list->get("mountedActions.0.data.attributes.$a.ref"))->toBe($refA)
        ->and($list->get("mountedActions.0.data.attributes.$b.label"))->toBe('Participating unfinished');
    $cleared = sharedMasterRow(['semantic_id' => '', '_suggested_semantic_id' => true]);
    $list->set("mountedActions.0.data.attributes.$a._master_composer", [$composerKey => $cleared])
        ->callAction(TestAction::make('createAndUse')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0'))
        ->assertHasErrors(["mountedActions.0.data.attributes.$a._master_composer.$composerKey.semantic_id"]);
    expect($list->get("mountedActions.0.data.attributes.$a._master_composer.$composerKey"))->toBe($cleared);
    $list->call('callMountedAction')->assertHasFormErrors();
    $valueKey = array_key_first($values);
    $list->set("mountedActions.0.data.attributes.$a._master_composer", [])
        ->set("mountedActions.0.data.attributes.$a.values.$valueKey.code", 'G1')
        ->call('callMountedAction')->assertHasFormErrors(["attributes.$b.key"]);
    expect(Value::count())->toBe(0)->and(Attribute::count())->toBe(0);
});

test('V08 shared Attributes may have no Values and ten exact definitions save', function () {
    $rows = array_map(fn (int $i): array => sharedAttributeRow(['label' => "Definition $i", 'key' => "legacy-key-$i"]), range(1, 10));
    $saved = app(CreateSharedAttributes::class)->handle($this->actor, $rows);
    expect(array_keys($saved))->toBe(array_column($rows, 'ref'));
    foreach ($rows as $row) {
        expect($saved[$row['ref']]->fresh()->only(['label', 'key']))->toBe(['label' => $row['label'], 'key' => $row['key']]);
    }
    expect(Option::count())->toBe(0);
});

test('V09 one root pending Master is reused under two Attributes with exact case sensitive Codes', function () {
    $master = sharedMasterRow();
    $rows = [sharedAttributeRow(['values' => [sharedAssociation(['value_ref' => $master['ref']])]]), sharedAttributeRow(['label' => 'Color', 'key' => 'color', 'values' => [sharedAssociation(['value_ref' => $master['ref'], 'code' => 'aA'])]])];
    $saved = app(CreateSharedAttributes::class)->handle($this->actor, $rows, [$master]);
    expect(Value::count())->toBe(1)->and(Option::count())->toBe(2);
    expect($saved[$rows[0]['ref']]->options()->sole()->only(['value_id', 'code']))->toBe(['value_id' => Value::sole()->id, 'code' => 'Aa']);
    expect($saved[$rows[1]['ref']]->options()->sole()->only(['value_id', 'code']))->toBe(['value_id' => Value::sole()->id, 'code' => 'aA']);
});

test('V06 V09 final Code failure rolls back every canonical row and retains source indices', function () {
    $master = sharedMasterRow();
    $blank = sharedAttributeRow(['label' => '', 'key' => '']);
    $rows = [$blank, sharedAttributeRow(['values' => [sharedAssociation(['value_ref' => $master['ref']])]]), sharedAttributeRow(['key' => 'second', 'values' => [sharedAssociation(['value_ref' => $master['ref']])]])];
    try {
        app(CreateSharedAttributes::class)->handle($this->actor, $rows, [$master]);
        $this->fail('Expected final duplicate Code.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('attributes.2.values.0.code');
    }
    expect(Value::count())->toBe(0)->and(Attribute::count())->toBe(0)->and(Option::count())->toBe(0);
});

test('V05 V11 mounted nested composer suggestions and invalid refs remain scoped to their native row', function () {
    Value::factory()->create(['semantic_id' => 'persisted_blue']);
    $list = Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table());
    $a = array_key_first($list->get('mountedActions.0.data.attributes'));
    $list->callAction(TestAction::make('openValues')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0'));
    $composer = array_key_first($list->get("mountedActions.0.data.attributes.$a._master_composer"));
    $path = "mountedActions.0.data.attributes.$a._master_composer.$composer";
    $list->set("$path.label", 'New native blue')->assertSet("$path.semantic_id", null)->assertDispatched('identifier-suggested', field: "$path.semantic_id", candidate: 'new_native_blue');
    $list->callAction(TestAction::make('regenerateIdentifier')->schemaComponent("attributes.$a.attribute-values._master_composer.$composer.semantic_id", 'mountedActionSchema0'))
        ->assertSet("$path.semantic_id", null)->assertDispatched('identifier-suggested', field: "$path.semantic_id", candidate: 'new_native_blue');
    $list->set("$path.semantic_id", 'persisted_blue')
        ->callAction(TestAction::make('createAndUse')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0'))
        ->assertHasErrors(["$path.semantic_id"]);
    $list->set("$path.semantic_id", 'valid_blue')->set("$path.ref", 'new:forged')
        ->callAction(TestAction::make('createAndUse')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0'))
        ->assertHasErrors(["$path.ref"])->assertSet("$path.ref", 'new:forged');
    expect(Value::count())->toBe(1)->and(Attribute::count())->toBe(0);
});

test('V09 mounted final callback projects helpers and creates a reused Master once', function () {
    $master = sharedMasterRow();
    $a = (string) Str::uuid();
    $b = (string) Str::uuid();
    $rows = [$a => [...sharedAttributeRow(['values' => [(string) Str::uuid() => sharedAssociation(['value_ref' => $master['ref']])]]), '_suggested_key' => true], $b => sharedAttributeRow(['key' => 'other', 'values' => [(string) Str::uuid() => sharedAssociation(['value_ref' => $master['ref'], 'code' => 'aA'])]])];
    Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table())
        ->set('mountedActions.0.data.master_values', [(string) Str::uuid() => $master])
        ->set('mountedActions.0.data.attributes', $rows)->callMountedAction()->assertHasNoFormErrors()->assertNotified();
    expect(Value::count())->toBe(1)->and(Attribute::count())->toBe(2)->and(Option::count())->toBe(2);
});

test('V06 mounted operation failure keeps blank and removed native row identities and exact source key', function () {
    $value = Value::factory()->create();
    $a = (string) Str::uuid();
    $blank = (string) Str::uuid();
    $b = (string) Str::uuid();
    $association = (string) Str::uuid();
    $rows = [$blank => sharedAttributeRow(['label' => '', 'key' => '']), $a => sharedAttributeRow(['values' => [(string) Str::uuid() => sharedAssociation(['value_id' => $value->id])]]), $b => sharedAttributeRow(['key' => 'second', 'values' => [$association => sharedAssociation(['value_id' => $value->id])]])];
    $list = Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table())
        ->set('mountedActions.0.data.attributes', $rows)->callMountedAction()
        ->assertHasErrors(["mountedActions.0.data.attributes.$b.values.$association.code"])->assertNotNotified();
    expect($list->get('mountedActions.0.data.attributes'))->toBe($rows);
    unset($rows[$blank]);
    $list->set('mountedActions.0.data.attributes', $rows)->callMountedAction()
        ->assertHasErrors(["mountedActions.0.data.attributes.$b.values.$association.code"]);
    expect($list->get('mountedActions.0.data.attributes'))->toBe($rows)->and(Attribute::count())->toBe(0)->and(Option::count())->toBe(0);
});

test('V19 only meaningful scalar drafts require confirmation and Keep editing retains inactive composer data', function () {
    $list = Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table())->callAction('discardDraft');
    expect($list->get('mountedActions'))->toBe([]);
    $list->mountAction(TestAction::make('createMany')->table());
    $a = array_key_first($list->get('mountedActions.0.data.attributes'));
    $row = sharedMasterRow(['semantic_id' => '', '_suggested_semantic_id' => true]);
    $list->set("mountedActions.0.data.attributes.$a._master_composer", ['native-key' => $row])
        ->mountAction('discardDraft');
    expect($list->get('mountedActions'))->toHaveCount(2);
    $list->call('unmountAction', false)->assertSet("mountedActions.0.data.attributes.$a._master_composer.native-key", $row);
    $list->callAction('discardDraft');
    expect($list->get('mountedActions'))->toBe([]);
});

test('V08 standalone all-empty definitions fail while optional unreferenced pool rows are not inserted', function () {
    expect(fn () => app(CreateSharedAttributes::class)->handle($this->actor, [sharedAttributeRow(['label' => ' ', 'key' => ''])]))->toThrow(ValidationException::class);
    $saved = app(CreateSharedAttributes::class)->handle($this->actor, [sharedAttributeRow()], [sharedMasterRow(['semantic_id' => ''])]);
    expect($saved)->toHaveCount(1)->and(Value::count())->toBe(0);
});

test('V10 exact invalid Codes are rejected without changing existing records', function (string $code) {
    $value = Value::factory()->create();
    $row = sharedAttributeRow(['values' => [sharedAssociation(['value_id' => $value->id, 'code' => $code])]]);
    try {
        app(CreateSharedAttributes::class)->handle($this->actor, [$row]);
        $this->fail('Expected exact Code validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('attributes.0.values.0.code');
    }
    expect(Attribute::count())->toBe(0)->and(Option::count())->toBe(0)->and(Value::count())->toBe(1);
})->with([' A', 'A ', 'A!', 'éA', 'A', 'Ab1']);

test('V09 V10 duplicate Attribute Keys and duplicate Master pairs roll back the complete batch', function (string $failure) {
    $value = Value::factory()->create();
    $values = [sharedAssociation(['value_id' => $value->id, 'code' => 'A1'])];
    if ($failure === 'pair') {
        $values[] = sharedAssociation(['value_id' => $value->id, 'code' => 'A2']);
    }
    $rows = [sharedAttributeRow(['values' => $values])];
    if ($failure === 'key') {
        $rows[] = sharedAttributeRow();
    }
    expect(fn () => app(CreateSharedAttributes::class)->handle($this->actor, $rows))->toThrow(ValidationException::class);
    expect(Attribute::count())->toBe(0)->and(Option::count())->toBe(0);
})->with(['pair', 'key']);

test('V06 the complete Master pool retains unreferenced and blank source positions', function () {
    $invalid = sharedMasterRow(['semantic_id' => 'Invalid id']);
    $pool = [sharedMasterRow(['label' => '', 'semantic_id' => '']), sharedMasterRow(['semantic_id' => 'not_selected']), $invalid];
    try {
        app(CreateSharedAttributes::class)->handle($this->actor, [sharedAttributeRow(['values' => [sharedAssociation(['value_ref' => $invalid['ref']])]])], $pool);
        $this->fail('Expected original pool position.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('master_values.2.semantic_id');
    }
    expect(Value::count())->toBe(0)->and(Attribute::count())->toBe(0);
});

test('V10 direct operations reject unknown keys invalid identities and cross Attribute association refs', function (Closure $rows) {
    $value = Value::factory()->create();
    expect(fn () => app(CreateSharedAttributes::class)->handle($this->actor, $rows($value)))->toThrow(ValidationException::class);
    expect(Attribute::count())->toBe(0)->and(Option::count())->toBe(0);
})->with([
    'Attribute helper' => [fn (Value $value): array => [sharedAttributeRow(['_suggested_key' => true])]],
    'association helper' => [fn (Value $value): array => [sharedAttributeRow(['values' => [sharedAssociation(['value_id' => $value->id, 'name' => 'untrusted'])]])]],
    'both sources' => [fn (Value $value): array => [sharedAttributeRow(['values' => [sharedAssociation(['value_id' => $value->id, 'value_ref' => 'new:'.Str::uuid()])]])]],
    'boolean ID' => [fn (Value $value): array => [sharedAttributeRow(['values' => [sharedAssociation(['value_id' => true])]])]],
    'zero ID' => [fn (Value $value): array => [sharedAttributeRow(['values' => [sharedAssociation(['value_id' => 0])]])]],
    'foreign ref' => [fn (Value $value): array => [sharedAttributeRow(['values' => [sharedAssociation(['value_ref' => 'new:'.Str::uuid()])]])]],
    'shared association ref' => [function (Value $value): array {
        $ref = 'new:'.Str::uuid();

        return [sharedAttributeRow(['values' => [sharedAssociation(['ref' => $ref, 'value_id' => $value->id])]]), sharedAttributeRow(['key' => 'second', 'values' => [sharedAssociation(['ref' => $ref, 'value_id' => $value->id, 'code' => 'aA'])]])];
    }],
]);

test('V08 new Attribute submission authorizes before database validation and rejects revoked permissions', function () {
    $visitor = User::factory()->create();
    expect(fn () => app(CreateSharedAttributes::class)->handle($visitor, []))->toThrow(AuthorizationException::class);
    $list = Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table());
    Gate::define('manage-catalog', fn () => false);
    $list->callMountedAction()->assertForbidden();
    expect(Attribute::count())->toBe(0);
});

test('V12 native picker state survives owner switches while staged IDs refresh exclusions', function () {
    $value = Value::factory()->create();
    $list = Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table())
        ->callAction(TestAction::make('add')->schemaComponent('attributes', 'mountedActionSchema0'));
    [$a, $b] = array_keys($list->get('mountedActions.0.data.attributes'));
    $openA = TestAction::make('openValues')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0');
    $openB = TestAction::make('openValues')->schemaComponent("attributes.$b.attribute-values", 'mountedActionSchema0');
    $list->callAction($openA)->assertSet("mountedActions.0.data.attributes.$a._selected_value_ids", [])
        ->set("mountedActions.0.data.attributes.$a._selected_value_ids", [$value->id])->callAction($openB)->callAction($openA)
        ->assertSet("mountedActions.0.data.attributes.$a._selected_value_ids", [$value->id])
        ->callAction(TestAction::make('useSelected')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0'));
    $fields = collect($list->instance()->getSchema('mountedActionSchema0')->getFlatComponents())->filter(fn ($field): bool => $field instanceof WorkspaceTableSelect);
    expect($fields)->toHaveCount(1)->and($fields->first()->getTableArguments()['excluded_ids'])->toBe([$value->id]);
    $list->callAction($openB)->callAction($openA);
    $field = collect($list->instance()->getSchema('mountedActionSchema0')->getFlatComponents())->first(fn ($field): bool => $field instanceof WorkspaceTableSelect);
    expect($field->getTableArguments()['excluded_ids'])->toBe([$value->id]);
});

test('V02 V11 composer manual IDs collide with the pending pool at the current native row', function () {
    $list = Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table());
    $a = array_key_first($list->get('mountedActions.0.data.attributes'));
    $list->set('mountedActions.0.data.master_values', [(string) Str::uuid() => sharedMasterRow(['semantic_id' => 'reserved_blue'])])
        ->callAction(TestAction::make('openValues')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0'));
    $key = array_key_first($list->get("mountedActions.0.data.attributes.$a._master_composer"));
    $path = "mountedActions.0.data.attributes.$a._master_composer.$key";
    $list->set("$path.label", 'Reserved Blue')->assertSet("$path.semantic_id", null)->assertDispatched('identifier-suggested', field: "$path.semantic_id", candidate: 'reserved_blue_2')
        ->set("$path.semantic_id", 'reserved_blue')
        ->callAction(TestAction::make('createAndUse')->schemaComponent("attributes.$a.attribute-values", 'mountedActionSchema0'))
        ->assertHasErrors(["$path.semantic_id"])->assertSet("$path.semantic_id", 'reserved_blue');
    expect($list->get('mountedActions.0.data.master_values'))->toHaveCount(1)->and(Value::count())->toBe(0);
});

test('native Value source tabs keep contextual staging actions and hide empty pending content', function () {
    $list = Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table());
    $a = array_key_first($list->get('mountedActions.0.data.attributes'));
    $scope = "attributes.$a.attribute-values";
    $list->callAction(TestAction::make('openValues')->schemaComponent($scope, 'mountedActionSchema0'))
        ->assertActionDisabled(TestAction::make('useSelected')->schemaComponent($scope, 'mountedActionSchema0'))
        ->assertActionHidden(TestAction::make('discardComposer')->schemaComponent($scope, 'mountedActionSchema0'));
    $schema = $list->instance()->getSchema('mountedActionSchema0');
    $tabs = $schema->getComponent(fn ($component): bool => $component instanceof Tabs);
    expect($tabs)->not->toBeNull();
    expect(array_map(fn ($tab): string => $tab->getLabel(), $tabs->getChildSchema()->getComponents()))->toBe(['Existing Master Values', 'New Master Values']);
    expect($schema->getComponentByStatePath('master_values', withHidden: true)->isHidden())->toBeTrue();
    expect($schema->getComponentByStatePath("attributes.$a._selected_pending", withHidden: true)->isHidden())->toBeTrue();
    $key = array_key_first($list->get("mountedActions.0.data.attributes.$a._master_composer"));
    $list->set("mountedActions.0.data.attributes.$a._master_composer.$key.label", 'Composer only')
        ->assertActionVisible(TestAction::make('discardComposer')->schemaComponent($scope, 'mountedActionSchema0'));
    $before = $list->get("mountedActions.0.data.attributes.$a");
    $list->mountAction(TestAction::make('discardComposer')->schemaComponent($scope, 'mountedActionSchema0'));
    expect($list->get('mountedActions'))->toHaveCount(2);
    $list->call('unmountAction', false);
    expect($list->get("mountedActions.0.data.attributes.$a"))->toBe($before);
    $list->callAction(TestAction::make('discardComposer')->schemaComponent($scope, 'mountedActionSchema0'))
        ->assertSet("mountedActions.0.data.attributes.$a._master_composer", []);
    expect(Attribute::count())->toBe(0)->and(Value::count())->toBe(0);
});

test('V02 same-request Attribute labels reserve proposed Keys without changing editable Keys', function () {
    Attribute::factory()->create(['key' => 'valve_seal']);
    $list = Livewire::test(ListAttributes::class)->mountAction(TestAction::make('createMany')->table())
        ->callAction(TestAction::make('add')->schemaComponent('attributes', 'mountedActionSchema0'));
    [$a, $b] = array_keys($list->get('mountedActions.0.data.attributes'));
    $root = 'mountedActions.0.data.attributes';

    $list->update(updates: ["$root.$a.label" => 'Valve seal', "$root.$b.label" => 'Valve seal']);

    $list->assertSet("$root.$a.key", null)->assertSet("$root.$b.key", null)
        ->assertDispatched('identifier-suggested', field: "$root.$a.key", candidate: 'valve_seal_2')
        ->assertDispatched('identifier-suggested', field: "$root.$b.key", candidate: 'valve_seal_3');
    expect(Attribute::count())->toBe(1);
});
