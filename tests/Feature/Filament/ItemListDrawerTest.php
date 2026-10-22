<?php

use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Livewire\Catalog\ItemListDrawer;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\RuleEffect;
use App\Models\User;
use App\Models\Value;
use App\RuleEffectKind;
use App\Services\CatalogImportParser;
use App\Services\ItemLists;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('rule effect lists and summaries display value wording without changing persisted kinds', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $effect = RuleEffect::factory()->create(['kind' => 'AllowOptions']);

    Livewire::test(ItemListDrawer::class, ['listKey' => 'rule-effects', 'parentId' => $effect->rule_id])
        ->assertCanSeeTableRecords([$effect])->assertSee(RuleEffectKind::AllowOptions->getLabel());

    $this->assertDatabaseHas('rule_effects', ['id' => $effect->id, 'kind' => 'AllowOptions']);
    expect($effect->rule->workspaceSummary())->toContain(RuleEffectKind::AllowOptions->getLabel());
});

test('item edit links select the actual owner record and reject foreign initial selections', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $attribute = ConfiguratorAttribute::factory()->create();
    $foreign = ConfiguratorAttribute::factory()->create();
    $url = app(ItemLists::class)->recordUrl($attribute);
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect((int) $query['attribute'])->toBe($attribute->id);
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $attribute->configurator, 'pageClass' => EditConfigurator::class, 'initialAttributeId' => $attribute->id])
        ->assertSet('selectedAttributeId', $attribute->id);
    $this->get(ConfiguratorResource::getUrl('edit', ['record' => $attribute->configurator_id, 'attribute' => $foreign->id]))->assertNotFound();
});

test('a count opens only its actual items and search stays within that set', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $parent = Attribute::factory()->create();
    $own = Option::factory()->create(['attribute_id' => $parent->id, 'code' => 'A1']);
    $foreign = Option::factory()->create(['code' => 'A2']);
    expect(app(ItemLists::class)->definition($actor, 'attribute-options', $parent->id)->query->count())->toBe(1);
    Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $parent->id])
        ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign])
        ->searchTable('A')->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
});

test('the attribute full list link preserves its parent filter', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $parent = Attribute::factory()->create();
    $own = Option::factory()->for($parent)->create();
    $foreign = Option::factory()->create();
    $url = app(ItemLists::class)->definition($actor, 'attribute-options', $parent->id)->fullListUrl;
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    Livewire::withQueryParams($query)->test(ListOptions::class)
        ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign])
        ->assertCountTableRecords(1);
});

test('the configurator groups full list link preserves its parent filter', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $parent = Configurator::factory()->create();
    $own = Group::factory()->for($parent)->create();
    $foreign = Group::factory()->for(Configurator::factory())->create();
    $unassigned = Group::factory()->create();
    $url = app(ItemLists::class)->definition($actor, 'configurator-groups', $parent->id)->fullListUrl;
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    Livewire::withQueryParams($query)->test(ListGroups::class)
        ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign, $unassigned])
        ->assertCountTableRecords(1);
});

test('the group products full list link preserves its parent filter', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $parent = Group::factory()->create();
    $own = Product::factory()->for($parent)->create();
    $foreign = Product::factory()->create();
    $url = app(ItemLists::class)->definition($actor, 'group-products', $parent->id)->fullListUrl;
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    Livewire::withQueryParams($query)->test(ListProducts::class)
        ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign])
        ->assertCountTableRecords(1);
});

test('item providers reject unrecognized keys and unauthorized callers', function () {
    $actor = User::factory()->create();
    $parent = Attribute::factory()->create();
    expect(fn () => app(ItemLists::class)->definition($actor, 'attribute-options', $parent->id))->toThrow(AuthorizationException::class);
    $actor->email = 'ycm@data4.work';
    expect(fn () => app(ItemLists::class)->definition($actor, 'arbitrary-model', $parent->id))->toThrow(HttpException::class);
});

test('an array count opens exactly the selected card properties with scoped search', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $properties = array_slice(CatalogImportParser::propertyKeys(), 0, 2);
    $group = Group::factory()->create(['result_settings' => ['card_properties' => $properties]]);
    $definition = app(ItemLists::class)->definition($actor, 'group-card-properties', $group->id);
    expect(array_keys($definition->query))->toBe($properties);
    Livewire::test(ItemListDrawer::class, ['listKey' => 'group-card-properties', 'parentId' => $group->id])
        ->assertSee($properties[0])->set('tableSearchScope', 'property_key')->searchTable($properties[0])->assertSee($properties[0]);
});

