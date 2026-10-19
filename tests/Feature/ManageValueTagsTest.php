<?php

use App\Actions\ManageValueTags;
use App\Filament\Pages\ManageTags;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

test('global rename previews all affected Values and preserves other metadata and tags', function () {
    $first = Value::factory()->create(['tags' => ['old', 'other']]);
    $second = Value::factory()->create(['tags' => ['old']]);
    $untouched = Value::factory()->create(['tags' => ['other']]);
    $before = $first->only(['label', 'semantic_id', 'description']);
    $action = app(ManageValueTags::class);
    $preview = $action->preview($this->actor, 'rename', 'old', 'new');
    expect($preview['affected'])->toBe(2)->and($first->fresh()->tags)->toBe(['old', 'other']);
    $action->apply($this->actor, 'rename', 'old', 'new', $preview['token']);
    expect($first->fresh()->tags)->toBe(['new', 'other'])
        ->and($first->fresh()->only(array_keys($before)))->toBe($before)
        ->and($second->fresh()->tags)->toBe(['new'])
        ->and($untouched->fresh()->tags)->toBe(['other']);
});

test('merging deduplicates the destination tag and removal only removes associations', function () {
    $first = Value::factory()->create(['tags' => ['old', 'keep', 'target']]);
    $second = Value::factory()->create(['tags' => ['old']]);
    $target = Value::factory()->create(['tags' => ['target']]);
    $action = app(ManageValueTags::class);
    $preview = $action->preview($this->actor, 'merge', 'old', 'target');
    $action->apply($this->actor, 'merge', 'old', 'target', $preview['token']);
    expect($first->fresh()->tags)->toBe(['target', 'keep'])
        ->and($second->fresh()->tags)->toBe(['target']);
    $preview = $action->preview($this->actor, 'remove', 'target');
    $action->apply($this->actor, 'remove', 'target', null, $preview['token']);
    expect($first->fresh()->tags)->toBe(['keep'])
        ->and($second->fresh()->tags)->toBe([])
        ->and($target->fresh()->tags)->toBe([])
        ->and(Value::count())->toBe(3);
});

test('changed memberships and changed intent invalidate a global preview without partial writes', function (string $change) {
    $first = Value::factory()->create(['tags' => ['old']]);
    $second = Value::factory()->create(['tags' => ['other']]);
    $action = app(ManageValueTags::class);
    $preview = $action->preview($this->actor, 'rename', 'old', 'new');
    if ($change === 'membership') {
        $second->update(['tags' => ['old']]);
    } elseif ($change === 'insert') {
        Value::factory()->create(['tags' => ['old']]);
    } elseif ($change === 'source') {
        $first->update(['tags' => ['old', 'changed']]);
    }
    expect(fn () => $action->apply($this->actor, 'rename', 'old', $change === 'intent' ? 'different' : 'new', $preview['token']))
        ->toThrow(ValidationException::class);
    expect($first->fresh()->tags)->toContain('old')->not->toContain('new');
})->with(['membership', 'insert', 'source', 'intent']);

test('global operations validate tag names and distinguish rename from merge', function (string $operation, string $source, ?string $target) {
    Value::factory()->create(['tags' => ['old', 'existing']]);
    expect(fn () => app(ManageValueTags::class)->preview($this->actor, $operation, $source, $target))
        ->toThrow(ValidationException::class);
})->with([
    ['rename', 'old', 'existing'], ['merge', 'old', 'missing'], ['rename', 'old', 'old'],
    ['rename', 'missing', 'new'], ['rename', 'old', '   '], ['rename', 'old', str_repeat('a', 81)],
    ['unknown', 'old', 'new'],
]);

test('global operations authorize again when applying an already reviewed preview', function () {
    $value = Value::factory()->create(['tags' => ['old']]);
    $action = app(ManageValueTags::class);
    $preview = $action->preview($this->actor, 'remove', 'old');
    $this->actor->update(['email' => 'forbidden@example.test']);
    expect(fn () => $action->apply($this->actor, 'remove', 'old', null, $preview['token']))->toThrow(AuthorizationException::class);
    expect($value->fresh()->tags)->toBe(['old']);
});

