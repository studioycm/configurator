<?php

use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Groups\Pages\CreateGroup;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('product lookup searches the physical product code and shows read only escaped facts', function () {
    $product = Product::factory()->create(['product_code' => '000ABC', 'product_name' => 'Public caption',
        'parts' => ['Part10' => 'ten', 'Part2' => '<unsafe>', 'Part1' => 'one'],
        'extra_data' => ['opaque' => 'a:1:{serialized}']]);
    $other = Product::factory()->create(['product_code' => '999OTHER']);
    Livewire::test(ListProducts::class)->searchTableColumns(['product_code' => '000ABC'])
        ->assertCanSeeTableRecords([$product])->assertCanNotSeeTableRecords([$other]);
    Livewire::test(ViewProduct::class, ['record' => $product->id])->assertSee('000ABC')->assertSee('Public caption')
        ->assertSeeInOrder(['Part1', 'one', 'Part2', '&lt;unsafe&gt;', 'Part10', 'ten'], false)
        ->assertDontSee('<unsafe>', false)->assertSee('Part28')->assertSee('a:1:{serialized}');
    expect(ProductResource::canCreate())->toBeFalse()->and(ProductResource::canEdit($product))->toBeFalse()
        ->and(Action::make('probe')->getModalWidth())->toBe(Width::SevenExtraLarge);
});

test('group editing uses the domain boundary and rejects a cycle without saving partial fields', function () {
    $parent = Group::factory()->create(['name' => 'Original']);
    $child = Group::factory()->for($parent, 'parent')->create();
    Livewire::test(EditGroup::class, ['record' => $parent->id])
        ->fillForm(['name' => 'Changed', 'parent_id' => $child->id])
        ->call('save')->assertHasErrors();
    expect($parent->fresh()->name)->toBe('Original');
    Livewire::test(CreateGroup::class)->fillForm(['name' => 'New group', 'sort_order' => 5])->call('create')->assertHasNoFormErrors();
    expect(Group::where('name', 'New group')->exists())->toBeTrue();
});

test('catalog resource access follows the existing admin admission gate', function () {
    $this->actingAs(User::factory()->create(['email' => 'visitor@example.test']));
    expect(GroupResource::canViewAny())->toBeFalse()->and(ProductResource::canViewAny())->toBeFalse();
    $this->get(GroupResource::getUrl())->assertForbidden();
    $this->get(ProductResource::getUrl())->assertForbidden();
});

test('the group editor saves staged metadata and preserves existing filter ids', function () {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10']]);
    $filter = GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Before', 'value_order' => ['10'], 'value_labels' => []]);
    Livewire::test(EditGroup::class, ['record' => $group->id])
        ->fillForm(['catalog_settings' => ['filters' => [['id' => $filter->id, 'property_key' => 'Working_Pressure', 'label' => 'After', 'values' => [['value' => '10', 'label' => 'Ten']]]], 'sub_groups' => [], 'result_settings' => ['default_page_size' => 2, 'allow_page_size_change' => true, 'page_size_options' => [['size' => 1], ['size' => 2], ['size' => 10]]]]])
        ->call('save')->assertHasNoFormErrors();
    expect($filter->fresh()->label)->toBe('After')->and($group->fresh()->result_settings['default_page_size'])->toBe(2);
});

test('new metadata rows keep their saved identities on a second editor save', function () {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10']]);
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id])->fillForm(['catalog_settings.filters' => [
        ['id' => null, 'property_key' => 'Working_Pressure', 'label' => 'Pressure', 'values' => []],
    ]])->call('save')->assertHasNoFormErrors();
    $saved = $group->filters()->sole()->getAttributes();
    $this->travel(1)->minutes();
    $editor->call('save')->assertHasNoFormErrors();
    expect($group->filters()->sole()->getAttributes())->toBe($saved);
});

test('metadata failure preserves the submitted draft and rolls back details too', function () {
    $group = Group::factory()->create(['name' => 'Original']);
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10']]);
    Livewire::test(EditGroup::class, ['record' => $group->id])->fillForm(['name' => 'Draft name', 'catalog_settings.filters' => [
        ['id' => null, 'property_key' => 'Working_Pressure', 'label' => 'Pressure', 'values' => [['value' => 'stale', 'label' => 'Draft label']]],
    ]])->call('save')->assertHasErrors()->assertSet('data.name', 'Draft name');
    expect($group->fresh()->name)->toBe('Original')->and($group->filters()->count())->toBe(0);
});

test('Group list action requests cannot bypass the dedicated domain save pages', function () {
    $leaf = Group::factory()->create();
    Product::factory()->for($leaf)->create();
    $list = Livewire::test(ListGroups::class);
    $list->callAction('create', data: ['name' => 'Forged child', 'parent_id' => null, 'configurator_id' => null, 'description' => null, 'sort_order' => 0]);
    $this->assertDatabaseMissing('groups', ['name' => 'Forged child']);
});

test('catalog administration links directly to the public catalog and each record page', function () {
    $product = Product::factory()->create();
    $group = $product->group;

    $this->get(GroupResource::getUrl())->assertSee('href="'.route('catalog.index').'"', false);
    Livewire::test(ListGroups::class)
        ->assertTableActionHasUrl('openCatalog', route('catalog.groups.show', $group), $group);
    Livewire::test(EditGroup::class, ['record' => $group->id])
        ->assertActionHasUrl('openCatalog', route('catalog.groups.show', $group));
    Livewire::test(ListProducts::class)
        ->assertTableActionHasUrl('openCatalog', route('catalog.products.show', $product), $product);
});
