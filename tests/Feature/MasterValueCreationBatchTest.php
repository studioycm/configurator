<?php

use App\Actions\CreateMasterValues;
use App\Filament\Resources\Values\Pages\EditValue;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Filament\Resources\Values\Schemas\ValueForm;
use App\Models\Attribute;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

function masterCreationRow(array $overrides = []): array
{
    return [...['ref' => 'new:'.Str::uuid(), 'label' => 'Light Blue', 'semantic_id' => 'light_blue', 'tags' => [], 'description' => null], ...$overrides];
}

test('V01 ten exact Master rows are created atomically with trimmed tags', function () {
    $rows = [];
    foreach (range(1, 10) as $i) {
        $rows[] = masterCreationRow(['label' => "Blue $i", 'semantic_id' => "blue_$i", 'tags' => [' metal ', "tag$i"], 'description' => "Specification $i"]);
    }
    $created = app(CreateMasterValues::class)->handle($this->actor, $rows);
    expect(array_keys($created))->toBe(array_column($rows, 'ref'));
    foreach ($rows as $i => $row) {
        expect($created[$row['ref']]->fresh()->only(['label', 'semantic_id', 'tags', 'description']))->toBe([...array_diff_key($row, ['ref' => true]), 'tags' => ['metal', 'tag'.($i + 1)]]);
    }
});

