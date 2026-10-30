<?php

use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogNavigation;
use Illuminate\Validation\ValidationException;

test('tree and tags filter the same model set with OR within categories and AND across categories', function () {
    $actor = User::factory()->create();
    $parent = Group::factory()->create();
    $outside = Group::factory()->create();
    $models = Group::factory()->count(3)->for($parent, 'parent')->create();
    foreach ([...$models, $outside] as $group) {
        Product::factory()->for($group)->create(['profile_fields' => ['Pressure' => '001']]);
    }
    $category = GroupTagCategory::factory()->create(['label' => 'Pressure']);
    $low = GroupTagValue::factory()->create(['group_tag_category_id' => $category->id, 'label' => 'Low']);
    $high = GroupTagValue::factory()->create(['group_tag_category_id' => $category->id, 'label' => 'High']);
    $water = GroupTagValue::factory()->create(['label' => 'Water']);
    $models[0]->tagValues()->attach([$low->id => ['position' => 0], $water->id => ['position' => 1]]);
    $models[1]->tagValues()->attach($high->id, ['position' => 0]);
    $models[2]->tagValues()->attach($water->id, ['position' => 0]);
    $outside->tagValues()->attach($low->id, ['position' => 0]);
    $parent->tagValues()->attach($high->id, ['position' => 0]);
    $before = Product::query()->orderBy('id')->get()->map->getAttributes()->all();
    $navigation = app(CatalogNavigation::class);
    $query = ['branchId' => $parent->id, 'tagValueIds' => [$low->id, $high->id]];
    $tree = $navigation->get($actor, [...$query, 'mode' => 'tree']);
    $tags = $navigation->get($actor, [...$query, 'mode' => 'tags']);
    expect(array_column($tree['cards'], 'id'))->toBe([$models[0]->id, $models[1]->id])
        ->and($tree['cards'])->toBe($tags['cards'])->and($tree['total'])->toBe($tags['total']);
    $both = $navigation->get($actor, [...$query, 'tagValueIds' => [$low->id, $high->id, $water->id]]);
    expect(array_column($both['cards'], 'id'))->toBe([$models[0]->id]);
    $facets = collect($both['tags'])->flatMap(fn (array $category): array => $category['values'])->keyBy('id');
    expect($facets[$low->id]['count'])->toBe(1)->and($facets[$high->id]['count'])->toBe(1)
        ->and($facets[$water->id]['count'])->toBe(2)
        ->and(Product::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($before);
});

test('Group search treats wildcard characters as literal and never searches Product codes', function () {
    $actor = User::factory()->create();
    $literal = Group::factory()->create(['name' => 'Model 10%_Valve', 'legacy_id' => 'Legacy-X']);
    $other = Group::factory()->create(['name' => 'Model 100XValve']);
    Product::factory()->for($literal)->create(['product_code' => 'OTHER']);
    Product::factory()->for($other)->create(['product_code' => 'ProductOnlyCode']);
    $navigation = app(CatalogNavigation::class);
    expect(array_column($navigation->get($actor, ['search' => '%_'])['cards'], 'id'))->toBe([$literal->id])
        ->and($navigation->get($actor, ['search' => 'ProductOnlyCode'])['total'])->toBe(0)
        ->and($navigation->get($actor, ['search' => 'legacy-x'])['total'])->toBe(1);
});

test('navigation rejects unsupported input and raw invalid IDs without coercing or broadening results', function () {
    $actor = User::factory()->create();
    $navigation = app(CatalogNavigation::class);
    foreach ([['unexpected' => true], ['page' => '01'], ['page' => true], ['page' => 0], ['page' => '9999999999999999999999'], ['branchId' => false], ['tagValueIds' => [1, 1]], ['tagValueIds' => ['01']], ['tagValueIds' => [PHP_INT_MAX]], ['tagValueIds' => range(1, 201)], ['mode' => 'other'], ['mode' => null], ['page' => null], ['search' => null], ['requestId' => null], ['tagValueIds' => null], ['search' => str_repeat('א', 501)], ['requestId' => str_repeat('x', 101)]] as $query) {
        expect(fn () => $navigation->get($actor, $query))->toThrow(ValidationException::class);
    }
    expect($navigation->get($actor, ['page' => PHP_INT_MAX])['cards'])->toBe([]);
});
