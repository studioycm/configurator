<?php

use App\Filament\Resources\BatchPreviewPresentation;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Models\Group;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('selection scope counts all matches across pages without discarding hidden selected records', function () {
    $outside = Value::factory()->create(['label' => 'Copper']);
    $inside = Value::factory()->create(['label' => 'Steel Z']);
    Value::factory()->count(5)->create(['label' => 'Steel A']);
    $component = Livewire::test(ListValues::class)->selectTableRecords([$outside, $inside])->sortTable('label')->set('tableRecordsPerPage', 5)->searchTable('Steel')->assertCanNotSeeTableRecords([$inside]);

    $component->call('workspaceSelectionScope', [(string) $outside->id, (string) $inside->id])
        ->assertReturned(['total' => 2, 'inside' => 1, 'outside' => 1]);
    $component->assertSet('selectedTableRecords', [(string) $outside->id, (string) $inside->id]);
    $component->mountAction(TestAction::make('batchEdit')->table()->bulk());
    $instance = $component->instance();
    expect($instance->getTable()->getBulkAction('batchEdit')->getModalDescription())->toContain('All 2 selected records', '1 outside current results');
    $component->call('unmountAction')->assertSet('selectedTableRecords', [(string) $outside->id, (string) $inside->id]);
    $component->searchTable('')->call('workspaceSelectionScope', [(string) $outside->id, (string) $inside->id])
        ->assertReturned(['total' => 2, 'inside' => 2, 'outside' => 0]);
});

test('changed deferred intent invalidates the old preview before any Apply writes', function () {
    $value = Value::factory()->create(['label' => 'Original']);
    $component = Livewire::test(ListValues::class)->selectTableRecords([$value]);
    $component->mountAction(TestAction::make('batchReplace')->table()->bulk())
        ->fillForm(['operation' => 'replace', 'field' => 'label', 'mode' => 'literal', 'search' => 'Original', 'replacement' => 'Reviewed', 'case_sensitive' => true, 'occurrences' => 'all', 'flags' => ''])
        ->callMountedAction(['preview' => true])->assertHasNoFormErrors();
    expect($component->get('batchPreview.token'))->not->toBeEmpty();

    $component->set('mountedActions.0.data.replacement', 'Changed after preview')->assertSet('batchPreviewStale', true);
    $component->callMountedAction()->assertHasErrors(['preview'])->assertNotDispatched('deselectAllTableRecords');
    expect($value->fresh()->label)->toBe('Original');
    $component->assertSet('mountedActions.0.data.replacement', 'Changed after preview');
    $component->callMountedAction(['preview' => true])->assertHasNoFormErrors()->assertSet('batchPreviewStale', false);
    $component->callMountedAction()->assertHasNoFormErrors();
    expect($value->fresh()->label)->toBe('Changed after preview');
});

test('changing selected records invalidates preview and never broadens Apply to an unreviewed record', function () {
    $first = Value::factory()->create(['label' => 'First']);
    $second = Value::factory()->create(['label' => 'Second']);
    $component = Livewire::test(ListValues::class)->selectTableRecords([$first]);
    $component->mountAction(TestAction::make('batchEdit')->table()->bulk())
        ->fillForm(['operation' => 'edit', 'changes' => [['field' => 'label', 'mode' => 'set', 'value' => 'Reviewed']]])
        ->callMountedAction(['preview' => true])->assertHasNoFormErrors();

    $component->set('selectedTableRecords', [(string) $first->id, (string) $second->id])->assertSet('batchPreviewStale', true);
    $component->callMountedAction()->assertHasErrors(['preview']);
    expect([$first->fresh()->label, $second->fresh()->label])->toBe(['First', 'Second']);
});

test('bulk preview puts the one changed Group field first and retains the original payload without writes', function () {
    $group = Group::factory()->create(['description' => 'Saved description']);
    $component = Livewire::test(ListGroups::class)->selectTableRecords([$group]);
    $component->mountAction(TestAction::make('batchEdit')->table()->bulk())
        ->fillForm(['operation' => 'edit', 'changes' => [['field' => 'cards_per_row', 'mode' => 'set', 'value' => 3]]])
        ->callMountedAction(['preview' => true])->assertHasNoFormErrors();
    $html = $component->instance()->getSchema($component->instance()->getMountedActionSchemaName())->toHtml();
    expect($html)->toContain('Changed fields', 'Cards Per Row', 'Full original data');
    $row = $component->get('batchPreview.rows.0');
    $fields = BatchPreviewPresentation::differences($row, $component->get('batchPreview.form_state'));
    expect(array_column($fields, 'field'))->toBe(['cards_per_row']);
    expect($row['before']['description'])->toBe('Saved description');
    expect($row['after']['cards_per_row'])->toBe(3);
    expect($group->fresh()->description)->toBe('Saved description');
    expect($group->fresh()->result_settings)->toBe($group->result_settings);
});

test('preview display preserves clear null empty text list ordering types and unchanged distinctions', function () {
    $row = ['before' => ['description' => 'Text', 'hint' => null, 'tags' => ['B', 'A'], 'name' => 'Same', 'label' => 0],
        'after' => ['description' => null, 'hint' => '', 'tags' => ['A', 'B'], 'name' => 'Same', 'label' => '0']];
    $intent = ['operation' => 'edit', 'changes' => [['field' => 'description', 'mode' => 'clear'], ['field' => 'hint', 'mode' => 'set'], ['field' => 'name', 'mode' => 'unchanged']]];
    $fields = BatchPreviewPresentation::differences($row, $intent);
    expect(array_column($fields, 'field'))->toBe(['description', 'hint', 'tags', 'label']);
    expect($fields[0]['mode'])->toBe(__('admin.batch.choices.clear'));
    expect($fields[2]['before'])->toBe(['B', 'A'])->and($fields[2]['after'])->toBe(['A', 'B']);
    $format = BatchPreviewPresentation::value(...);
    expect([$format(null), $format(''), $format([]), $format(0), $format('0'), $format(false), $format('false')])->toHaveCount(7);
    expect(count(array_unique([$format(null), $format(''), $format([]), $format(0), $format('0'), $format(false), $format('false')])))->toBe(7);
    expect(BatchPreviewPresentation::differences(['before' => $row['before'], 'after' => $row['before']], $intent))->toBe([]);
});

test('removal previews keep blockers and identify removal separately from clearing a field', function () {
    $value = Value::factory()->create(['label' => 'Retained master value']);
    Option::factory()->for($value)->create();
    $component = Livewire::test(ListValues::class)->selectTableRecords([$value])
        ->mountAction(TestAction::make('batchRemove')->table()->bulk())
        ->callMountedAction(['preview' => true])->assertHasNoFormErrors();
    $html = $component->instance()->getSchema($component->instance()->getMountedActionSchemaName())->toHtml();
    expect($html)->toContain('Remove this record', 'Full original data', '1 record is blocked');
    expect($component->get('batchPreview.blocked_count'))->toBe(1);
    expect($component->get('batchPreview.rows.0.after'))->toBe([]);
    expect($value->fresh()->label)->toBe('Retained master value');
});
