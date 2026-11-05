<?php

use App\Filament\Resources\Values\Pages\ListValues;
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
