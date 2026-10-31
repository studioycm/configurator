<?php

use App\Actions\SaveCatalogPresentationSettings;
use App\Actions\SaveGroupSettings;
use App\Livewire\Catalog\GroupShow;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogCards;
use App\Services\CatalogNavigation;
use App\Services\CatalogPresentation;
use App\Services\CatalogSnapshots;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function presentationCriteria(): array
{
    return ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []];
}

test('saved presentation consumers keep bounded queries and payloads with real category references', function (int $productCount, int $modelCount) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $group = Group::factory()->create(['name' => 'Measured model', 'description' => null])->fresh();
    Product::factory()->count($productCount)->for($group)->sequence(fn (Sequence $sequence): array => ['product_code' => sprintf('MEASURED-%03d', $sequence->index)])->create(['profile_fields' => []]);
    $categories = GroupTagCategory::factory()->count(2)->create();
    $tag = GroupTagValue::factory()->for($categories[0], 'category')->create(['label' => 'Measured tag']);
    $group->tagValues()->attach($tag->id, ['position' => 0]);
    for ($index = 1; $index < $modelCount; $index++) {
        $other = Group::factory()->create(['name' => 'Measured other '.$index, 'description' => null]);
        Product::factory()->for($other)->create(['product_code' => 'OTHER-'.$index, 'profile_fields' => []]);
        $other->tagValues()->attach($tag->id, ['position' => 0]);
    }
    $service = app(CatalogPresentation::class);
    $shared = app(SaveCatalogPresentationSettings::class)->handle($actor, array_replace($service->defaults(), ['group_card_tag_categories' => [$categories[0]->id]]), 'defaults:v1');
    app(SaveGroupSettings::class)->patchPresentation($actor, $group, ['catalog_presentation' => ['group_card_tag_categories' => [$categories[1]->id], 'product_image_placement' => 'right']], [], $service->groupFingerprint($group));
    $snapshot = app(CatalogSnapshots::class)->get($group->id);
    $measure = function (Closure $operation): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $value = $operation();
            $sql = array_column(DB::getQueryLog(), 'query');
            $body = $value instanceof TestResponse ? $value->getContent() : json_encode($value, JSON_THROW_ON_ERROR);

            return ['value' => $value, 'metrics' => [
                'query_count' => count($sql), 'reference_queries' => count(array_filter($sql, fn (string $query): bool => preg_match('/^select .* from ["`]group_tag_categories["`] where/i', $query) === 1)),
                'write_queries' => count(array_filter($sql, fn (string $query): bool => preg_match('/^(insert|update|delete|replace|alter|create|drop)\b/i', $query) === 1)),
                'payload_bytes' => strlen($body), 'sql_without_bindings' => $sql,
            ]];
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    };

    $reads = [
        'navigation' => $measure(fn (): array => app(CatalogNavigation::class)->get($actor, [])),
        'dataset' => $measure(fn (): TestResponse => $this->getJson(route('catalog.groups.dataset', $group))),
        'cards' => $measure(fn (): array => app(CatalogCards::class)->get($group->id, presentationCriteria(), $snapshot->revision(), 'measured-cards', $shared->version)),
        'ssr' => $measure(fn (): TestResponse => $this->get(route('catalog.groups.show', $group))),
    ];
    $reads['not_modified'] = $measure(fn (): TestResponse => $this->getJson(route('catalog.groups.dataset', $group), ['If-None-Match' => $reads['dataset']['value']->headers->get('ETag')]));
    expect($reads['navigation']['value']['displayVersion'])->toBe($shared->version);
    expect($reads['navigation']['value']['total'])->toBe($modelCount);
    expect(count($reads['navigation']['value']['cards']))->toBe(min(24, $modelCount));
    $reads['dataset']['value']->assertOk()->assertJsonPath('displayVersion', $shared->version)->assertJsonPath('presentation.product_image_placement', 'right');
    expect($reads['cards']['value']['displayVersion'])->toBe($shared->version);
    expect($reads['cards']['value']['total'])->toBe($productCount);
    expect($reads['cards']['value']['presentation']['group_card_tag_categories'])->toBe([$categories[1]->id]);
    $reads['ssr']['value']->assertOk()->assertSee($shared->version);
    $reads['not_modified']['value']->assertNotModified();
    $bounds = ['navigation' => ['queries' => 8, 'bytes' => 65536], 'dataset' => ['queries' => 12, 'bytes' => 65536],
        'cards' => ['queries' => 12, 'bytes' => 262144], 'ssr' => ['queries' => 24, 'bytes' => 524288], 'not_modified' => ['queries' => 6, 'bytes' => 0]];
    foreach ($reads as $name => $read) {
        expect($read['metrics']['query_count'])->toBeLessThanOrEqual($bounds[$name]['queries']);
        expect($read['metrics']['write_queries'])->toBe(0);
        expect($read['metrics']['reference_queries'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(2);
        expect($read['metrics']['payload_bytes'])->toBeLessThanOrEqual($bounds[$name]['bytes']);
    }
    if (getenv('CATALOG_K2_CAPTURE_QUERY_MEASUREMENTS') === '1') {
        fwrite(STDOUT, PHP_EOL.'CATALOG_K2_CONSUMER_MEASUREMENT '.json_encode([
            'engine' => DB::connection()->getDriverName(), 'cache' => 'warm business snapshot; actual saved presentation version and nonempty shared/own category references',
            'display_version' => $shared->version, 'group_revision' => $snapshot->revision(),
            'products' => $productCount, 'eligible_model_groups' => $modelCount, 'bounds' => $bounds,
            'measurements' => array_map(fn (array $read): array => $read['metrics'], $reads),
            'limit' => 'Synthetic guarded SQLite query/payload measurements; HTTP middleware and SSR template queries included. No production latency or MySQL query-plan claim.',
        ], JSON_THROW_ON_ERROR).PHP_EOL);
    }
})->with(['one model and Product' => [1, 1], 'multiple card chunks and a full navigation slice' => [25, 30]]);

