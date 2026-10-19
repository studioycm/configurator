<?php

use App\Filament\Pages\ManageTags;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Livewire\Catalog\ItemListDrawer;
use App\Livewire\Catalog\TagValueEditor;
use App\Livewire\Catalog\TagValues;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('tag operators match any or all tags without changing the selected tags', function (string $surface) {
    $both = Value::factory()->create(['tags' => ['metal', 'standard']]);
    $one = Value::factory()->create(['tags' => ['metal']]);
    $other = Value::factory()->create(['tags' => ['plastic']]);
    $bothOption = Option::factory()->for($both)->create();
    $oneOption = Option::factory()->for($one)->create();
    $otherOption = Option::factory()->for($other)->create();
    [$component, $matches, $partial, $excluded] = $surface === 'values'
        ? [ListValues::class, $both, $one, $other]
        : [ListOptions::class, $bothOption, $oneOption, $otherOption];

    $page = Livewire::test($component)->set('tableFilters.tags.values', ['metal', 'standard'])
        ->assertCanSeeTableRecords([$matches, $partial])->assertCanNotSeeTableRecords([$excluded]);
    $page->call('toggleTagFilterOperator')->assertSet('tableFilters.tags.operator', 'and')
        ->assertSet('tableFilters.tags.values', ['metal', 'standard'])
        ->assertCanSeeTableRecords([$matches])->assertCanNotSeeTableRecords([$partial, $excluded]);
    $page->call('toggleTagFilterOperator')->assertSet('tableFilters.tags.operator', 'or')
        ->assertCanSeeTableRecords([$matches, $partial]);
})->with(['values', 'options']);

test('legacy tag visibility preferences cannot hide tags or discard other saved display settings', function () {
    Value::factory()->create(['tags' => ['metal', 'standard']]);

    $page = Livewire::test(ListValues::class)->call('restoreTableBrowserProfile', [
        'tagFilters' => [], 'perPage' => 25, 'filters' => ['tags' => ['values' => ['metal'], 'operator' => 'and']],
    ])->assertHasNoErrors()->assertSet('tableRecordsPerPage', 25)
        ->assertSet('tableFilters.tags.operator', 'and');

    expect(array_keys($page->instance()->inlineTagsForm->getFlatFields()['values']->getOptions()))->toBe(['metal', 'standard']);
    $page->assertDontSee('Visible tags, in display order')->assertDontSee('Clear tags');
});

test('tag rows open the split editor without count links or a separate Values action', function () {
    Value::factory()->create(['tags' => ['metal']]);

    $page = Livewire::test(ManageTags::class)->call('selectTag', hash('sha256', 'metal'))
        ->assertSet('selectedTag', 'metal')->assertSeeHtml('catalog-tag-workspace')
        ->assertDontSee('View Master Values');

    expect($page->instance()->getTable()->getRecordAction(['tag' => 'metal', 'usage' => 1]))->toBe('selectTag');
    expect($page->instance()->getTable()->getColumn('usage')->record(['tag' => 'metal', 'usage' => 1])->getUrl())->toBeNull();
});

test('inline tag saves preserve other Value fields and stay inside the workspace', function () {
    $value = Value::factory()->create(['tags' => ['metal']]);
    $before = $value->only(['label', 'semantic_id', 'description']);

    Livewire::test(TagValueEditor::class, ['valueId' => $value->id, 'tag' => 'metal'])
        ->fillForm(['tags' => ['metal', 'standard']])->call('save')->assertHasNoErrors()
        ->assertDispatched('catalog-tags-updated')->assertNoRedirect();

    expect($value->fresh()->tags)->toBe(['metal', 'standard']);
    expect($value->fresh()->only(array_keys($before)))->toBe($before);
});

test('an inline tag edit refuses to overwrite a concurrent tag change', function () {
    $value = Value::factory()->create(['tags' => ['metal']]);
    $editor = Livewire::test(TagValueEditor::class, ['valueId' => $value->id, 'tag' => 'metal'])
        ->fillForm(['tags' => ['metal', 'standard']]);
    $value->update(['tags' => ['metal', 'concurrent']]);

    $editor->call('save')->assertHasErrors(['data.tags']);

    expect($value->fresh()->tags)->toBe(['metal', 'concurrent']);
});

test('bulk tag edits apply only to reviewed workspace Values', function (string $mode, array $tags, array $expected) {
    $first = Value::factory()->create(['tags' => ['metal', 'standard']]);
    $second = Value::factory()->create(['tags' => ['metal', 'standard']]);
    $other = Value::factory()->create(['tags' => ['outside']]);

    $page = Livewire::test(TagValues::class, ['tag' => 'metal'])
        ->selectTableRecords([$first, $second])
        ->callAction(TestAction::make('editTags')->table()->bulk())
        ->fillForm(['mode' => $mode, 'tags' => $tags], 'assignmentForm')
        ->call('refreshAssignmentPreview')->assertHasNoErrors();
    expect($first->fresh()->tags)->toBe(['metal', 'standard']);
    $page->call('applyAssignment')->assertHasNoErrors()->assertDispatched('catalog-tags-updated')->assertNoRedirect();

    expect($first->fresh()->tags)->toBe($expected);
    expect($second->fresh()->tags)->toBe($expected);
    expect($other->fresh()->tags)->toBe(['outside']);
})->with([
    ['add', ['special'], ['metal', 'standard', 'special']],
    ['remove', ['standard'], ['metal']],
    ['set', ['special'], ['special']],
    ['set', [], []],
]);

