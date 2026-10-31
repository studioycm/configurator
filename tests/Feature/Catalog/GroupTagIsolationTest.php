<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Livewire\Catalog\Index;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogNavigation;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;
use Mockery\MockInterface;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(fn () => $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work'])));

test('first page SSR and JSON use the same authorized model projection and normalized Unicode query', function () {
    $root = Group::factory()->create(['name' => 'Organizing branch']);
    $leaf = Group::factory()->for($root, 'parent')->create(['name' => 'שסתום / Valve']);
    Product::factory()->for($leaf)->create();
    $empty = Group::factory()->for($root, 'parent')->create(['name' => 'EMPTY-MODEL']);
    $this->get(route('catalog.index', ['g' => ['version' => '1', 'q' => 'שסתום /', 'branch' => (string) $root->id]]))
        ->assertOk()->assertSee('data-catalog-navigation', false)->assertSee('שסתום / Valve')->assertDontSee('EMPTY-MODEL');
    Livewire::test(Index::class)->call('loadNavigation', ['search' => 'שסתום /', 'branchId' => (string) $root->id, 'requestId' => 'new'])
        ->assertReturned(fn (array $reply): bool => $reply['query']['branchId'] === $root->id && array_column($reply['cards'], 'id') === [$leaf->id] && $reply['requestId'] === 'new');
});

test('branch links redirect to validated first-page context and route identity wins', function () {
    $root = Group::factory()->create();
    $leaf = Group::factory()->for($root, 'parent')->create();
    Product::factory()->for($leaf)->create();
    $this->get(route('catalog.groups.show', ['group' => $root, 'g' => ['version' => '1', 'branch' => (string) $leaf->id, 'mode' => 'tags']]))
        ->assertRedirect(route('catalog.index', ['g' => ['version' => 1, 'branch' => $root->id, 'mode' => 'tags']]));
    $this->get(route('catalog.groups.show', $leaf))->assertOk()->assertSee('data-catalog-snapshot');
});

test('tree JSON validates identity independently and does not mutate card criteria', function () {
    $root = Group::factory()->create();
    Group::factory()->for($root, 'parent')->create();
    $tree = app(CatalogNavigation::class)->get(auth()->user(), [])['treeVersion'];
    $page = Livewire::test(Index::class);
    $page->call('loadTreeChildren', (string) $root->id, '1', $tree, 'tree-one')
        ->assertReturned(fn (array $reply): bool => $reply['parentId'] === $root->id && count($reply['rows']) === 1);
    $page->call('loadTreeChildren', '01', '1', $tree, 'bad');
    expect($page->effects['returnsMeta'][0]['errors'])->not->toBeEmpty();
    $page->call('loadTreeChildren', $root->id, 1, str_repeat('f', 64), 'old')
        ->assertReturned(fn (array $reply): bool => $reply['status'] === 'refresh_required' && $reply['rows'] === []);
});

test('malformed first-page state is explained without rendering a wider match set', function () {
    $group = Group::factory()->create(['name' => 'MUST-NOT-BROADEN']);
    Product::factory()->for($group)->create();
    $this->get(route('catalog.index', ['g' => ['version' => '9', 'branch' => '01']]))->assertOk()
        ->assertSee('data-catalog-invalid-state', false)->assertDontSee('MUST-NOT-BROADEN');
    Livewire::test(Index::class)->call('loadNavigation', ['page' => '01']);
    $this->get(route('catalog.index', ['g' => ['version' => '1', 'branch' => '999999']]))->assertNotFound();
});

test('direct JSON navigation reauthorizes a withdrawn actor and hidden parents', function () {
    $page = Livewire::test(Index::class);
    $root = Group::factory()->create(['is_active' => false]);
    $page->call('loadNavigation', ['branchId' => $root->id]);
    expect($page->effects['returnsMeta'][0]['status'])->toBe(404);
    auth()->logout();
    $page->call('loadNavigation', []);
    expect($page->effects['returnsMeta'][0]['status'])->toBe(401);
});

test('same-label Group tags never become Product filters presets or configuration choices', function () {
    [$configurator, $definition] = canonicalDefinitionFixture();
    foreach ($definition['attributes'] as &$attribute) {
        $attribute['default_configurator_option_id'] = $attribute['options'][0]['id'];
    }
    unset($attribute);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $definition);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Pressure', 'label' => 'Pressure']);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['Pressure' => '001']]);
    Product::factory()->for($group)->create(['profile_fields' => ['Pressure' => '0']]);
    $category = GroupTagCategory::factory()->create(['label' => 'Pressure']);
    $tag = GroupTagValue::factory()->for($category, 'category')->create(['label' => '001']);
    $twin = GroupTagValue::factory()->for($category, 'category')->create(['label' => '001']);
    $group->tagValues()->attach($tag->id, ['position' => 0]);
    $before = Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->get('runtime');
    $this->get(route('catalog.index', ['g' => ['version' => '1', 'tags' => [$tag->id]]]))->assertOk();
    $group->tagValues()->sync([$twin->id => ['position' => 0]]);
    $this->get(route('catalog.products.show', ['product' => $product, 'g' => ['version' => '1', 'tags' => [$twin->id]], 'tagValueIds' => [$twin->id]]))->assertOk();
    $after = Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->get('runtime');
    expect($before['configurator_id'])->toBe($configurator->id)->and(array_filter($before['selections'], fn (mixed $value): bool => $value !== null))->toHaveCount(3)->and($after)->toBe($before);
    $left = $this->get(route('catalog.groups.show', ['group' => $group, 'g' => ['version' => '1', 'tags' => [$tag->id]]]))->assertOk()->getContent();
    $right = $this->get(route('catalog.groups.show', ['group' => $group, 'tagValueIds' => [$twin->id]]))->assertOk()->getContent();
    preg_match('/<script type="application\/json" data-catalog-snapshot>(.*?)<\/script>/s', $left, $a);
    preg_match('/<script type="application\/json" data-catalog-snapshot>(.*?)<\/script>/s', $right, $b);
    $snapshot = json_decode($a[1], true, flags: JSON_THROW_ON_ERROR);
    expect($snapshot['rows'])->toHaveCount(2)->and($snapshot['fields'])->not->toBeEmpty()->and($a[1])->toBe($b[1]);
});

