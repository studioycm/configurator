<?php

use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetRecordSearch;
use App\Services\CatalogAssetTargets;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['catalog-assets.enabled' => true]);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('image record fragments narrow code matches while a lone number matches any segment', function () {
    $group = Group::factory()->create();
    $prefix = Product::factory()->for($group)->create(['product_code' => 'D60-EL-P10-01']);
    $pressure = Product::factory()->for($group)->create(['product_code' => 'D40-EL-LP6-04']);
    $suffix = Product::factory()->for($group)->create(['product_code' => 'D40-EL-P10-06']);
    Product::factory()->for($group)->create(['product_code' => 'D40-HY-P10-02', 'product_name' => 'el 6']);
    $search = app(CatalogAssetRecordSearch::class);
    $query = app(CatalogAssetTargets::class)->eligibleQuery(auth()->user(), 'product.main_image', []);

    expect($search->apply($query, $search->validate(['query' => 'el 6'], 'product'), 'product')->orderBy('id')->pluck('id')->all())->toBe([$prefix->id, $pressure->id, $suffix->id]);
    expect($search->apply($query, $search->validate(['query' => 'el lp6 4'], 'product'), 'product')->pluck('id')->all())->toBe([$pressure->id]);
    expect($query->count())->toBe(4);
});

test('image record search applies target OR inside fragment AND without broadening mandatory owner scope', function () {
    $own = Group::factory()->create(['name' => 'Water valves']);
    $outside = Group::factory()->create();
    $match = Product::factory()->for($own)->create(['product_code' => 'EL-01', 'product_name' => 'Small valve']);
    Product::factory()->for($own)->create(['product_code' => 'HY-02', 'product_name' => 'Other']);
    Product::factory()->for($outside)->create(['product_code' => 'EL-03', 'product_name' => 'Small valve']);
    $search = app(CatalogAssetRecordSearch::class);
    $query = app(CatalogAssetTargets::class)->eligibleQuery(auth()->user(), 'product.main_image', ['list_key' => 'group-products', 'parent_id' => $own->id]);
    $recipe = $search->validate(['query' => 'el small', 'targets' => ['code', 'name']], 'product');

    expect($search->apply($query, $recipe, 'product')->pluck('id')->all())->toBe([$match->id]);
    expect($search->apply($query, $search->validate(['query' => 'el missing', 'combine' => 'any'], 'product'), 'product')->pluck('id')->all())->toBe([$match->id]);
});

test('image record formatting whole value positions and exact paste have distinct semantics', function () {
    $a = Product::factory()->create(['product_code' => 'D60-EL-LP6-001']);
    $b = Product::factory()->create(['product_code' => 'd60_el_lp6_001']);
    $c = Product::factory()->create(['product_code' => 'D60-EL-LP6-01']);
    $search = app(CatalogAssetRecordSearch::class);
    $query = Product::query();

    expect($search->apply($query, $search->validate(['query' => 'd60 el lp6 001', 'match' => 'whole'], 'product'), 'product')->orderBy('id')->pluck('id')->all())->toBe([$a->id, $b->id]);
    expect($search->apply($query, $search->validate(['query' => 'EL-LP6', 'positions' => [2, 3], 'match' => 'whole', 'literal_separators' => true], 'product'), 'product')->orderBy('id')->pluck('id')->all())->toBe([$a->id, $c->id]);
    expect($search->apply($query, $search->validate(['query' => 'el-lp6', 'positions' => [2, 3], 'match' => 'whole', 'case_sensitive' => true], 'product'), 'product')->count())->toBe(0);
    expect($search->apply($query, $search->validate(['exact_codes' => 'D60-EL-LP6-001'], 'product'), 'product')->pluck('id')->all())->toBe([$a->id]);
    expect($search->apply($query, $search->validate(['query' => '--- ___'], 'product'), 'product')->count())->toBe(0);
});

test('image record search treats wildcards as literals and exclusions preserve absent reference values', function () {
    $a = Product::factory()->create(['product_code' => 'EL_%!-001', 'profile_fields' => ['product_pic' => '/ari/a', 'Product_pic' => 'wrong', 'a.b' => 'literal']]);
    $b = Product::factory()->create(['product_code' => 'EL-002', 'profile_fields' => []]);
    Product::factory()->create(['product_code' => 'HY-003', 'profile_fields' => ['product_pic' => 'blocked']]);
    $search = app(CatalogAssetRecordSearch::class);

    expect($search->apply(Product::query(), $search->validate(['query' => '_%!'], 'product'), 'product')->pluck('id')->all())->toBe([$a->id]);
    expect($search->apply(Product::query(), $search->validate(['targets' => ['reference'], 'exclude' => 'blocked'], 'product'), 'product')->orderBy('id')->pluck('id')->all())->toBe([$a->id, $b->id]);
    expect($search->apply(Product::query(), $search->validate(['targets' => ['reference'], 'query' => 'wrong'], 'product'), 'product')->count())->toBe(0);
});

test('image record recipe validation rejects unsupported controls before building a query', function (array $input) {
    expect(fn () => app(CatalogAssetRecordSearch::class)->validate($input, 'product'))->toThrow(ValidationException::class);
})->with([
    [['targets' => ['untrusted.sql']]], [['positions' => [0]]], [['positions' => [17]]],
    [['query' => str_repeat('x', 501)]], [['query' => implode(' ', array_fill(0, 21, 'el'))]],
    [['match' => 'regex']], [['group_ids' => ['bad']]], [['extra' => true]],
]);

test('Groups search names and legacy IDs without inventing Product code semantics', function () {
    $group = Group::factory()->create(['name' => 'Water valves', 'legacy_id' => '001']);
    $search = app(CatalogAssetRecordSearch::class);
    expect($search->apply(Group::query(), $search->validate(['query' => '001', 'targets' => ['legacy']], 'group'), 'group')->pluck('id')->all())->toBe([$group->id]);
    expect(fn () => $search->validate(['targets' => ['code']], 'group'))->toThrow(ValidationException::class);
});

test('separator only excluded fragments do not erase every record', function () {
    $product = Product::factory()->create(['product_code' => 'EL-001']);
    $search = app(CatalogAssetRecordSearch::class);
    expect($search->apply(Product::query(), $search->validate(['query' => 'el', 'exclude' => '--- ___'], 'product'), 'product')->pluck('id')->all())->toBe([$product->id]);
});