test('central tag list searches counts distinct Values and links to the applied Values filter', function () {
    Value::factory()->create(['tags' => ['Common', 'Unique']]);
    Value::factory()->create(['tags' => ['Common']]);
    $page = Livewire::test(ManageTags::class);
    $records = $page->instance()->getTableRecords();
    expect(collect($records)->firstWhere('tag', 'Common')['usage'])->toBe(2);
    $page->searchTable('unique');
    expect(collect($page->instance()->getTableRecords())->pluck('tag')->all())->toBe(['Unique']);
    expect(ManageTags::valuesUrl('Common'))->toContain('filters%5Btags%5D%5Bvalues%5D%5B0%5D=Common');
});

test('personal tag filter ordering persists separately from shared tags and keeps active tags available', function () {
    Value::factory()->create(['tags' => ['a', 'b', 'c']]);
    $page = Livewire::test(ListValues::class)
        ->call('restoreTableBrowserProfile', ['tagFilters' => ['c', 'a']])
        ->assertHasNoErrors()->assertSet('tableTagFilters', ['c', 'a']);
    expect(array_keys($page->instance()->personalTagFilterOptions()))->toBe(['c', 'a']);
    $page->filterTable('tags', ['values' => ['b']]);
    expect(array_keys($page->instance()->personalTagFilterOptions()))->toBe(['c', 'a', 'b']);
    Livewire::test(ListValues::class)->assertSet('tableTagFilters', ['c', 'a']);
    expect(array_keys(Value::tagOptions()))->toBe(['a', 'b', 'c']);
    $page->call('restoreTableBrowserProfile', ['tagFilters' => []])->assertSet('tableTagFilters', []);
    $page->call('restoreTableBrowserProfile', ['tagFilters' => null])->assertSet('tableTagFilters', null);
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    Livewire::test(ListValues::class)->assertSet('tableTagFilters', null);
});

test('selected Values have a direct tag assignment action with add remove and replace previews', function (string $mode, array $tags, array $expected) {
    $value = Value::factory()->create(['tags' => ['original', 'keep']]);
    $other = Value::factory()->create(['tags' => ['untouched']]);
    $page = Livewire::test(ListValues::class)->selectTableRecords([$value]);
    $page->mountAction(TestAction::make('assignTags')->table()->bulk())
        ->fillForm(['operation' => 'edit', 'changes' => [['field' => 'tags', 'mode' => $mode, 'value' => $tags]]])
        ->callMountedAction(['preview' => true])->assertHasNoFormErrors();
    expect($value->fresh()->tags)->toBe(['original', 'keep']);
    $page->callMountedAction()->assertHasNoFormErrors();
    expect($value->fresh()->tags)->toBe($expected)->and($other->fresh()->tags)->toBe(['untouched']);
})->with([
    ['add', ['new'], ['original', 'keep', 'new']],
    ['remove', ['original'], ['keep']],
    ['set', ['new'], ['new']],
    ['set', [], []],
]);

test('the global tag dialog requires an impact preview and applies the confirmed rename', function () {
    $value = Value::factory()->create(['tags' => ['old']]);
    $page = Livewire::test(ManageTags::class)
        ->mountAction(TestAction::make('rename')->table(hash('sha256', 'old')))
        ->fillForm(['target' => 'new'])
        ->callMountedAction(['preview' => true])->assertHasNoFormErrors();
    expect($value->fresh()->tags)->toBe(['old'])->and($page->get('tagPreview.affected'))->toBe(1);
    $page->callMountedAction()->assertHasNoFormErrors()->assertDispatched('catalog-tags-updated');
    expect($value->fresh()->tags)->toBe(['new']);
});

