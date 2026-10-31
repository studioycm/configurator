<?php

use App\Actions\SaveCatalogPresentationSettings;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogNavigation;
use App\Services\CatalogPresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('card slices and independent versioned tree pages have separate bounds and truthful totals', function () {
    $actor = User::factory()->create();
    $root = Group::factory()->create(['name' => 'Root']);
    $other = Group::factory()->create(['name' => 'Other']);
    $tag = GroupTagValue::factory()->create();
    $groups = collect();
    for ($i = 0; $i < 51; $i++) {
        $group = Group::factory()->for($root, 'parent')->create(['name' => 'Model '.$i, 'sort_order' => $i]);
        Product::factory()->for($group)->create();
        $group->tagValues()->attach($tag->id, ['position' => 0]);
        $groups->push($group);
    }
    $otherModel = Group::factory()->for($other, 'parent')->create();
    Product::factory()->for($otherModel)->create();
    $navigation = app(CatalogNavigation::class);
    $first = $navigation->get($actor, ['branchId' => $root->id, 'page' => 1, 'requestId' => 'cards-1']);
    $second = $navigation->get($actor, ['branchId' => $root->id, 'page' => 2]);
    expect(count($first['cards']))->toBe(24)->and($first['total'])->toBe(51)->and($first['hasMore'])->toBeTrue()
        ->and(array_intersect(array_column($first['cards'], 'id'), array_column($second['cards'], 'id')))->toBeEmpty()
        ->and($first['tags'][0]['values'][0]['count'])->toBe(51);
    $version = $first['treeVersion'];
    $a = $navigation->children($actor, $root->id, 1, $version, 'tree-a');
    $b = $navigation->children($actor, $root->id, 2, $version, 'tree-b');
    $independent = $navigation->children($actor, $other->id, 1, $version, 'tree-other');
    expect(count($a['rows']))->toBe(50)->and($a['hasMore'])->toBeTrue()->and($a['nextPage'])->toBe(2)
        ->and(array_column($b['rows'], 'id'))->toBe([$groups->last()->id])->and($b['hasMore'])->toBeFalse()
        ->and(array_column($independent['rows'], 'id'))->toBe([$otherModel->id])
        ->and($navigation->children($actor, $root->id, PHP_INT_MAX, $version, 'end')['rows'])->toBe([])
        ->and($navigation->get($actor, ['branchId' => $root->id, 'page' => 1])['cards'])->toBe($first['cards']);
    $rootRows = $navigation->children($actor, null, 1, $version, 'root')['rows'];
    expect(collect($rootRows)->keyBy('id')[$root->id]['modelCount'])->toBe(51);
    $groups[0]->update(['name' => 'Changed']);
    $stale = $navigation->children($actor, $root->id, 2, $version, 'late');
    expect($stale['status'])->toBe('refresh_required')->and($stale['rows'])->toBe([])->and($stale['requestId'])->toBe('late');
    $root->update(['is_active' => false]);
    expect(fn () => $navigation->children($actor, $root->id, 1, $version, 'hidden'))->toThrow(HttpException::class);
    expect(fn () => $navigation->children($actor, PHP_INT_MAX, 1, $version, 'missing'))->toThrow(HttpException::class);
});

