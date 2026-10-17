<?php

use App\Filament\Pages\AdminAppearance;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Livewire\Catalog\ItemListDrawer;
use App\Models\AdminAppearanceSettings;
use App\Models\Attribute;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('browser table preferences restore through native Filament state and survive the next mount', function () {
    $page = Livewire::test(ListOptions::class);
    $page->call('restoreTableBrowserProfile', [
        'columns' => [['name' => 'code', 'isToggled' => true], ['name' => 'id', 'isToggled' => true]],
        'sort' => 'code:desc', 'search' => 'Flange', 'searchScope' => 'code', 'perPage' => 25,
    ])->assertHasNoErrors()->assertSet('tableSort', 'code:desc')->assertSet('tableSearch', 'Flange')->assertSet('tableSearchScope', 'code')->assertSet('tableRecordsPerPage', 25);

    $restored = Livewire::test(ListOptions::class);
    $restored->assertSet('tableSort', 'code:desc')->assertSet('tableSearch', 'Flange')->assertSet('tableSearchScope', 'code')->assertSet('tableRecordsPerPage', 25);
    expect(array_slice(array_column($restored->get('tableColumns'), 'name'), 0, 2))->toBe(['code', 'id']);
});

test('native table sessions are isolated by user and table surface', function () {
    $first = Livewire::test(ListOptions::class);
    $first->call('restoreTableBrowserProfile', ['search' => 'one', 'perPage' => 25]);
    Livewire::test(ListAttributes::class)->assertSet('tableSearch', '');
    $this->actingAs(User::factory()->create(['email' => 'beny.cr@gmail.com']));
    Livewire::test(ListOptions::class)->assertSet('tableSearch', '');
});

test('restoring stale column metadata cannot change canonical columns or enable arbitrary sorting', function () {
    $page = Livewire::test(ListOptions::class);
    $page->call('restoreTableBrowserProfile', [
        'columns' => [['name' => 'missing', 'isToggled' => true], ['name' => 'code', 'isToggled' => true, 'isHidden' => true, 'label' => 'Injected']],
        'sort' => 'password:desc', 'searchScope' => 'password', 'perPage' => 100000,
    ])->assertHasNoErrors()->assertSet('tableSearchScope', 'all');
    expect($page->instance()->getTable()->getColumn('code')->getLabel())->toBe(__('catalog.fields.code'));
    expect(array_column($page->get('tableColumns'), 'name'))->not->toContain('missing');
    expect($page->get('tableRecordsPerPage'))->not->toBe(100000);
    expect($page->get('tableSort'))->not->toBe('password:desc');
});

test('browser preference restoration denies accounts without panel permission before mutation', function () {
    $page = Livewire::test(ListOptions::class);
    $this->actingAs(User::factory()->create());
    $page->call('restoreTableBrowserProfile', ['search' => 'forbidden'])->assertForbidden();
});

test('saving personal appearance validates the draft without changing shared defaults', function () {
    $before = AdminAppearanceSettings::findOrFail(1)->settings;
    Livewire::test(AdminAppearance::class)
        ->fillForm(['cell_padding_block' => 4])
        ->call('savePersonal')->assertHasNoFormErrors()->assertDispatched('admin-personal-appearance-saved');
    expect(AdminAppearanceSettings::findOrFail(1)->settings)->toBe($before);
});

test('personal appearance reset restores shared defaults without a shared write', function () {
    $before = AdminAppearanceSettings::findOrFail(1)->settings;
    Livewire::test(AdminAppearance::class)->fillForm(['cell_padding_block' => 4])
        ->call('resetPersonal')->assertSet('data.cell_padding_block', $before['cell_padding_block'])->assertDispatched('admin-personal-appearance-saved');
    expect(AdminAppearanceSettings::findOrFail(1)->settings)->toBe($before);
});

test('restoring serialized visibility flags preserves their boolean meaning', function () {
    Livewire::test(ListGroups::class)
        ->call('restoreTableBrowserProfile', ['showOrderControls' => '1'])
        ->assertSet('showOrderControls', true);
});

test('drawer sessions stay with their owner and list rather than leaking to another drawer', function () {
    $first = Attribute::factory()->create();
    $second = Attribute::factory()->create();
    $parameters = ['listKey' => 'attribute-options', 'parentId' => $first->id];
    Livewire::test(ItemListDrawer::class, $parameters)
        ->call('restoreTableBrowserProfile', ['search' => 'own', 'searchScope' => 'code']);
    Livewire::test(ItemListDrawer::class, $parameters)
        ->assertSet('tableSearch', 'own')->assertSet('tableSearchScope', 'code');
    Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $second->id])
        ->assertSet('tableSearch', '')->assertSet('tableSearchScope', 'all');
});

test('restored filters use native applied state and clearing them survives the next mount', function () {
    $page = Livewire::test(ListOptions::class);
    $page->call('restoreTableBrowserProfile', ['filters' => ['attribute_id' => ['value' => 12], 'removed' => ['value' => 1]]])->assertHasNoErrors();
    expect($page->get('tableFilters'))->not->toHaveKey('removed');
    expect($page->get('tableFilters.attribute_id.value'))->toBe('12');
    $page->call('restoreTableBrowserProfile', ['filters' => []])->assertHasNoErrors();
    Livewire::test(ListOptions::class)->assertSet('tableFilters.attribute_id.value', null);
});

test('invalid profile payloads fail before any table changes and personal saves omit public settings', function () {
    Livewire::test(ListOptions::class)->call('restoreTableBrowserProfile', ['search' => 'changed', 'perPage' => []])
        ->assertHasErrors()->assertSet('tableSearch', '');
    Livewire::test(AdminAppearance::class)->fillForm(['show_configurator_loading_indicator' => true])
        ->call('savePersonal')->assertDispatched('admin-personal-appearance-saved', function ($event, $parameters) {
            return ! array_key_exists('show_configurator_loading_indicator', $parameters['settings']);
        });
});
