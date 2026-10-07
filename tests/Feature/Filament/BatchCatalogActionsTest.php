<?php

use App\Actions\BatchCatalogChanges;
use App\Actions\SaveCanonicalDefinition;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Options\Pages\EditOption;
use App\Filament\Resources\Values\Pages\EditValue;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Option;
use App\Models\Product;
use App\Models\SubGroup;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('mixed batch edits preserve untouched fields and reject a stale preview', function () {
    $first = Value::factory()->create(['label' => 'First', 'description' => 'One']);
    $second = Value::factory()->create(['label' => 'Second', 'description' => 'Two']);
    $action = app(BatchCatalogChanges::class);
    $intent = ['operation' => 'edit', 'changes' => [['field' => 'description', 'mode' => 'clear']]];
    $preview = $action->preview(auth()->user(), 'values', [$first->id, $second->id], $intent);
    $second->update(['description' => 'Concurrent']);
    expect(fn () => $action->apply(auth()->user(), 'values', [$first->id, $second->id], $intent, $preview['token']))->toThrow(ValidationException::class);
    expect($first->fresh()->description)->toBe('One');
    $preview = $action->preview(auth()->user(), 'values', [$first->id, $second->id], $intent);
    $action->apply(auth()->user(), 'values', [$first->id, $second->id], $intent, $preview['token']);
    expect($first->fresh()->label)->toBe('First')->and($second->fresh()->label)->toBe('Second')->and($first->fresh()->description)->toBeNull()->and($second->fresh()->description)->toBeNull();
});

test('invalid later row blocks the whole selected set and unknown fields are rejected', function () {
    $first = Attribute::factory()->create(['label' => 'A']);
    $second = Attribute::factory()->create(['label' => str_repeat('Z', 255)]);
    $action = app(BatchCatalogChanges::class);
    $intent = ['operation' => 'replace', 'field' => 'label', 'mode' => 'literal', 'search' => 'Z', 'replacement' => 'ZZ', 'case_sensitive' => true, 'occurrences' => 'all', 'flags' => ''];
    expect(fn () => $action->preview(auth()->user(), 'attributes', [$first->id, $second->id], $intent))->toThrow(ValidationException::class);
    expect($first->fresh()->label)->toBe('A')->and($second->fresh()->label)->toBe(str_repeat('Z', 255));
    expect(fn () => $action->preview(auth()->user(), 'attributes', [$first->id], ['operation' => 'edit', 'changes' => [['field' => 'id', 'mode' => 'set', 'value' => 999]]]))->toThrow(ValidationException::class);
});

test('Group batch presentation preserves all card settings and related lists', function () {
    $group = Group::factory()->create(['result_settings' => ['card_show_labels' => false, 'card_property_layout' => 'above_start', 'card_padding_inline' => 11]]);
    $action = app(BatchCatalogChanges::class);
    $intent = ['operation' => 'edit', 'changes' => [['field' => 'card_show_labels', 'mode' => 'set', 'value' => true]]];
    $preview = $action->preview(auth()->user(), 'groups', [$group->id], $intent);
    $action->apply(auth()->user(), 'groups', [$group->id], $intent, $preview['token']);
    expect($group->fresh()->result_settings['card_show_labels'])->toBeTrue()->and($group->fresh()->result_settings['card_property_layout'])->toBe('above_start')->and($group->fresh()->result_settings['card_padding_inline'])->toBe(11);
    $this->actingAs(User::factory()->create());
    expect(fn () => $action->preview(auth()->user(), 'groups', [$group->id], $intent))->toThrow(AuthorizationException::class);
});

