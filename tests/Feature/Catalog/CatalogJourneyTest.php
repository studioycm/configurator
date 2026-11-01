<?php

use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAvailability;
use App\Services\CatalogJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function journeyFixture(): array
{
    $actor = User::factory()->create(['email' => 'journey-viewer@example.test']);
    $root = Group::factory()->create(['name' => 'שורש']);
    $group = Group::factory()->for($root, 'parent')->create();
    $tag = GroupTagValue::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => 'pressure']);
    GroupFilter::factory()->for($group)->create(['property_key' => 'code']);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['pressure' => '0', 'code' => '001']]);
    $state = ['version' => 2, 'groupId' => (string) $group->id, 'productId' => (string) $product->id,
        'catalog' => ['query' => ['branchId' => $root->id, 'search' => 'שסתום é', 'tagValueIds' => [$tag->id], 'attributeValues' => [], 'mode' => 'tags', 'page' => 2], 'anchor' => 'model-'.$group->id],
        'products' => ['criteria' => ['version' => 1, 'filters' => ['pressure' => '0', 'code' => '001'], 'subGroupId' => null, 'precedence' => ['filter:code', 'filter:pressure']], 'anchor' => 'product-'.$product->id]];

    return [$actor, $root, $group, $product, $state];
}

test('legacy return links upgrade while r2 attributes retain exact values through the real route chain', function () {
    [$actor, , $group, $product, $state] = journeyFixture();
    $this->actingAs($actor);
    $journey = app(CatalogJourney::class);
    $legacy = $state;
    $legacy['version'] = 1;
    unset($legacy['catalog']['query']['attributeValues']);
    $raw = fn (mixed $wire): string => rtrim(strtr(base64_encode(json_encode($wire, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    expect($journey->decode($raw($legacy)))->toBe($state);
    $legacy['catalog']['query']['attributeValues'] = [];
    expect(fn () => $journey->decode($raw($legacy)))->toThrow(ValidationException::class);
    $state['catalog']['query']['attributeValues'] = ['application' => [' 0 ', '001', 'Case', 'case', 'מים']];
    $encoded = $journey->encode($state);
    expect($journey->decode($encoded))->toBe($state);
    $html = $this->get(route('catalog.products.show', ['product' => $product, 'r' => $encoded, 'configuration' => '001-raw']))->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $hrefs = [];
    foreach ((new DOMXPath($dom))->query('//nav[@aria-label="Breadcrumb"]//a') as $link) {
        $hrefs[] = $link->getAttribute('href');
    }
    expect($hrefs)->toContain($journey->catalogUrl($state), $journey->groupUrl($state));
    $this->get($journey->groupUrl($state))->assertOk()->assertDontSee(__('catalog.journey.reset'));
    $this->get($journey->catalogUrl($state))->assertOk()->assertDontSee(__('catalog.journey.reset'));
    $wire = $journey->forClient($state);
    $wire['catalog']['query']['attributeValues'] = [];
    expect(fn () => $journey->decode($raw($wire)))->toThrow(ValidationException::class);
});

test('raw r2 attribute collections retain JSON list identity at the Group return entry', function (string $collection, bool $valid) {
    [$actor, , $group, , $state] = journeyFixture();
    $journey = app(CatalogJourney::class);
    $wire = $journey->forClient($state);
    $values = json_decode($collection, flags: JSON_THROW_ON_ERROR);
    $wire['catalog']['query']['attributeValues']->application = $values;
    $encoded = rtrim(strtr(base64_encode(json_encode($wire, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    $response = $this->actingAs($actor)->get(route('catalog.groups.show', ['group' => $group, 'r' => $encoded]))->assertOk();
    if (! $valid) {
        $response->assertSee(__('catalog.journey.reset'));
        expect(fn () => $journey->decode($encoded))->toThrow(ValidationException::class);

        return;
    }
    $response->assertDontSee(__('catalog.journey.reset'));
    expect($journey->decode($encoded)['catalog']['query']['attributeValues'])->toBe($values === [] ? [] : ['application' => $values]);
})->with([
    'indexed object' => ['{"0":"literal"}', false],
    'empty object' => ['{}', false],
    'empty list' => ['[]', true],
    'exact string list' => ['[" 0 ","001","Case","case","מים"]', true],
]);

test('journeys retain Unicode exact values and chronology without writing or querying each encoded link', function () {
    [$actor, , $group, $product, $state] = journeyFixture();
    $journey = app(CatalogJourney::class);
    $valid = $journey->validate($actor, $state);
    expect($valid)->toBe($state)->and($journey->decode($journey->encode($valid)))->toBe($valid);
    DB::enableQueryLog();
    DB::flushQueryLog();
    for ($i = 0; $i < 24; $i++) {
        $journey->catalogUrl($valid);
        $journey->groupUrl($valid);
    }
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
    parse_str(parse_url($journey->groupUrl($valid), PHP_URL_QUERY), $query);
    expect($query['d']['filters'])->toBe(['pressure' => '0', 'code' => '001'])
        ->and($query['d']['precedence'])->toBe(['filter:code', 'filter:pressure'])
        ->and($journey->decode($query['r'])['productId'])->toBe((string) $product->id);
});

test('return transport rejects malformed bytes maps lists and versions', function (mixed $encoded) {
    expect(fn () => app(CatalogJourney::class)->decode($encoded))->toThrow(ValidationException::class);
})->with([
    'array' => [[]], 'padding' => ['e30='], 'non alphabet' => ['***'],
    'invalid utf8' => [rtrim(strtr(base64_encode("\xff"), '+/', '-_'), '=')],
    'list root' => [rtrim(strtr(base64_encode('[]'), '+/', '-_'), '=')],
    'unsupported shape' => [rtrim(strtr(base64_encode('{"version":2}'), '+/', '-_'), '=')],
]);

test('JSON filters must be a map even when empty and wire size is encoded bytes', function () {
    [, , , , $state] = journeyFixture();
    $j = app(CatalogJourney::class);
    $state['products']['criteria']['filters'] = [];
    $state['products']['criteria']['precedence'] = [];
    $encoded = $j->encode($state);
    $json = base64_decode(strtr($encoded, '-_', '+/'));
    expect($json)->toContain('"filters":{}');
    $bad = str_replace('"filters":{}', '"filters":[]', $json);
    expect(fn () => $j->decode(rtrim(strtr(base64_encode($bad), '+/', '-_'), '=')))->toThrow(ValidationException::class);
    $padding = 6144 - strlen($json);
    $atLimit = rtrim(strtr(base64_encode($json.str_repeat(' ', $padding)), '+/', '-_'), '=');
    expect(strlen($atLimit))->toBe(8192)->and($j->decode($atLimit))->toBe($state);
    expect(fn () => $j->decode($atLimit.'A'))->toThrow(ValidationException::class);
});

test('canonical identifiers explicit keys and exact Product criteria are enforced', function (string $kind) {
    [$actor, , , , $state] = journeyFixture();
    match ($kind) {
        'leading zero' => $state['groupId'] = '01',
        'boolean' => $state['groupId'] = true,
        'float' => $state['groupId'] = 1.0,
        'unknown' => $state['returnUrl'] = 'https://elsewhere.test',
        'anchor' => $state['catalog']['anchor'] = 'button,script',
        'foreign field' => $state['products']['criteria']['filters']['foreign'] = '001',
        'numeric value' => $state['products']['criteria']['filters']['pressure'] = 0,
        'wrong chronology' => $state['products']['criteria']['precedence'] = ['filter:code'],
    };
    expect(fn () => app(CatalogJourney::class)->validate($actor, $state))->toThrow(ValidationException::class);
})->with(['leading zero', 'boolean', 'float', 'unknown', 'anchor', 'foreign field', 'numeric value', 'wrong chronology']);

test('current owners and ancestry are checked rather than trusting an internally consistent old link', function (string $change) {
    [$actor, $root, $group, $product, $state] = journeyFixture();
    match ($change) {
        'ancestor' => $root->update(['is_active' => false]),
        'inactive product' => $product->update(['is_active' => false]),
        'reassigned product' => $product->update(['group_id' => Group::factory()->create()->id]),
        'branch' => Group::factory()->for($group, 'parent')->create(),
    };
    expect(fn () => app(CatalogJourney::class)->validate($actor, $state))->toThrow(ValidationException::class);
})->with(['ancestor', 'inactive product', 'reassigned product', 'branch']);

test('actual model and breadcrumb links carry a validated independent return journey', function () {
    [$actor, $root, $group, $product, $state] = journeyFixture();
    $this->actingAs($actor);
    $j = app(CatalogJourney::class);
    $source = $state;
    $source['catalog']['query']['search'] = '';
    $source['catalog']['query']['tagValueIds'] = [];
    $source['catalog']['query']['page'] = 1;
    $source['products'] = null;
    $source['productId'] = null;
    $index = $this->get($j->catalogUrl($source))->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($index);
    $xp = new DOMXPath($dom);
    $link = $xp->query('//*[@data-catalog-model-id="'.$group->id.'"]//h2/a')->item(0)->getAttribute('href');
    parse_str(parse_url($link, PHP_URL_QUERY), $query);
    expect($j->decode($query['r']))->toBe($source);
    $this->get($link)->assertOk()->assertSee('data-catalog-journey=', false)->assertDontSee(__('catalog.journey.reset'));
    $encoded = $j->encode($state);
    $productHtml = $this->get(route('catalog.products.show', ['product' => $product->id, 'r' => $encoded]))->assertOk()->getContent();
    @$dom->loadHTML($productHtml);
    $xp = new DOMXPath($dom);
    $hrefs = [];
    foreach ($xp->query('//nav[@aria-label="Breadcrumb"]//a') as $a) {
        $hrefs[] = $a->getAttribute('href');
    }
    expect($hrefs)->toContain($j->catalogUrl($state), $j->groupUrl($state));
    $this->get($j->groupUrl($state))->assertOk()->assertDontSee(__('catalog.journey.reset'));
});

test('valid but foreign journeys never attach another routes Group or Product context', function (string $routeType) {
    [$actor, , , , $state] = journeyFixture();
    $otherGroup = Group::factory()->create();
    $other = Product::factory()->for($otherGroup)->create();
    $params = ['r' => app(CatalogJourney::class)->encode($state), $routeType => $routeType === 'group' ? $otherGroup->id : $other->id];
    $response = $this->actingAs($actor)->get(route($routeType === 'group' ? 'catalog.groups.show' : 'catalog.products.show', $params))->assertOk();
    $response->assertSee(__('catalog.journey.reset'));
    expect($response->getContent())->not->toContain(e(app(CatalogJourney::class)->groupUrl($state)));
})->with(['group', 'product']);

test('stale return context resets with a notice while actual withdrawn targets remain unavailable', function () {
    [$actor, $root, $group, $product, $state] = journeyFixture();
    $this->actingAs($actor);
    $url = route('catalog.products.show', ['product' => $product->id, 'r' => app(CatalogJourney::class)->encode($state)]);
    $this->get(route('catalog.products.show', $product))->assertOk()->assertDontSee(__('catalog.journey.reset'));
    $this->get(route('catalog.products.show', ['product' => $product->id, 'r' => ['unsafe']]))->assertOk()->assertSee(__('catalog.journey.reset'));
    $root->update(['is_active' => false]);
    $this->get($url)->assertNotFound();
    $root->update(['is_active' => true]);
    $product->update(['group_id' => Group::factory()->create()->id]);
    $this->get($url)->assertOk()->assertSee(__('catalog.journey.reset'));
});

test('compatibility redirects keep supported return and Product state without forwarding arbitrary query', function () {
    [$actor, , $group, $product, $state] = journeyFixture();
    $this->actingAs($actor);
    $r = app(CatalogJourney::class)->encode($state);
    $this->get('/catalog/products/'.$product->id.'?'.http_build_query(['r' => $r, 'returnUrl' => 'https://elsewhere.test']))
        ->assertRedirect(route('catalog.products.show', ['product' => $product->id, 'r' => $r]));
    $response = $this->get('/catalog/groups/'.$group->id.'?'.http_build_query(['r' => $r, 'd' => $state['products']['criteria'], 'unknown' => 'drop']))->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['r'])->toBe($r)->and($query['d']['filters']['code'])->toBe('001')->and($query)->not->toHaveKey('unknown');
    $this->get($response->headers->get('Location'))->assertOk()->assertDontSee(__('catalog.journey.reset'));
});

test('return recovery never disguises an infrastructure failure as a reset', function () {
    [$actor, , , , $state] = journeyFixture();
    $j = app(CatalogJourney::class);
    $encoded = $j->encode($state);
    $this->mock(CatalogAvailability::class)->shouldReceive('visibleGroups')->once()->andThrow(new RuntimeException('Infrastructure failed'));
    app()->forgetInstance(CatalogJourney::class);
    expect(fn () => app(CatalogJourney::class)->resolve($actor, $encoded))->toThrow(RuntimeException::class, 'Infrastructure failed');
});

test('numeric-key JSON objects cannot masquerade as return lists', function (string $field) {
    [, , , , $state] = journeyFixture();
    $j = app(CatalogJourney::class);
    $raw = json_decode(base64_decode(strtr($j->encode($state), '-_', '+/')));
    if ($field === 'tags') {
        $raw->catalog->query->tagValueIds = (object) $raw->catalog->query->tagValueIds;
    } else {
        $raw->products->criteria->precedence = (object) $raw->products->criteria->precedence;
    }
    $encoded = rtrim(strtr(base64_encode(json_encode($raw)), '+/', '-_'), '=');
    expect(fn () => $j->decode($encoded))->toThrow(ValidationException::class);
})->with(['tags', 'precedence']);

test('another eligible Product in the same Group cannot borrow the source Product return context', function () {
    [$actor, , $group, , $state] = journeyFixture();
    $other = Product::factory()->for($group)->create();
    $this->actingAs($actor)->get(route('catalog.products.show', ['product' => $other->id, 'r' => app(CatalogJourney::class)->encode($state)]))
        ->assertOk()->assertSee(__('catalog.journey.reset'));
});

test('an unfiltered return keeps an object filter map in the actual Group client data', function () {
    [$actor, , , , $state] = journeyFixture();
    $state['products']['criteria']['filters'] = [];
    $state['products']['criteria']['precedence'] = [];
    $j = app(CatalogJourney::class);
    $html = $this->actingAs($actor)->get($j->groupUrl($state))->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $data = (new DOMXPath($dom))->query('//*[@data-catalog-journey]')->item(0)->getAttribute('data-catalog-journey');
    expect(json_decode($data)->products->criteria->filters)->toBeInstanceOf(stdClass::class);
});

test('numeric property key zero remains a map through validation transport and the generated Group return route', function () {
    $actor = User::factory()->create(['email' => 'numeric-journey@example.test']);
    $group = Group::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => '0']);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['0' => '001']]);
    $state = ['version' => 2, 'groupId' => (string) $group->id, 'productId' => (string) $product->id, 'catalog' => null,
        'products' => ['criteria' => ['version' => 1, 'filters' => ['0' => '001'], 'subGroupId' => null, 'precedence' => ['filter:0']], 'anchor' => 'product-'.$product->id]];
    $j = app(CatalogJourney::class);
    $validated = $j->validate($actor, $state);
    $encoded = $j->encode($validated);
    expect($j->decode($encoded))->toBe($state);
    $wire = json_decode(base64_decode(strtr($encoded, '-_', '+/')));
    expect($wire->products->criteria->filters)->toBeInstanceOf(stdClass::class)
        ->and($wire->products->criteria->filters->{'0'})->toBe('001');
    $url = $j->groupUrl($validated);
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query['d']['filters'][0])->toBe('001')->and($query['d']['precedence'])->toBe(['filter:0']);
    $html = $this->actingAs($actor)->get($url)->assertOk()->assertDontSee(__('catalog.journey.reset'))->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $data = (new DOMXPath($dom))->query('//*[@data-catalog-journey]')->item(0)->getAttribute('data-catalog-journey');
    expect(json_decode($data)->products->criteria->filters->{'0'})->toBe('001');
    $wire->products->criteria->filters = ['001'];
    $list = rtrim(strtr(base64_encode(json_encode($wire)), '+/', '-_'), '=');
    expect(fn () => $j->decode($list))->toThrow(ValidationException::class);
});
