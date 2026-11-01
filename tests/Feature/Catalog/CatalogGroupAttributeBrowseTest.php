<?php

use App\Models\Group;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogJourney;
use App\Services\CatalogNavigation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work'])));

function ga2Model(array $attributes = [], ?Group $parent = null): Group
{
    $group = Group::factory()->create(['parent_id' => $parent?->id, 'catalog_attributes' => (object) $attributes]);
    Product::factory()->for($group)->create(['profile_fields' => ['application' => 'Product-only']]);

    return $group;
}

test('Group attribute matching preserves literal strings and never inherits branch Product or tag facts', function () {
    $parent = Group::factory()->create(['catalog_attributes' => ['application' => ['Parent-only']]]);
    $values = ['0', '001', 'Case', 'case', ' 0 ', 'מים Multi Word', "\u{200D}0\u{200D}", "\u{00AD}0"];
    $groups = [];
    foreach ($values as $value) {
        $groups[] = ga2Model(['application' => [$value]], $parent);
    }
    $service = app(CatalogNavigation::class);
    foreach ($values as $index => $value) {
        $reply = $service->get(auth()->user(), ['attributeValues' => ['application' => [$value]]]);
        expect(array_column($reply['cards'], 'id'))->toBe([$groups[$index]->id])
            ->and($reply['query']['attributeValues'])->toBe(['application' => [$value]]);
    }
    foreach (['Parent-only', 'Product-only'] as $value) {
        expect($service->get(auth()->user(), ['attributeValues' => ['application' => [$value]]])['total'])->toBe(0);
    }
});

test('attributes OR within and AND across combine with independent tags while facets count the whole branch', function () {
    $a = ga2Model(['application' => ['A', 'B'], 'fluid_type' => ['Water']]);
    $b = ga2Model(['application' => ['B'], 'fluid_type' => ['Oil']]);
    $c = ga2Model(['application' => ['C'], 'fluid_type' => ['Water']]);
    $tag = GroupTagValue::factory()->create();
    $b->tagValues()->attach($tag, ['position' => 0]);
    $service = app(CatalogNavigation::class);
    $query = ['attributeValues' => ['application' => ['A', 'B'], 'fluid_type' => ['Water']]];
    $reply = $service->get(auth()->user(), $query);
    expect(array_column($reply['cards'], 'id'))->toBe([$a->id]);
    $facet = collect($reply['attributes'])->firstWhere('key', 'application');
    expect(array_column($facet['values'], 'count', 'value'))->toBe(['A' => 1, 'B' => 2, 'C' => 1]);
    expect($service->get(auth()->user(), [...$query, 'tagValueIds' => [$tag->id]])['total'])->toBe(0);
    $reply = $service->get(auth()->user(), ['attributeValues' => ['application' => ['Gone']], 'tagValueIds' => [$tag->id]]);
    $gone = collect(collect($reply['attributes'])->firstWhere('key', 'application')['values'])->firstWhere('value', 'Gone');
    expect($gone)->toBe(['value' => 'Gone', 'count' => 0, 'selected' => true, 'available' => false]);
});

test('missing and malformed documents stay truthful without hiding eligible cards or inventing future facts', function () {
    $missing = ga2Model();
    $bad = ga2Model();
    DB::table('groups')->where('id', $bad->id)->update(['catalog_attributes' => '["invalid"]']);
    $future = ga2Model(['future_fact' => ['Future']]);
    $cards = collect(app(CatalogNavigation::class)->get(auth()->user(), [])['cards'])->keyBy('id');
    expect($cards[$missing->id]['attributesStatus'])->toBe('missing')
        ->and($cards[$bad->id]['attributesStatus'])->toBe('unavailable')
        ->and($cards[$future->id]['attributesStatus'])->toBe('missing')
        ->and($cards[$future->id]['attributes'])->toBe([]);
    expect(fn () => app(CatalogNavigation::class)->get(auth()->user(), ['attributeValues' => ['future_fact' => ['Future']]]))->toThrow(ValidationException::class);
    expect($bad->fresh()->getRawOriginal('catalog_attributes'))->toBe('["invalid"]');
});

test('attribute facets include model twenty five and card paths and active Product counts use real ancestry', function () {
    $root = Group::factory()->create();
    $middle = Group::factory()->for($root, 'parent')->create();
    $deep = Group::factory()->for($middle, 'parent')->create();
    foreach (range(1, 25) as $index) {
        $group = ga2Model(['application' => [$index === 25 ? 'Last page fact' : 'Shared']], $deep);
    }
    Product::factory()->for($group)->create(['is_active' => false]);
    $reply = app(CatalogNavigation::class)->get(auth()->user(), ['branchId' => $deep->id]);
    expect($reply['cards'])->toHaveCount(24);
    $facet = collect($reply['attributes'])->firstWhere('key', 'application');
    expect(array_column($facet['values'], 'count', 'value'))->toBe(['Shared' => 24, 'Last page fact' => 1]);
    $last = app(CatalogNavigation::class)->get(auth()->user(), ['branchId' => $deep->id, 'page' => 2])['cards'][0];
    expect(array_column($last['ancestors'], 'id'))->toBe([$root->id, $middle->id, $deep->id])
        ->and($last['productCount'])->toBe(1);
});