test('SSR and actual JSON transport remain private and no-store', function () {
    $response = $this->get(route('catalog.index'))->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $snapshot = $xpath->query('//*[@*[name()="wire:snapshot"]]')->item(0)->getAttribute('wire:snapshot');
    $reply = $this->postJson(Livewire::getUpdateUri(), ['components' => [[
        'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'loadNavigation', 'params' => [['requestId' => 'real-transport']]]],
    ]]], ['X-Livewire' => 'true'])->assertOk()->assertJsonPath('components.0.effects.returns.0.requestId', 'real-transport');
    expect($reply->headers->get('Cache-Control'))->toContain('no-store', 'private');
});

test('unexpected JSON read failures are reported without leaking the internal exception', function () {
    $page = Livewire::test(Index::class);
    Exceptions::fake();
    $this->mock(CatalogNavigation::class, function (MockInterface $mock): void {
        $mock->shouldReceive('get')->once()->andThrow(new RuntimeException('Internal projection failure'));
    });
    $page->call('loadNavigation', []);
    expect($page->effects['returnsMeta'][0]['status'])->toBe(500);
    Exceptions::assertReported(RuntimeException::class);
    expect(json_encode($page->effects))->not->toContain('Internal projection failure');
});

test('K3-URL-CODEC-001 actual SSR validates the raw Group namespace and array spelling', function (string $raw, bool $valid) {
    $category = GroupTagCategory::factory()->create();
    foreach ([31 => 'URL-MODEL-A', 47 => 'URL-MODEL-B'] as $id => $name) {
        $tag = GroupTagValue::factory()->for($category, 'category')->create(['id' => $id]);
        $group = Group::factory()->create(['name' => $name]);
        Product::factory()->for($group)->create();
        $group->tagValues()->attach($tag->id, ['position' => 0]);
    }
    $response = $this->get(route('catalog.index').'?'.$raw)->assertOk();
    if (! $valid) {
        $response->assertSee('data-catalog-invalid-state', false)->assertDontSee('data-catalog-navigation', false)->assertDontSee('URL-MODEL-A')->assertDontSee('URL-MODEL-B');

        return;
    }
    $response->assertSee('data-catalog-navigation', false)->assertDontSee('data-catalog-invalid-state', false);
    preg_match('/<script type="application\/json" data-navigation-projection>(.*?)<\/script>/s', $response->getContent(), $match);
    $projection = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    expect($projection['query']['tagValueIds'])->toBe([31, 47])->and(array_column($projection['cards'], 'name'))->toBe(['URL-MODEL-A', 'URL-MODEL-B']);
})->with([
    'indexed' => ['g[version]=1&g[tags][0]=31&g[tags][1]=47', true],
    'append' => ['g[version]=1&g[tags][]=31&g[tags][]=47', true],
    'reversed contiguous' => ['g[version]=1&g[tags][1]=47&g[tags][0]=31', true],
    'scalar' => ['g=bad', false],
    'bare scalar' => ['g', false],
    'scalar then map' => ['g=bad&g[version]=1', false],
    'repeated scalar' => ['g=bad&g=worse', false],
    'repeated version' => ['g[version]=1&g[version]=1', false],
    'repeated search' => ['g[version]=1&g[q]=one&g[q]=two', false],
    'duplicate index' => ['g[version]=1&g[tags][0]=31&g[tags][0]=47', false],
    'sparse index' => ['g[version]=1&g[tags][0]=31&g[tags][2]=47', false],
    'leading-zero index' => ['g[version]=1&g[tags][00]=31&g[tags][1]=47', false],
    'mixed append and index' => ['g[version]=1&g[tags][0]=31&g[tags][]=47', false],
    'duplicate value' => ['g[version]=1&g[tags][]=31&g[tags][]=31', false],
    'scalar tags' => ['g[version]=1&g[tags]=31', false],
    'unknown' => ['g[version]=1&g[future]=bad', false],
]);