test('the native bulk dialog previews without saving then applies the reviewed fields', function () {
    $value = Value::factory()->create(['label' => 'Original', 'description' => 'Untouched']);
    $component = Livewire::test(ListValues::class)->selectTableRecords([$value]);
    $data = ['operation' => 'edit', 'changes' => [['field' => 'label', 'mode' => 'set', 'value' => 'Reviewed']]];
    $component->mountAction(TestAction::make('batchEdit')->table()->bulk())->fillForm($data)->callMountedAction(['preview' => true])->assertHasNoActionErrors()->assertNotDispatched('deselectAllTableRecords');
    $bulk = $component->instance()->getTable()->getBulkAction('batchEdit');
    expect($bulk->getLabel())->toBe('Edit selected');
    expect($bulk->getModalSubmitAction())->not->toBe($bulk);
    expect($bulk->getModalSubmitAction()->getLabel())->toBe('Apply previewed changes');
    expect($value->fresh()->label)->toBe('Original');
    expect($component->get('batchPreview.token'))->not->toBeEmpty();
    $component->callMountedAction()->assertHasNoActionErrors()->assertDispatched('deselectAllTableRecords');
    expect($value->fresh()->label)->toBe('Reviewed')->and($value->fresh()->description)->toBe('Untouched');
});

test('replacement detects collisions within the selection before saving', function () {
    $first = Option::factory()->create(['code' => 'A1']);
    $second = Option::factory()->create(['code' => 'B1']);
    $intent = ['operation' => 'replace', 'field' => 'code', 'mode' => 'regex', 'search' => '[AB]', 'replacement' => 'C', 'case_sensitive' => true, 'occurrences' => 'all', 'flags' => ''];
    expect(fn () => app(BatchCatalogChanges::class)->preview(auth()->user(), 'options', [$first->id, $second->id], $intent))->toThrow(ValidationException::class);
    expect($first->fresh()->code)->toBe('A1')->and($second->fresh()->code)->toBe('B1');
});

test('blocked bulk removal explains exact categories and cannot remove a valid subset', function () {
    $used = Attribute::factory()->create();
    $free = Attribute::factory()->create();
    Option::factory()->for($used)->create();
    $action = app(BatchCatalogChanges::class);
    $intent = ['operation' => 'remove'];
    $preview = $action->preview(auth()->user(), 'attributes', [$used->id, $free->id], $intent);
    expect($preview['blocked_count'])->toBe(1)->and($preview['blockers'][0])->toMatchArray(['category' => 'options', 'count' => 1, 'parent_id' => $used->id, 'list_key' => 'canonical-attribute-options']);
    expect(fn () => $action->apply(auth()->user(), 'attributes', [$used->id, $free->id], $intent, $preview['token']))->toThrow(ValidationException::class);
    expect(Attribute::whereKey([$used->id, $free->id])->count())->toBe(2);
});

test('a dependency added after bulk preview refreshes blockers and keeps the reviewed selection', function () {
    $attribute = Attribute::factory()->create();
    $component = Livewire::test(ListAttributes::class)->selectTableRecords([$attribute])
        ->mountAction(TestAction::make('batchRemove')->table()->bulk())
        ->callMountedAction(['preview' => true])->assertHasNoActionErrors();
    expect($component->get('batchPreview.blockers'))->toBe([]);
    Option::factory()->for($attribute)->create();
    $component->callMountedAction()->assertHasActionErrors()->assertNotDispatched('deselectAllTableRecords');
    expect($component->get('batchPreview.blockers')[0])->toMatchArray(['category' => 'options', 'count' => 1]);
    expect($component->get('selectedTableRecords'))->toContain((string) $attribute->id);
    expect($attribute->fresh())->not->toBeNull();
});

test('batch tags are trimmed and local batch edits reject foreign owners', function () {
    $value = Value::factory()->create(['tags' => ['first']]);
    $action = app(BatchCatalogChanges::class);
    $intent = ['operation' => 'edit', 'changes' => [['field' => 'tags', 'mode' => 'add', 'value' => [' second ']]]];
    $preview = $action->preview(auth()->user(), 'values', [$value->id], $intent);
    $action->apply(auth()->user(), 'values', [$value->id], $intent, $preview['token']);
    expect($value->fresh()->tags)->toBe(['first', 'second']);
    $inclusion = ConfiguratorAttribute::factory()->create();
    $foreign = Configurator::factory()->create();
    expect(fn () => $action->preview(auth()->user(), 'configurator-attributes', [$inclusion->id], ['operation' => 'edit', 'changes' => [['field' => 'help_text', 'mode' => 'clear']]], $foreign->id))->toThrow(ValidationException::class);
});

