<?php

use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Options\Pages\EditOption;
use App\Livewire\Catalog\ItemListDrawer;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\Option;
use App\Models\User;
use App\Services\CatalogImportParser;
use App\Services\ItemLists;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Components\Tabs;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('inspection drawers retain full editor links and do not allow local editing without explicit opt in', function () {
    $attribute = Attribute::factory()->create();
    $option = Option::factory()->for($attribute)->create();
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $attribute->id]);

    $drawer->assertActionExists(TestAction::make('edit')->table($option))
        ->assertActionDoesNotExist(TestAction::make('select')->table($option));
    $drawer->call('selectRecord', (string) $option->id)->assertNotFound();
});

test('shared option rows open a local editor whose selection survives table search and pagination', function () {
    $attribute = Attribute::factory()->create();
    $option = Option::factory()->for($attribute)->create();
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $attribute->id, 'allowLocalEditing' => true]);

    $drawer->callAction(TestAction::make('select')->table($option))->assertSet('selectedRecordId', (string) $option->id)
        ->assertSee('catalog-item-drawer-editor', false)->assertNoRedirect()
        ->searchTable('unmatched search')->call('gotoPage', 2)
        ->assertSet('selectedRecordId', (string) $option->id)->assertSee('catalog-item-drawer-editor', false);

    expect($drawer->instance()->editorComponent())->toBe(EditOption::class);
});

test('a local option save preserves exact code and dispatches a refresh for its drawer only', function () {
    $attribute = Attribute::factory()->create();
    $option = Option::factory()->for($attribute)->create(['code' => 'A1']);
    $editor = Livewire::test(EditOption::class, ['record' => $option->id, 'listKey' => 'attribute-options', 'parentId' => $attribute->id])
        ->fillForm(['code' => '0b']);

    $editor->call('save')->assertHasNoFormErrors()->assertNoRedirect()
        ->assertDispatchedTo(ItemListDrawer::class, 'item-drawer-record-saved', listKey: 'attribute-options', parentId: $attribute->id, record: (string) $option->id);

    $this->assertDatabaseHas('options', ['id' => $option->id, 'code' => '0b']);
});

test('drawer and child editor both reject a record outside the fresh base list', function () {
    $attribute = Attribute::factory()->create();
    $foreign = Option::factory()->create();

    Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $attribute->id, 'allowLocalEditing' => true])
        ->call('selectRecord', (string) $foreign->id)->assertNotFound();
    Livewire::test(EditOption::class, ['record' => $foreign->id, 'listKey' => 'attribute-options', 'parentId' => $attribute->id])
        ->assertNotFound();
});

test('an option that leaves the list after mounting cannot be saved through its drawer', function () {
    $attribute = Attribute::factory()->create();
    $otherAttribute = Attribute::factory()->create();
    $option = Option::factory()->for($attribute)->create(['code' => 'A1']);
    $editor = Livewire::test(EditOption::class, ['record' => $option->id, 'listKey' => 'attribute-options', 'parentId' => $attribute->id])
        ->fillForm(['code' => '0b']);
    $option->update(['attribute_id' => $otherAttribute->id]);

    $editor->call('save')->assertNotFound();

    $this->assertDatabaseHas('options', ['id' => $option->id, 'code' => 'A1', 'attribute_id' => $otherAttribute->id]);
});

test('reloading an option editor rechecks its current drawer membership', function () {
    $attribute = Attribute::factory()->create();
    $option = Option::factory()->for($attribute)->create();
    $editor = Livewire::test(EditOption::class, ['record' => $option->id, 'listKey' => 'attribute-options', 'parentId' => $attribute->id]);
    $option->update(['attribute_id' => Attribute::factory()->create()->id]);

    $editor->call('reloadBatchEditor')->assertNotFound();
});

test('an embedded option editor retains usage inspection and excludes deletion', function () {
    $attribute = Attribute::factory()->create();
    $option = Option::factory()->for($attribute)->create();

    Livewire::test(EditOption::class, ['record' => $option->id, 'listKey' => 'attribute-options', 'parentId' => $attribute->id])
        ->assertActionExists('usage')->assertActionDoesNotExist('remove');
    Livewire::test(EditOption::class, ['record' => $option->id])->assertActionExists('remove');
});

test('a saved option that moves to another Attribute clears its previous drawer editor', function () {
    $attribute = Attribute::factory()->create();
    $otherAttribute = Attribute::factory()->create();
    $option = Option::factory()->for($attribute)->create();
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $attribute->id, 'allowLocalEditing' => true])
        ->call('selectRecord', (string) $option->id);

    Livewire::test(EditOption::class, ['record' => $option->id, 'listKey' => 'attribute-options', 'parentId' => $attribute->id])
        ->fillForm(['attribute_id' => $otherAttribute->id])->call('save')->assertHasNoFormErrors();
    $drawer->dispatch('item-drawer-record-saved', listKey: 'attribute-options', parentId: $attribute->id, record: (string) $option->id)
        ->assertSet('selectedRecordId', null)->assertCanNotSeeTableRecords([$option]);

    $this->assertDatabaseHas('options', ['id' => $option->id, 'attribute_id' => $otherAttribute->id]);
});