test('large count lists paginate and hover previews remain bounded plain text', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $parent = Attribute::factory()->create();
    $items = Option::factory()->count(105)->for($parent)->create();
    $items->first()->value->update(['label' => '<script>alert(1)</script>']);
    $preview = app(ItemLists::class)->preview($actor, 'attribute-options', $parent->id);
    expect($preview)->toHaveCount(6)->and($preview[0])->toContain('<script>')->and($preview[5])->toBe('100 more items — open list');
    $page = Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $parent->id]);
    expect($page->instance()->getTableRecords()->total())->toBe(105)->and($page->instance()->getTableRecords()->count())->toBeLessThan(105);
    $page->assertDontSee('<script>alert(1)</script>', escape: false);
});

test('item editor links use the native persistent workspace tab identifier', function () {
    $inclusion = ConfiguratorAttribute::factory()->create();
    parse_str(parse_url(app(ItemLists::class)->recordUrl($inclusion), PHP_URL_QUERY), $query);
    expect($query['tab'])->toBe('attributes::data::tab');
});

test('opening the displayed subset retains search fields and sort without searching extra destination fields', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $parent = Group::factory()->create(['name' => 'needle']);
    $match = Product::factory()->for($parent)->create(['product_name' => 'needle seal']);
    $extraFieldOnly = Product::factory()->for($parent)->create(['product_name' => 'other', 'product_code' => 'other']);
    $foreign = Product::factory()->create(['product_name' => 'needle']);
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'group-products', 'parentId' => $parent->id])
        ->searchTable('needle')->sortTable('product_name', 'desc');
    parse_str(parse_url($drawer->instance()->fullListUrl(), PHP_URL_QUERY), $query);
    Livewire::withQueryParams($query)->test(ListProducts::class)
        ->assertSet('tableSearch', 'needle')->assertSet('tableSearchScope', 'subset')
        ->assertSee(__('admin-ux.related_list_fields'))
        ->assertSet('tableSort', 'product_name:desc')
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$extraFieldOnly, $foreign]);
});

test('empty relation lists distinguish a missing relation from an empty search result', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $parent = Attribute::factory()->create();
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $parent->id, 'allowLocalEditing' => true]);
    $drawer->assertSee(__('admin-ux.no_related_records'));
    $own = Option::factory()->for($parent)->create(['code' => 'A1']);
    Option::factory()->create(['code' => 'B1']);
    $drawer->searchTable('unmatched')->assertSee(__('admin-ux.no_matches'))
        ->call('clearListSearch')->assertCanSeeTableRecords([$own])->assertCountTableRecords(1);
});

test('full list links preserve applied tags operators and combined constraints while retaining the parent', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $parent = Attribute::factory()->create();
    $both = Value::factory()->create(['label' => 'Seal', 'tags' => ['metal', 'standard']]);
    $one = Value::factory()->create(['label' => 'Seal', 'tags' => ['metal']]);
    $match = Option::factory()->for($parent)->create(['value_id' => $both->id, 'code' => 'B1']);
    $tagMismatch = Option::factory()->for($parent)->create(['value_id' => $one->id, 'code' => 'B2']);
    $ruleMismatch = Option::factory()->for($parent)->create(['value_id' => Value::factory()->create(['label' => 'Seal other', 'tags' => ['metal', 'standard']])->id, 'code' => 'C1']);
    $foreign = Option::factory()->create(['value_id' => $both->id, 'code' => 'B3']);
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $parent->id])
        ->set('tableSearchScope', 'value.label')->searchTable('Seal')
        ->filterTable('tags', ['values' => ['metal', 'standard'], 'operator' => 'and'])
        ->filterTable('constraints', ['rules' => [['type' => 'code', 'data' => ['operator' => 'startsWith', 'settings' => ['text' => 'B']]]]]);
    parse_str(parse_url($drawer->instance()->fullListUrl(), PHP_URL_QUERY), $query);
    Livewire::withQueryParams($query)->test(ListOptions::class)->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$tagMismatch, $ruleMismatch, $foreign])->assertCountTableRecords(1);
});

test('an invalid transferred search field fails closed instead of broadening the list', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $record = Product::factory()->create(['product_name' => 'Needle']);
    Livewire::withQueryParams(['search' => 'Needle', 'scope' => 'subset', 'searchFields' => ['all']])->test(ListProducts::class)
        ->assertCanNotSeeTableRecords([$record]);
});