test('local batch previews identify inclusions by their effective shared label', function () {
    $attribute = ConfiguratorAttribute::factory()->create(['label_override' => null]);
    $preview = app(BatchCatalogChanges::class)->preview(auth()->user(), 'configurator-attributes', [$attribute->id], ['operation' => 'edit', 'changes' => [['field' => 'help_text', 'mode' => 'clear']]], $attribute->configurator_id);
    expect($preview['rows'][0]['name'])->toBe($attribute->attribute->label);
});

test('shared code replacement previews the affected existing Configurators', function () {
    $inclusion = ConfiguratorOption::factory()->create();
    $owner = $inclusion->configuratorAttribute->configurator;
    $option = $inclusion->option;
    $preview = app(BatchCatalogChanges::class)->preview(auth()->user(), 'options', [$option->id], ['operation' => 'edit', 'changes' => [['field' => 'code', 'mode' => 'set', 'value' => 'Z9']]]);
    expect($preview['rows'][0]['impact']['count'])->toBe(1)
        ->and($preview['rows'][0]['impact']['owners'])->toBe([['id' => $owner->id, 'name' => $owner->name]]);
    expect($option->fresh()->code)->not->toBe('Z9');
});

test('a later domain rejection rolls back all earlier writes in the batch', function () {
    $first = Value::factory()->create(['description' => 'First original']);
    $second = Value::factory()->create(['description' => 'Second original']);
    $intent = ['operation' => 'edit', 'changes' => [['field' => 'description', 'mode' => 'clear']]];
    $batch = app(BatchCatalogChanges::class);
    $preview = $batch->preview(auth()->user(), 'values', [$first->id, $second->id], $intent);
    $realSave = app(SaveCanonicalDefinition::class);
    $this->mock(SaveCanonicalDefinition::class)->shouldReceive('handle')->twice()->andReturnUsing(function ($actor, $record, $data) use ($first, $second, $realSave) {
        if ($record->id === $second->id) {
            expect($first->fresh()->description)->toBeNull();
            throw ValidationException::withMessages(['definition' => 'Concurrent domain failure.']);
        }

        return $realSave->handle($actor, $record, $data);
    });
    expect(fn () => $batch->apply(auth()->user(), 'values', [$first->id, $second->id], $intent, $preview['token']))->toThrow(ValidationException::class);
    expect($first->fresh()->description)->toBe('First original')->and($second->fresh()->description)->toBe('Second original');
});

test('Group description batches also support branches and preserve saved filter and preset rows', function () {
    $branch = Group::factory()->create(['description' => 'Branch before']);
    Group::factory()->create(['parent_id' => $branch->id]);
    $leaf = Group::factory()->create(['description' => 'Leaf before']);
    Product::factory()->for($leaf)->create(['properties' => ['Working_Pressure' => '10']]);
    $filter = GroupFilter::factory()->for($leaf)->create(['property_key' => 'Working_Pressure', 'value_order' => ['10'], 'value_labels' => ['10' => 'Ten']]);
    $preset = SubGroup::factory()->for($leaf)->create(['property_key' => 'Working_Pressure', 'allowed_values' => ['10']]);
    $before = [$filter->fresh()->getAttributes(), $preset->fresh()->getAttributes(), $leaf->fresh()->result_settings];
    $intent = ['operation' => 'edit', 'changes' => [['field' => 'description', 'mode' => 'set', 'value' => 'Updated']]];
    $batch = app(BatchCatalogChanges::class);
    $preview = $batch->preview(auth()->user(), 'groups', [$branch->id, $leaf->id], $intent);
    $batch->apply(auth()->user(), 'groups', [$branch->id, $leaf->id], $intent, $preview['token']);
    expect($branch->fresh()->description)->toBe('Updated')->and($leaf->fresh()->description)->toBe('Updated');
    expect([$filter->fresh()->getAttributes(), $preset->fresh()->getAttributes(), $leaf->fresh()->result_settings])->toBe($before);
});

