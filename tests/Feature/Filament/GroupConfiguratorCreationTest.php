<?php

use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Configurators\Pages\CreateConfigurator;
use App\Filament\Resources\Configurators\Pages\ListConfigurators;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Groups\Pages\CreateGroup;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('the table plus opens an inline creation pane and cancel restores the list layout', function (string $listPage, string $createPage, bool $hasEditorPane) {
    $list = Livewire::test($listPage)->searchTable('retained search');
    expect($list->instance()->hasEditorPane())->toBe($hasEditorPane);

    $list->callAction(TestAction::make('create')->table())
        ->assertHasNoFormErrors()->assertSet('isCreatingRecord', true)
        ->assertSet('tableSearch', 'retained search')->assertNoRedirect()
        ->assertActionDisabled(TestAction::make('create')->table());
    expect($list->instance()->editorComponent())->toBe($createPage);
    expect($list->instance()->hasEditorPane())->toBeTrue();

    Livewire::test($createPage, ['embedded' => true])
        ->callAction(TestAction::make('cancel')->schemaComponent('form-actions', schema: 'content'))
        ->assertDispatched('catalog-create-cancelled')->assertNoRedirect();
    $list->dispatch('catalog-create-cancelled', resource: $listPage::getResource())
        ->assertSet('isCreatingRecord', false)->assertSet('tableSearch', 'retained search')
        ->assertNoRedirect();
    expect($list->instance()->hasEditorPane())->toBe($hasEditorPane);
})->with([
    'groups' => [ListGroups::class, CreateGroup::class, true],
    'configurators' => [ListConfigurators::class, CreateConfigurator::class, false],
]);

test('cancelling group creation restores the previously selected inline editor', function () {
    $record = Group::factory()->create();
    $list = Livewire::test(ListGroups::class)->callAction(TestAction::make('select')->table($record));

    $list->call('createRecord')->dispatch('catalog-create-cancelled', resource: GroupResource::class)
        ->assertSet('isCreatingRecord', false)->assertSet('selectedRecord', (string) $record->id)
        ->assertNoRedirect();

    expect($list->instance()->editorComponent())->toBe(EditGroup::class);
});

test('embedded group creation saves through the domain action and opens the new inline editor', function () {
    $fields = ['name' => 'New inline group', 'description' => 'Shared catalog group', 'sort_order' => 5];
    $list = Livewire::test(ListGroups::class)->call('createRecord');
    $editor = Livewire::test(CreateGroup::class, ['embedded' => true])->fillForm($fields);

    $editor->call('create')->assertHasNoFormErrors()->assertNoRedirect()->assertNotified()
        ->assertDispatched('catalog-record-created');

    $record = Group::query()->sole();
    $this->assertDatabaseHas('groups', $fields);
    $list->dispatch('catalog-record-created', resource: GroupResource::class, record: (string) $record->id)
        ->assertSet('isCreatingRecord', false)->assertSet('selectedRecord', (string) $record->id)
        ->assertNoRedirect()->assertCanSeeTableRecords([$record]);
    expect($list->instance()->editorComponent())->toBe(EditGroup::class);
    $editor->call('create');
    $this->assertDatabaseCount('groups', 1);
});

test('embedded configurator creation saves its initial settings and navigates to the dedicated edit page', function () {
    $fields = ['name' => 'New inline configurator', 'description' => 'Shared authoring definition'];
    $list = Livewire::test(ListConfigurators::class)->call('createRecord');
    $editor = Livewire::test(CreateConfigurator::class, ['embedded' => true])->fillForm($fields);

    $editor->call('create')->assertHasNoFormErrors()->assertNoRedirect()->assertNotified()
        ->assertDispatched('catalog-record-created');

    $record = Configurator::query()->sole();
    $this->assertDatabaseHas('configurators', $fields);
    expect($record->context_schema)->toBe(['territory' => [], 'application' => []]);
    expect($record->policy_overrides)->toBe([]);
    $list->dispatch('catalog-record-created', resource: ConfiguratorResource::class, record: (string) $record->id)
        ->assertSet('isCreatingRecord', false)->assertSet('selectedRecord', null)
        ->assertRedirect(ConfiguratorResource::getUrl('edit', ['record' => $record]));
    expect($list->instance()->editorComponent())->toBeNull();
    expect($list->instance()->hasEditorPane())->toBeFalse();
    $editor->call('create');
    $this->assertDatabaseCount('configurators', 1);
});

test('embedded group creation reports invalid parent ancestry beside the field and keeps its draft', function () {
    $parent = Group::factory()->create();
    $child = Group::factory()->for($parent, 'parent')->create();
    $parent->update(['parent_id' => $child->id]);
    $editor = Livewire::test(CreateGroup::class, ['embedded' => true])
        ->fillForm(['name' => 'Retained group draft', 'parent_id' => $parent->id]);

    $editor->call('create')->assertHasFormErrors(['parent_id'])
        ->assertSee('A Group cannot become its own ancestor.')
        ->assertSet('data.name', 'Retained group draft')->assertSet('data.parent_id', $parent->id)
        ->assertNotDispatched('catalog-record-created')->assertNoRedirect();

    $this->assertDatabaseCount('groups', 2);
});

test('inline group and configurator creation rechecks permission before saving', function (string $listPage, string $createPage, string $table) {
    $list = Livewire::test($listPage);
    $editor = Livewire::test($createPage, ['embedded' => true])->fillForm(['name' => 'Forbidden draft']);
    $this->actingAs(User::factory()->create(['email' => 'visitor@example.test']));

    $list->call('createRecord')->assertForbidden();
    $editor->call('create')->assertForbidden();

    $this->assertDatabaseCount($table, 0);
})->with([
    'groups' => [ListGroups::class, CreateGroup::class, 'groups'],
    'configurators' => [ListConfigurators::class, CreateConfigurator::class, 'configurators'],
]);
