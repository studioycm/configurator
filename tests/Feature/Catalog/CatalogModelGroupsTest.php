<?php

use App\Models\Configurator;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogNavigation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('catalog offers only visible physical leaves with directly owned active Products', function () {
    $actor = User::factory()->create();
    $root = Group::factory()->create(['name' => 'Parent']);
    $model = Group::factory()->for($root, 'parent')->create(['name' => 'Visible model']);
    Product::factory()->for($model)->create();
    $empty = Group::factory()->for($root, 'parent')->create();
    $inactiveOnly = Group::factory()->create();
    Product::factory()->for($inactiveOnly)->create(['is_active' => false]);
    $hidden = Group::factory()->create(['is_active' => false]);
    $hiddenChild = Group::factory()->for($hidden, 'parent')->create();
    Product::factory()->for($hiddenChild)->create();
    $mixed = Group::factory()->create();
    Group::factory()->for($mixed, 'parent')->create(['is_active' => false]);
    Product::factory()->for($mixed)->create();
    $disabledShown = Configurator::factory()->create(['is_active' => false, 'disabled_group_behavior' => 'visible']);
    $shown = Group::factory()->create(['configurator_id' => $disabledShown->id]);
    Product::factory()->for($shown)->create();
    $disabledHidden = Configurator::factory()->create(['is_active' => false, 'disabled_group_behavior' => 'hide']);
    $denied = Group::factory()->create(['configurator_id' => $disabledHidden->id]);
    Product::factory()->for($denied)->create();
    $cycleA = Group::factory()->create();
    $cycleB = Group::factory()->for($cycleA, 'parent')->create();
    DB::table('groups')->where('id', $cycleA->id)->update(['parent_id' => $cycleB->id]);
    Product::factory()->for($cycleA)->create();

    $navigation = app(CatalogNavigation::class);
    $all = $navigation->get($actor, []);
    expect(array_column($all['cards'], 'id'))->toBe([$model->id, $shown->id])
        ->and($all['total'])->toBe(2)->and($all['displayVersion'])->toBe('defaults:v1')
        ->and(array_column($all['cards'], 'mainImage'))->toBe([null, null]);
    $branch = $navigation->get($actor, ['branchId' => $root->id]);
    expect(array_column($branch['cards'], 'id'))->toBe([$model->id]);
    expect(array_column($navigation->get($actor, ['branchId' => $model->id])['cards'], 'id'))->toBe([$model->id]);
    expect($empty->fresh()->parent_id)->toBe($root->id)->and($mixed->products()->count())->toBe(1);
    expect(fn () => $navigation->get($actor, ['branchId' => $hidden->id]))->toThrow(HttpException::class);
});

test('navigation requires a current authenticated actor but does not require admin permission', function () {
    $viewer = User::factory()->create();
    $navigation = app(CatalogNavigation::class);
    expect($navigation->get($viewer, [])['total'])->toBe(0);
    expect(fn () => $navigation->get(new User, []))->toThrow(HttpException::class);
    $viewer->delete();
    expect(fn () => $navigation->get($viewer, []))->toThrow(HttpException::class);
});