test('projection query count stays fixed when the hierarchy grows and responses remain bounded', function () {
    $actor = User::factory()->create();
    $navigation = app(CatalogNavigation::class);
    $measure = function () use ($navigation, $actor): array {
        DB::enableQueryLog();
        $result = $navigation->get($actor, []);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();

        return [$result, count($queries), array_column($queries, 'query')];
    };
    [$empty, $emptyCount] = $measure();
    for ($i = 0; $i < 60; $i++) {
        $group = Group::factory()->create();
        Product::factory()->for($group)->create();
    }
    [$large, $largeCount, $largeQueries] = $measure();
    expect(count($large['cards']))->toBe(24)->and(count($large['treeRows']))->toBe(50)
        ->and($large['total'])->toBe(60)->and($largeCount)->toBeLessThanOrEqual(8)
        ->and($largeCount - $emptyCount)->toBeLessThanOrEqual(3);
    $evidenceDirectory = getenv('CATALOG_K1_EVIDENCE_DIR');
    if (is_string($evidenceDirectory) && is_dir($evidenceDirectory)) {
        file_put_contents($evidenceDirectory.'/query-measurement.json', json_encode(['empty_queries' => $emptyCount, 'sixty_model_queries' => $largeCount, 'card_rows' => count($large['cards']), 'tree_rows' => count($large['treeRows']), 'total' => $large['total'], 'payload_bytes' => strlen(json_encode($large, JSON_THROW_ON_ERROR)), 'sql_without_bindings' => $largeQueries], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL);
    }
});

test('navigation projects saved defaults and real card overrides without leaking raw metadata', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $first = GroupTagCategory::factory()->create(['label' => 'Application']);
    $second = GroupTagCategory::factory()->create(['label' => 'Pressure']);
    $low = GroupTagValue::factory()->for($first, 'category')->create(['label' => '001']);
    $high = GroupTagValue::factory()->for($second, 'category')->create(['label' => '001']);
    $model = Group::factory()->create(['name' => 'שסתום / Valve', 'result_settings' => ['unknown' => ['zero' => '0'], 'catalog_presentation' => ['group_image_placement' => 'right', 'group_card_tag_categories' => [$second->id, $first->id]]]]);
    Product::factory()->for($model)->create();
    $model->tagValues()->attach([$low->id => ['position' => 1], $high->id => ['position' => 0]]);
    $presentation = app(CatalogPresentation::class);
    $saved = app(SaveCatalogPresentationSettings::class)->handle($actor, array_replace($presentation->defaults(), ['sidebar_mode' => 'tags', 'group_card_tag_categories' => [$first->id]]), 'defaults:v1');
    $navigation = app(CatalogNavigation::class);
    $reply = $navigation->get($actor, ['search' => 'שסתום /', 'tagValueIds' => [(string) $high->id, (string) $low->id], 'requestId' => 'unicode']);
    expect($reply['displayVersion'])->toBe($saved->version)
        ->and($reply['query'])->toBe(['branchId' => null, 'search' => 'שסתום /', 'tagValueIds' => [$low->id, $high->id], 'mode' => 'tags', 'page' => 1])
        ->and($reply['presentation']['sidebar_mode'])->toBe('tags')
        ->and($reply['cards'][0]['presentation']['group_image_placement'])->toBe('right')
        ->and(array_column($reply['cards'][0]['tags'], 'id'))->toBe([$high->id, $low->id])
        ->and($reply['cards'][0]['mainImage'])->toBeNull()
        ->and(json_encode($reply))->not->toContain('unknown', 'result_settings')
        ->and($model->fresh()->result_settings['unknown']['zero'])->toBe('0');
    expect($navigation->get($actor, ['mode' => 'tree'])['query']['mode'])->toBe('tree');
});

test('initial root disclosure exposes further pages without changing model cards', function () {
    $actor = User::factory()->create();
    Group::factory()->count(51)->create();
    $reply = app(CatalogNavigation::class)->get($actor, []);
    expect($reply['rootTreePagination'])->toBe(['page' => 1, 'nextPage' => 2, 'hasMore' => true])
        ->and($reply['cards'])->toBe([])->and($reply['total'])->toBe(0);
});

test('navigation rejects explicit null mode and malformed Unicode without falling back to defaults', function (array $query) {
    $actor = User::factory()->create();
    app(CatalogNavigation::class)->get($actor, $query);
})->with([[['mode' => null]], [['search' => "\xFF"]], [['page' => true]], [['tagValueIds' => ['01']]]])->throws(ValidationException::class);
