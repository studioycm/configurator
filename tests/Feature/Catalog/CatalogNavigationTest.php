<?php

use App\Models\Group;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogNavigation;
use Illuminate\Support\Facades\DB;
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