test('the embedded picker adds existing Values without leaving the tag workspace', function () {
    $tagged = Value::factory()->create(['tags' => ['metal']]);
    $other = Value::factory()->create(['tags' => ['standard']]);

    Livewire::test(TagValues::class, ['tag' => 'metal'])->call('showPicker')
        ->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$tagged])
        ->selectTableRecords([$other])->callAction(TestAction::make('assignTag')->table()->bulk())
        ->assertHasNoErrors()->assertSet('picking', false)->assertSet('tag', 'metal')->assertNoRedirect();

    expect($other->fresh()->tags)->toBe(['standard', 'metal']);
});

test('new shared tags are created by assigning selected Values inside the workspace', function () {
    $value = Value::factory()->create(['tags' => ['standard']]);

    Livewire::test(TagValues::class)->fillForm(['name' => 'new-tag'], 'nameForm')
        ->selectTableRecords([$value])->callAction(TestAction::make('assignTag')->table()->bulk())
        ->assertHasNoErrors()->assertSet('tag', 'new-tag')->assertSet('picking', false)
        ->assertDispatched('tag-workspace-selected', tag: 'new-tag')->assertNoRedirect();

    expect($value->fresh()->tags)->toBe(['standard', 'new-tag']);
});

test('the related Options of one Master Value have no redundant tags filter', function (string $key) {
    $value = Value::factory()->create(['tags' => ['metal']]);
    Option::factory()->for($value)->create();

    $page = Livewire::test(ItemListDrawer::class, ['listKey' => $key, 'parentId' => $value->id]);

    expect($page->instance()->getTable()->getFilter('tags'))->toBeNull();
    $page->assertDontSeeHtml('catalog-inline-tag-filter');
})->with(['value-options', 'canonical-value-options']);

test('tag editors authorize saves again after access is revoked', function () {
    $value = Value::factory()->create(['tags' => ['metal']]);
    $page = Livewire::test(TagValueEditor::class, ['valueId' => $value->id, 'tag' => 'metal'])
        ->fillForm(['tags' => ['changed']]);
    auth()->user()->update(['email' => 'forbidden@example.test']);

    $page->call('save')->assertForbidden();

    expect($value->fresh()->tags)->toBe(['metal']);
});

test('inline editors cannot mount a Value outside the selected tag', function () {
    $value = Value::factory()->create(['tags' => ['other']]);

    Livewire::test(TagValueEditor::class, ['valueId' => $value->id, 'tag' => 'metal'])->assertNotFound();
});

test('cancelling a stale row reloads its current tags so editing can continue', function () {
    $value = Value::factory()->create(['tags' => ['metal']]);
    $page = Livewire::test(TagValueEditor::class, ['valueId' => $value->id, 'tag' => 'metal'])
        ->fillForm(['tags' => ['metal', 'pending']]);
    $value->update(['tags' => ['metal', 'concurrent']]);

    $page->call('cancel')->assertSet('data.tags', ['metal', 'concurrent'])->assertHasNoErrors();
});

test('row editors have separate input identifiers for accessible tag labels', function () {
    $first = Value::factory()->create(['tags' => ['metal']]);
    $second = Value::factory()->create(['tags' => ['metal']]);
    $one = Livewire::test(TagValueEditor::class, ['valueId' => $first->id, 'tag' => 'metal']);
    $two = Livewire::test(TagValueEditor::class, ['valueId' => $second->id, 'tag' => 'metal']);

    expect($one->instance()->form->getFlatFields()['tags']->getId())
        ->not->toBe($two->instance()->form->getFlatFields()['tags']->getId());
});

test('tag mutation events refresh clean editors and preserve dirty row drafts', function () {
    $value = Value::factory()->create(['tags' => ['metal']]);
    $clean = Livewire::test(TagValueEditor::class, ['valueId' => $value->id, 'tag' => 'metal']);
    $dirty = Livewire::test(TagValueEditor::class, ['valueId' => $value->id, 'tag' => 'metal'])
        ->fillForm(['tags' => ['metal', 'draft']]);
    $value->update(['tags' => ['metal', 'bulk']]);

    $clean->dispatch('catalog-tags-updated')->assertSet('data.tags', ['metal', 'bulk'])
        ->assertSet('originalTags', ['metal', 'bulk'])
        ->fillForm(['tags' => ['metal', 'bulk', 'next']])->call('save')->assertHasNoErrors();
    $dirty->dispatch('catalog-tags-updated')->assertSet('data.tags', ['metal', 'draft'])
        ->assertSet('originalTags', ['metal'])->call('save')->assertHasErrors(['data.tags']);

    expect($value->fresh()->tags)->toBe(['metal', 'bulk', 'next']);
});