test('shared-only save updates navigation dataset SSR and card versions without rebuilding business snapshots', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $group = Group::factory()->create()->fresh();
    Product::factory()->for($group)->create();
    $snapshot = app(CatalogSnapshots::class)->get($group->id);
    $first = $this->getJson(route('catalog.groups.dataset', $group))->assertOk();
    $etag = $first->headers->get('ETag');
    $first->assertJsonPath('displayVersion', 'defaults:v1')->assertJsonPath('presentation.sidebar_mode', 'tree');
    $this->getJson(route('catalog.groups.dataset', $group), ['If-None-Match' => $etag])->assertNotModified();
    $service = app(CatalogPresentation::class);
    $saved = app(SaveCatalogPresentationSettings::class)->handle($actor, array_replace($service->defaults(), ['sidebar_mode' => 'tags', 'tag_counts' => false]), 'defaults:v1');
    $fresh = $this->getJson(route('catalog.groups.dataset', $group), ['If-None-Match' => $etag])->assertOk()->assertJsonPath('displayVersion', $saved->version)->assertJsonPath('presentation.tag_counts', false);
    expect($fresh->headers->get('ETag'))->not->toBe($etag);
    $this->getJson(route('catalog.groups.dataset', $group), ['If-None-Match' => $fresh->headers->get('ETag')])->assertNotModified();
    expect(app(CatalogNavigation::class)->get($actor, [])['displayVersion'])->toBe($saved->version);
    $cards = app(CatalogCards::class)->get($group->id, presentationCriteria(), $snapshot->revision(), 'version-test', $saved->version);
    expect($cards['displayVersion'])->toBe($saved->version);
    expect($cards['status'])->toBe('ready');
    expect($cards['presentation']['tag_counts'])->toBeFalse();
    expect(app(CatalogSnapshots::class)->cached($group->id, $snapshot->revision())->data)->toBe($snapshot->data);
    $page = $this->get(route('catalog.groups.show', $group))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($page);
    $xpath = new DOMXPath($document);
    $wire = json_decode($xpath->evaluate('string(//script[@data-catalog-snapshot])'), true, flags: JSON_THROW_ON_ERROR);
    expect($wire['displayVersion'])->toBe($saved->version);
    expect($wire['revision'])->toBe($snapshot->revision());
    expect($group->fresh()->catalog_revision)->toBe($group->catalog_revision);
});

test('cards project fresh own Group metadata and reject stale display pairs before rendering chunks', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $group = Group::factory()->create()->fresh();
    Product::factory()->for($group)->create();
    $presentation = app(CatalogPresentation::class);
    app(SaveGroupSettings::class)->patchPresentation($actor, $group, ['catalog_presentation' => ['product_image_placement' => 'right']], [], $presentation->groupFingerprint($group));
    $snapshot = app(CatalogSnapshots::class)->get($group->id);
    $cards = app(CatalogCards::class)->get($group->id, presentationCriteria(), $snapshot->revision(), 'own-test');
    expect($cards['presentation']['product_image_placement'])->toBe('right');
    $saved = app(SaveCatalogPresentationSettings::class)->handle($actor, $presentation->defaults(), 'defaults:v1');
    $stale = app(CatalogCards::class)->get($group->id, presentationCriteria(), $snapshot->revision(), 'old-display', 'defaults:v1');
    expect($stale['status'])->toBe('refresh_required');
    expect($stale['displayVersion'])->toBe($saved->version);
    expect($stale['htmlChunks'])->toBe([]);
    $page = Livewire::test(GroupShow::class, ['group' => $group])->instance();
    expect(fn () => $page->loadCards(presentationCriteria(), $snapshot->revision(), 'bad-token', ['forged']))->toThrow(ValidationException::class);
});

test('dataset rechecks visibility and cards cannot use a cached image envelope after access changes', function () {
    $this->actingAs(User::factory()->create());
    $group = Group::factory()->create();
    Product::factory()->for($group)->create();
    $snapshot = app(CatalogSnapshots::class)->get($group->id);
    $this->getJson(route('catalog.groups.dataset', $group))->assertOk();
    $group->update(['is_active' => false]);
    $this->getJson(route('catalog.groups.dataset', $group))->assertNotFound();
    expect(fn () => app(CatalogCards::class)->get($group->id, presentationCriteria(), $snapshot->revision(), 'denied-test'))->toThrow(HttpException::class);
});