test('V01 V06 a later canonical error rolls back and keeps original blank row positions', function () {
    $rows = [masterCreationRow(['label' => '', 'semantic_id' => '']), masterCreationRow(['semantic_id' => 'first']), masterCreationRow(['label' => '', 'semantic_id' => '']), masterCreationRow(['semantic_id' => 'first'])];
    try {
        app(CreateMasterValues::class)->handle($this->actor, $rows);
        $this->fail('Expected duplicate identifier error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('master_values.3.semantic_id');
    }
    $this->assertDatabaseCount('values', 0);
});

test('V07 direct callers reject unknown keys and tags duplicated by trimming', function (array $overrides, string $field) {
    try {
        app(CreateMasterValues::class)->handle($this->actor, [masterCreationRow($overrides)]);
        $this->fail('Expected invalid domain shape.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
    $this->assertDatabaseCount('values', 0);
})->with([
    [['tags' => [' tag ', 'tag']], 'master_values.0.tags.0'],
    [['_suggested_semantic_id' => true], 'master_values.0'],
    [['semantic_id' => ''], 'master_values.0.semantic_id'],
]);

test('nested pools create only referenced rows and preserve source indices', function () {
    $empty = masterCreationRow(['label' => '', 'semantic_id' => '']);
    $row = masterCreationRow();
    expect(app(CreateMasterValues::class)->handle($this->actor, [$empty, $row], []))->toBe([]);
    $created = app(CreateMasterValues::class)->handle($this->actor, [$empty, $row], [$row['ref']]);
    expect(array_keys($created))->toBe([$row['ref']]);
    $this->assertDatabaseCount('values', 1);
});

test('empty batches, malformed refs and raw overflow fail without writes', function (array $rows) {
    expect(fn () => app(CreateMasterValues::class)->handle($this->actor, $rows))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('values', 0);
})->with([
    fn () => [[masterCreationRow(['label' => '', 'semantic_id' => ''])]],
    fn () => [[masterCreationRow(['ref' => 'new:bad'])]],
    fn () => [array_map(fn () => masterCreationRow(), range(1, 501))],
]);

test('operation authorization precedes input validation', function () {
    $visitor = User::factory()->create(['email' => 'visitor@example.test']);
    expect(fn () => app(CreateMasterValues::class)->handle($visitor, []))->toThrow(AuthorizationException::class);
});

test('V01 the native list action creates a batch and retains the selected editor', function () {
    $record = Value::factory()->create();
    $list = Livewire::test(ListValues::class)->callAction(TestAction::make('select')->table($record));
    $list->callAction(TestAction::make('createMany')->table(), data: ['master_values' => [masterCreationRow()]])
        ->assertHasNoFormErrors()->assertNotified()->assertSet('selectedRecord', (string) $record->id);
    $this->assertDatabaseCount('values', 2);
});

test('V03 native add five then ten preserves UUIDs manual clear and helper state', function () {
    $list = Livewire::test(ListValues::class)->mountAction(TestAction::make('createMany')->table());
    $rows = $list->get('mountedActions.0.data.master_values');
    expect($rows)->toHaveCount(1);
    $key = array_key_first($rows);
    $list->set("mountedActions.0.data.master_values.$key.label", 'First label')
        ->set("mountedActions.0.data.master_values.$key.semantic_id", '');
    $original = $list->get("mountedActions.0.data.master_values.$key");
    $list->callAction(TestAction::make('add5')->schemaComponent('master_values', 'mountedActionSchema0'));
    $afterFive = $list->get('mountedActions.0.data.master_values');
    expect($afterFive)->toHaveCount(6)->and($afterFive[$key])->toBe($original);
    $list->callAction(TestAction::make('add10')->schemaComponent('master_values', 'mountedActionSchema0'));
    $afterTen = $list->get('mountedActions.0.data.master_values');
    expect($afterTen)->toHaveCount(16)->and(array_keys($afterTen))->toContain(...array_keys($afterFive));
    foreach ($afterFive as $uuid => $row) {
        expect($afterTen[$uuid])->toBe($row);
    }
    expect(array_unique(array_column($afterTen, 'ref')))->toHaveCount(16);
});

test('V02 V06 mounted blur and suffix affect only the owning row and failure retains UUID state', function () {
    Value::factory()->create(['semantic_id' => 'light_blue']);
    $list = Livewire::test(ListValues::class)->mountAction(TestAction::make('createMany')->table())
        ->callAction(TestAction::make('add')->schemaComponent('master_values', 'mountedActionSchema0'));
    $keys = array_keys($list->get('mountedActions.0.data.master_values'));
    [$a, $b] = $keys;
    $root = 'mountedActions.0.data.master_values';
    $list->set("$root.$a.label", 'Light Blue')->assertSet("$root.$a.semantic_id", null)
        ->assertDispatched('identifier-suggested', field: "$root.$a.semantic_id", livewireId: $list->instance()->getId(), candidate: 'light_blue_2');
    /** Simulate acceptance of the asserted server candidate; real timing is covered in JS/browser. */
    $list->set("$root.$a.semantic_id", $list->get("$root.$a._identifier_semantic_id_candidate.value"));
    $list->set("$root.$b.label", 'Light Blue')->assertDispatched('identifier-suggested', field: "$root.$b.semantic_id", candidate: 'light_blue_3');
    $list->set("$root.$b.semantic_id", $list->get("$root.$b._identifier_semantic_id_candidate.value"));
    $list->set("$root.$a.semantic_id", 'manual')->set("$root.$a.label", 'Changed')->assertSet("$root.$a.semantic_id", 'manual');
    $list->callAction(TestAction::make('regenerateIdentifier')->schemaComponent("master_values.$a.semantic_id", 'mountedActionSchema0'))
        ->assertSet("$root.$a.semantic_id", 'manual')->assertSet("$root.$b.semantic_id", 'light_blue_3')
        ->assertDispatched('identifier-suggested', field: "$root.$a.semantic_id", candidate: 'changed', explicit: true);
    $list->set("$root.$a.semantic_id", $list->get("$root.$a._identifier_semantic_id_candidate.value"));
    $list->set("$root.$b.semantic_id", '')->set("$root.$b.label", 'Revised')->assertSet("$root.$b.semantic_id", '');
    $before = $list->get($root);
    $list->callMountedAction()->assertHasErrors(["$root.$b.semantic_id"])->assertNotNotified();
    expect($list->get($root))->toBe($before);
    $this->assertDatabaseCount('values', 1);
});

test('V03 495 plus five initializes only the new children and a forged over-limit call changes nothing', function () {
    $list = Livewire::test(ListValues::class)->mountAction(TestAction::make('createMany')->table());
    $rows = [];
    foreach (range(1, 495) as $i) {
        $rows[(string) Str::uuid()] = [...masterCreationRow(['label' => '', 'semantic_id' => '']), '_suggested_semantic_id' => false];
    }
    $list->set('mountedActions.0.data.master_values', $rows)
        ->callAction(TestAction::make('add5')->schemaComponent('master_values', 'mountedActionSchema0'));
    $after = $list->get('mountedActions.0.data.master_values');
    expect($after)->toHaveCount(500);
    foreach ($rows as $uuid => $row) {
        expect($after[$uuid])->toBe($row);
    }
    $rows[(string) Str::uuid()] = [...masterCreationRow(['label' => '', 'semantic_id' => '']), '_suggested_semantic_id' => false];
    $list->set('mountedActions.0.data.master_values', $rows);
    $schema = $list->instance()->getSchema('mountedActionSchema0');
    $repeater = $schema->getComponent('master_values');
    $append = new ReflectionMethod(ValueForm::class, 'appendRows');
    try {
        $append->invoke(null, $repeater, 5);
        $this->fail('Forged overflow must fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('mountedActions.0.data.master_values');
    }
    expect($list->get('mountedActions.0.data.master_values'))->toBe($rows);
});

test('V06 blank first and middle rows preserve the later operation error UUID', function () {
    $list = Livewire::test(ListValues::class)->mountAction(TestAction::make('createMany')->table());
    $rows = [];
    foreach ([['label' => '', 'semantic_id' => ''], ['semantic_id' => 'duplicate'], ['label' => '', 'semantic_id' => ''], ['semantic_id' => 'duplicate']] as $overrides) {
        $rows[(string) Str::uuid()] = [...masterCreationRow($overrides), '_suggested_semantic_id' => true];
    }
    $last = array_key_last($rows);
    $list->set('mountedActions.0.data.master_values', $rows)->callMountedAction()
        ->assertHasErrors(["mountedActions.0.data.master_values.$last.semantic_id"])->assertNotNotified();
    expect($list->get('mountedActions.0.data.master_values'))->toBe($rows);
    $this->assertDatabaseCount('values', 0);
});

test('V19 empty cancel closes and dirty discard confirmation keeps or clears its parent only', function () {
    $list = Livewire::test(ListValues::class)->mountAction(TestAction::make('createMany')->table())
        ->callAction('discardDraft');
    expect($list->get('mountedActions'))->toBe([]);
    $list->mountAction(TestAction::make('createMany')->table());
    $key = array_key_first($list->get('mountedActions.0.data.master_values'));
    $list->set("mountedActions.0.data.master_values.$key.label", 'Retain me');
    $list->mountAction('discardDraft');
    expect($list->get('mountedActions'))->toHaveCount(2);
    $list->call('unmountAction', false)->assertSet("mountedActions.0.data.master_values.$key.label", 'Retain me');
    $list->callAction('discardDraft');
    expect($list->get('mountedActions'))->toBe([]);
});

class MasterBatchParentEditor extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Attribute $record;

    public array $data = [];

    public function mount(Attribute $record): void
    {
        $this->record = $record;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->model($this->record)->operation('edit')->components([
            ValueForm::batch('master_values'),
        ]);
    }

    public function save(): void
    {
        $this->form->getState();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}<x-filament-actions::modals /></div>';
    }
}

test('V05 a nested new Master has native create context and never ignores a coincident parent ID', function () {
    $value = Value::factory()->create(['semantic_id' => 'existing']);
    $attribute = Attribute::factory()->create(['id' => $value->id]);
    $editor = Livewire::test(MasterBatchParentEditor::class, ['record' => $attribute]);
    $uuid = array_key_first($editor->get('data.master_values'));
    $editor->set("data.master_values.$uuid.label", 'Native context')->assertSet("data.master_values.$uuid.semantic_id", null)->assertDispatched('identifier-suggested', field: "data.master_values.$uuid.semantic_id", candidate: 'native_context');
    $editor->set("data.master_values.$uuid.semantic_id", 'existing')->call('save')->assertHasErrors(["data.master_values.$uuid.semantic_id"]);
    $this->assertDatabaseCount('values', 1);
    Livewire::test(EditValue::class, ['record' => $value->id])->call('save')->assertHasNoFormErrors();
});

test('native append dispatches its row identity as ordinary event data for browser focus', function () {
    Livewire::test(ListValues::class)->mountAction(TestAction::make('createMany')->table())
        ->callAction(TestAction::make('add')->schemaComponent('master_values', 'mountedActionSchema0'))
        ->assertDispatched('master-value-batch-appended', fn (string $name, array $params): bool => isset($params['rowRef']) && Str::isUuid(substr($params['rowRef'], 4)));
});

test('unreferenced rows still validate domain shape and ref namespaces', function (array $overrides) {
    expect(fn () => app(CreateMasterValues::class)->handle($this->actor, [masterCreationRow($overrides)], []))->toThrow(ValidationException::class);
})->with([[['tags' => 'wrong']], [['label' => []]], [['description' => false]], [['semantic_id' => []]]]);

test('batch rendered native table keeps helper inputs before four distinct visible cells', function () {
    $list = Livewire::test(ListValues::class)->mountAction(TestAction::make('createMany')->table());
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($list->instance()->getSchema('mountedActionSchema0')->toHtml());
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $rows = $xpath->query('//div[@data-master-value-batch]//tbody/tr');
    expect($rows)->toHaveCount(1);
    $fields = [];
    foreach ($xpath->query('./td', $rows->item(0)) as $cell) {
        $field = $xpath->query('.//*[@data-batch-field]', $cell)->item(0);
        if ($field) {
            $fields[] = $field->getAttribute('data-batch-field');
        }
    }
    expect($fields)->toBe(['label', 'semantic_id', 'tags', 'description']);
    expect($xpath->query('./input[@type="hidden"]', $rows->item(0)))->toHaveCount(4);
});

test('batch native controls keep explicit accessible names when column labels are visually hidden', function () {
    $list = Livewire::test(ListValues::class)->mountAction(TestAction::make('createMany')->table());
    $document = new DOMDocument;
    $previousErrors = libxml_use_internal_errors(true);
    $document->loadHTML($list->instance()->getSchema('mountedActionSchema0')->toHtml());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    $xpath = new DOMXPath($document);
    foreach (['label' => 'UI Name', 'semantic_id' => 'ID Name', 'tags' => 'Tags', 'description' => 'Specification'] as $field => $label) {
        $wrapper = $xpath->query('//*[@data-batch-field="'.$field.'"]')->item(0);
        $control = $xpath->query('.//input[not(@type="hidden")]|.//textarea', $wrapper)->item(0);
        expect($control->getAttribute('aria-label'))->toBe($label);
        expect($xpath->query('.//label[@for="'.$control->getAttribute('id').'"]', $wrapper))->toHaveCount(1);
    }
    expect($xpath->query('//thead/tr/th'))->toHaveCount(5);
});

test('V02 one request proposes distinct reserved identifiers without writing editable IDs', function () {
    Value::factory()->create(['semantic_id' => 'light_blue']);
    $list = Livewire::test(ListValues::class)->mountAction(TestAction::make('createMany')->table())
        ->callAction(TestAction::make('add')->schemaComponent('master_values', 'mountedActionSchema0'));
    [$a, $b] = array_keys($list->get('mountedActions.0.data.master_values'));
    $root = 'mountedActions.0.data.master_values';

    $list->update(updates: ["$root.$a.label" => 'Light Blue', "$root.$b.label" => 'Light Blue']);

    $list->assertSet("$root.$a.semantic_id", null)->assertSet("$root.$b.semantic_id", null)
        ->assertSet("$root.$a._suggested_semantic_id", false)
        ->assertDispatched('identifier-suggested', field: "$root.$a.semantic_id", candidate: 'light_blue_2', explicit: false)
        ->assertDispatched('identifier-suggested', field: "$root.$b.semantic_id", candidate: 'light_blue_3', explicit: false);
    $this->assertDatabaseCount('values', 1);
});