test('local option editing preserves the existing stale draft guard', function () {
    $attribute = Attribute::factory()->create();
    $option = Option::factory()->for($attribute)->create(['code' => 'A1']);
    $editor = Livewire::test(EditOption::class, ['record' => $option->id, 'listKey' => 'attribute-options', 'parentId' => $attribute->id])
        ->fillForm(['code' => '0b']);
    $option->update(['code' => 'A2']);

    $editor->call('save')->assertHasFormErrors(['record'])->assertSet('data.code', '0b')
        ->assertNotDispatched('item-drawer-record-saved');

    $this->assertDatabaseHas('options', ['id' => $option->id, 'code' => 'A2']);
});

test('assigned group rows open their own editor and reject an unrelated group', function () {
    $configurator = Configurator::factory()->create();
    $group = Group::factory()->for($configurator)->create();
    $foreign = Group::factory()->create();

    Livewire::test(ItemListDrawer::class, ['listKey' => 'configurator-groups', 'parentId' => $configurator->id, 'allowLocalEditing' => true])
        ->callAction(TestAction::make('select')->table($group))->assertSet('selectedRecordId', (string) $group->id)
        ->assertSee('catalog-item-drawer-editor', false)->assertNoRedirect();
    Livewire::test(EditGroup::class, ['record' => $foreign->id, 'listKey' => 'configurator-groups', 'parentId' => $configurator->id])
        ->assertNotFound();
});

test('card property rows open the real parent Group on its Presentation tab without adding a fake row', function () {
    $property = CatalogImportParser::propertyKeys()[0];
    $group = Group::factory()->create(['result_settings' => ['card_properties' => [$property]]]);
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'group-card-properties', 'parentId' => $group->id, 'allowLocalEditing' => true]);

    $drawer->callAction(TestAction::make('select')->table($property))->assertSet('selectedRecordId', (string) $group->id)
        ->assertSee('catalog-item-drawer-editor', false)->assertNoRedirect();

    expect($drawer->instance()->getTableRecords()->total())->toBe(1);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id, 'listKey' => 'group-card-properties', 'parentId' => $group->id, 'initialTab' => 'form.group-settings.presentation::data::tab']);
    $tabs = $editor->instance()->form->getComponent('group-settings');
    expect($tabs)->toBeInstanceOf(Tabs::class);
    expect($tabs->getChildSchema()->getComponents()[3]->getId())->toBe('form.group-settings.presentation::data::tab');
    expect($tabs->getActiveTab())->toBe(4);
    expect($tabs->isTabPersistedInQueryString())->toBeFalse();
    parse_str(parse_url(app(ItemLists::class)->definition(auth()->user(), 'group-card-properties', $group->id)->fullListUrl, PHP_URL_QUERY), $query);
    expect($query['group-tab'])->toBe('form.group-settings.presentation::data::tab');
    $this->get(GroupResource::getUrl('edit', ['record' => $group, 'group-tab' => 'form.group-settings.presentation::data::tab']))
        ->assertSee('activeTab: 4', false);
});

test('the Presentation editor remains available when a Group has no selected card properties', function () {
    $group = Group::factory()->create();

    Livewire::test(ItemListDrawer::class, ['listKey' => 'group-card-properties', 'parentId' => $group->id, 'allowLocalEditing' => true])
        ->call('editPresentation')->assertSet('selectedRecordId', (string) $group->id)
        ->assertSee('catalog-item-drawer-editor', false);
});

test('parent Groups do not offer a Presentation editor that their form does not support', function () {
    $group = Group::factory()->create();
    Group::factory()->for($group, 'parent')->create();

    Livewire::test(ItemListDrawer::class, ['listKey' => 'group-card-properties', 'parentId' => $group->id, 'allowLocalEditing' => true])
        ->assertActionDoesNotExist(TestAction::make('editPresentation')->table())
        ->call('editPresentation')->assertNotFound();
    Livewire::test(EditGroup::class, ['record' => $group->id, 'listKey' => 'group-card-properties', 'parentId' => $group->id, 'initialTab' => 'form.group-settings.presentation::data::tab'])
        ->assertNotFound();
});

test('saving a Group from its drawer refreshes only that list and keeps an unrelated editor draft', function () {
    $configurator = Configurator::factory()->create();
    $group = Group::factory()->for($configurator)->create();
    $other = Group::factory()->create();
    $otherEditor = Livewire::test(EditGroup::class, ['record' => $other->id])->fillForm(['name' => 'Unsaved other draft']);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id, 'listKey' => 'configurator-groups', 'parentId' => $configurator->id])
        ->fillForm(['name' => 'Saved drawer group']);

    $editor->call('save')->assertHasNoFormErrors()->assertNoRedirect()
        ->assertDispatchedTo(ItemListDrawer::class, 'item-drawer-record-saved', listKey: 'configurator-groups', parentId: $configurator->id, record: (string) $group->id);
    $otherEditor->call('$refresh')->assertSet('data.name', 'Unsaved other draft');

    $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Saved drawer group']);
    $this->assertDatabaseHas('groups', ['id' => $other->id, 'name' => $other->name]);
});

test('blocking rules cannot open a local Group or Option editor', function () {
    $rule = ConfiguratorRule::factory()->create();

    Livewire::test(ItemListDrawer::class, ['listKey' => 'rule-effects', 'parentId' => $rule->id, 'allowLocalEditing' => true])
        ->call('selectRecord', (string) $rule->id)->assertNotFound();
    Livewire::test(EditGroup::class, ['record' => Group::factory()->create()->id, 'listKey' => 'rule-effects', 'parentId' => $rule->id])
        ->assertNotFound();
});