test('empty root SSR JSON replies and return links adapt maps consistently before client normalization', function () {
    $group = ga2Model();
    $service = app(CatalogNavigation::class);
    $internal = $service->get(auth()->user(), [])['query'];
    expect($internal['attributeValues'])->toBe([]);
    $html = $this->get(route('catalog.index'))->assertOk()->getContent();
    preg_match('~<script[^>]*data-navigation-projection[^>]*>(.*?)</script>~s', $html, $match);
    $wire = json_decode($match[1], flags: JSON_THROW_ON_ERROR);
    expect($wire->query->attributeValues)->toBeInstanceOf(stdClass::class)->and($wire->query->tagValueIds)->toBe([]);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $snapshot = $xpath->query('//*[@*[name()="wire:snapshot"]]')->item(0)->getAttribute('wire:snapshot');
    $response = $this->postJson(Livewire::getUpdateUri(), ['components' => [[
        'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'loadNavigation', 'params' => [['attributeValues' => []]]]],
    ]]], ['X-Livewire' => 'true'])->assertOk();
    $reply = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
    expect($reply->components[0]->effects->returns[0]->query->attributeValues)->toBeInstanceOf(stdClass::class);
    $literalResponse = $this->postJson(Livewire::getUpdateUri(), ['components' => [[
        'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'loadNavigation', 'params' => [['attributeValues' => ['application' => [' 0 ', '001']]]]]],
    ]]], ['X-Livewire' => 'true'])->assertOk();
    $literalReply = json_decode($literalResponse->getContent(), flags: JSON_THROW_ON_ERROR);
    expect($literalReply->components[0]->effects->returns[0]->query->attributeValues->application)->toBe([' 0 ', '001']);
    $journey = app(CatalogJourney::class);
    $return = ['version' => 2, 'groupId' => (string) $group->id, 'productId' => null, 'catalog' => ['query' => $internal, 'anchor' => 'model-'.$group->id], 'products' => null];
    $encoded = $journey->encode($return);
    expect($journey->decode($encoded)['catalog']['query'])->toBe($internal);
    $html = $this->get($journey->catalogUrl($return))->assertOk()->getContent();
    preg_match('~data-journey-return="([^"]*)"~', $html, $match);
    $restored = json_decode(html_entity_decode($match[1], ENT_QUOTES), flags: JSON_THROW_ON_ERROR);
    expect($restored->query->attributeValues)->toBeInstanceOf(stdClass::class)
        ->and($restored->anchor)->toBe('model-'.$group->id);
});

test('parsed empty-map adaptation never admits nonempty list roots typed values or duplicate literals', function () {
    $service = app(CatalogNavigation::class);
    expect($service->validateQuery(['attributeValues' => []])['attributeValues'])->toBe([]);
    foreach ([[['A']], ['application' => [0]], ['application' => ['A', 'A']], ['application' => null], ['application' => ['']], ['application' => [str_repeat('x', 201)]]] as $bad) {
        expect(fn () => $service->validateQuery(['attributeValues' => $bad]))->toThrow(ValidationException::class);
    }
});

test('attribute criteria bound canonical UTF8 bytes and valid raw g2 preserves append and index literals', function () {
    $service = app(CatalogNavigation::class);
    $group = ga2Model(['application' => [' 0 ', '001', 'Case', 'case', 'מים']]);
    $values = [' 0 ', '001', 'Case', 'case', 'מים'];
    foreach (['append', 'indexed'] as $style) {
        $raw = 'g[version]=2';
        foreach ($values as $index => $value) {
            $raw .= '&g[attributes][application]['.($style === 'indexed' ? $index : '').']='.rawurlencode($value);
        }
        $html = $this->get(route('catalog.index').'?'.$raw)->assertOk()->assertDontSee('data-catalog-invalid-state', false)->getContent();
        preg_match('~<script[^>]*data-navigation-projection[^>]*>(.*?)</script>~s', $html, $match);
        $reply = json_decode($match[1], true);
        expect($reply['query']['attributeValues'])->toBe(['application' => $values])->and(array_column($reply['cards'], 'id'))->toBe([$group->id]);
    }
    $large = array_fill_keys(array_keys(config('group_attributes.definitions')), array_map(fn (int $n): string => str_repeat('א', 198).$n, range(1, 25)));
    expect(fn () => $service->validateQuery(['attributeValues' => $large]))->toThrow(ValidationException::class);
});