test('tag preferences reject malformed lists before changing personal state', function () {
    Livewire::test(ListValues::class)->call('restoreTableBrowserProfile', ['tagFilters' => ['a', 'a']])
        ->assertHasErrors()->assertSet('tableTagFilters', null);
});

test('refreshing tag choices keeps a removed active filter visible without broadening the results', function () {
    $value = Value::factory()->create(['tags' => ['old']]);
    $page = Livewire::test(ListValues::class)->filterTable('tags', ['values' => ['old']]);
    $value->update(['tags' => []]);
    $page->call('refreshTagFilters')->assertSet('tableFilters.tags.values', ['old']);
    expect($page->instance()->personalTagFilterOptions())->toBe(['old' => 'old (unused)']);
    $page->assertCanNotSeeTableRecords([$value]);
});

test('global tag writes roll back all Values when a later save fails', function () {
    $first = Value::factory()->create(['tags' => ['old']]);
    $second = Value::factory()->create(['tags' => ['old']]);
    $action = app(ManageValueTags::class);
    $preview = $action->preview($this->actor, 'rename', 'old', 'new');
    Event::listen('eloquent.saving: '.Value::class, function (Value $value) use ($second): void {
        if ($value->id === $second->id) {
            throw new RuntimeException('Concurrent save failure');
        }
    });
    expect(fn () => $action->apply($this->actor, 'rename', 'old', 'new', $preview['token']))->toThrow(RuntimeException::class);
    expect($first->fresh()->tags)->toBe(['old'])->and($second->fresh()->tags)->toBe(['old']);
});

test('an affected Value changed after the global modal preview cannot be silently updated', function () {
    $value = Value::factory()->create(['tags' => ['old']]);
    $page = Livewire::test(ManageTags::class)
        ->mountAction(TestAction::make('rename')->table(hash('sha256', 'old')))
        ->fillForm(['target' => 'new'])->callMountedAction(['preview' => true]);
    $value->update(['tags' => ['old', 'changed']]);
    $page->callMountedAction()->assertHasErrors(['preview']);
    expect($value->fresh()->tags)->toBe(['old', 'changed'])->and($page->get('tagPreview'))->toBe([]);
});

test('the central tag page and its mutations deny accounts outside catalog administration', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(ManageTags::class)->assertForbidden();
});

test('a source tag removed after the global modal preview reports a validation error', function () {
    $value = Value::factory()->create(['tags' => ['old']]);
    $page = Livewire::test(ManageTags::class)
        ->mountAction(TestAction::make('rename')->table(hash('sha256', 'old')))
        ->fillForm(['target' => 'new'])->callMountedAction(['preview' => true]);
    $value->update(['tags' => []]);
    $page->callMountedAction()->assertHasErrors();
    expect($value->fresh()->tags)->toBe([]);
});

test('global tag management preserves the exact identity of existing spaced tags', function () {
    $value = Value::factory()->create(['tags' => [' old ', 'other']]);
    $action = app(ManageValueTags::class);
    $preview = $action->preview($this->actor, 'rename', ' old ', ' new ');
    $action->apply($this->actor, 'rename', ' old ', ' new ', $preview['token']);
    expect($value->fresh()->tags)->toBe(['new', 'other']);
});

test('tag usage links apply the native filters URL alias ahead of a previously saved table filter', function () {
    $metal = Value::factory()->create(['tags' => ['metal']]);
    $other = Value::factory()->create(['tags' => ['saved']]);
    Livewire::test(ListValues::class)
        ->call('restoreTableBrowserProfile', ['filters' => ['tags' => ['values' => ['saved']]]]);
    parse_str(parse_url(ManageTags::valuesUrl('metal'), PHP_URL_QUERY), $query);
    expect($query)->toBe(['filters' => ['tags' => ['values' => ['metal']]]]);
    Livewire::withQueryParams($query)->test(ListValues::class)
        ->assertSet('tableFilters.tags.values', ['metal'])
        ->assertCanSeeTableRecords([$metal])
        ->assertCanNotSeeTableRecords([$other]);
});
