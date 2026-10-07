<?php

use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Livewire\Catalog\ItemListDrawer;
use App\Models\Attribute;
use App\Models\ConfiguratorAttribute;
use App\Models\Group;
use App\Models\Option;
use App\Models\User;
use App\Services\CatalogImportParser;
use App\Services\ItemLists;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