test('presentation batches keep unselected saved vocabulary choices instead of expanding them', function () {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10']]);
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '20']]);
    $filter = GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'value_order' => ['10'], 'value_labels' => ['10' => 'Ten']]);
    $before = $filter->fresh()->getAttributes();
    $intent = ['operation' => 'edit', 'changes' => [['field' => 'card_show_labels', 'mode' => 'set', 'value' => true]]];
    $batch = app(BatchCatalogChanges::class);
    $preview = $batch->preview(auth()->user(), 'groups', [$group->id], $intent);
    $batch->apply(auth()->user(), 'groups', [$group->id], $intent, $preview['token']);
    expect($filter->fresh()->getAttributes())->toBe($before)->and($group->fresh()->result_settings['card_show_labels'])->toBeTrue();
});

test('shared resource editors keep their draft and reject saving over a batch change', function (string $kind) {
    [$record, $page, $table, $field, $value, $draft] = match ($kind) {
        'value' => [Value::factory()->create(['label' => 'Before']), EditValue::class, 'values', 'label', 'Batch label', ['description' => 'Keep this draft']],
        'attribute' => [Attribute::factory()->create(['label' => 'Before']), EditAttribute::class, 'attributes', 'label', 'Batch label', ['key' => 'changed_key']],
        'option' => [Option::factory()->create(['code' => 'Q1']), EditOption::class, 'options', 'code', 'Z9', ['code' => 'U9']],
        'group' => [Group::factory()->create(['description' => 'Before']), EditGroup::class, 'groups', 'description', 'Batch description', ['name' => 'Keep draft name']],
    };
    $editor = Livewire::test($page, ['record' => $record->id])->fillForm($draft);
    $intent = ['operation' => 'edit', 'changes' => [['field' => $field, 'mode' => 'set', 'value' => $value]]];
    $batch = app(BatchCatalogChanges::class);
    $preview = $batch->preview(auth()->user(), $table, [$record->id], $intent);
    $batch->apply(auth()->user(), $table, [$record->id], $intent, $preview['token']);
    $editor->dispatch('catalog-batch-applied', model: $record::class, ids: [$record->id])->assertSet('batchEditorStale', true)
        ->call('save')->assertHasFormErrors();
    foreach ($draft as $key => $draftValue) {
        $editor->assertSet('data.'.$key, $draftValue);
    }
    expect($record->fresh()->getAttribute($field))->toBe($value);
    $editor->call('reloadBatchEditor')->assertSet('batchEditorStale', false)->assertSet('data.'.$field, $value);
})->with(['value', 'attribute', 'option', 'group']);

test('clean shared editors reload batch values and subsequent ordinary saves retain them', function () {
    $value = Value::factory()->create(['label' => 'Before']);
    $editor = Livewire::test(EditValue::class, ['record' => $value->id]);
    $value->update(['label' => 'Batch label']);
    $editor->dispatch('catalog-batch-applied', model: Value::class, ids: [$value->id])->assertSet('data.label', 'Batch label');
    $editor->fillForm(['description' => 'After batch'])->call('save')->assertHasNoFormErrors();
    expect($value->fresh()->label)->toBe('Batch label')->and($value->fresh()->description)->toBe('After batch');
    $editor->fillForm(['description' => 'Second ordinary save'])->call('save')->assertHasNoFormErrors();
    expect($value->fresh()->description)->toBe('Second ordinary save');
});
