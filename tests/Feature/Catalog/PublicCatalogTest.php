<?php

use App\Livewire\Catalog\GroupShow;
use App\Livewire\Catalog\Index;
use App\Livewire\Catalog\ProductShow;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\SubGroup;
use App\Models\User;
use App\Services\CatalogCards;
use App\Services\CatalogPolicy;
use App\Services\CatalogRevisions;
use App\Services\CatalogSnapshots;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

test('group breadcrumbs and escaped heading belong to the page header', function () {
    $root = Group::factory()->create(['name' => 'Actual root']);
    $group = Group::factory()->for($root, 'parent')->create(['name' => 'Leaf <script>unsafe</script>']);

    $html = $this->get(route('catalog.groups.show', $group))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//h1')->length)->toBe(1);
    expect($xpath->query('//*[@aria-label="Breadcrumb"]/ancestor::header')->length)->toBe(1);
    expect(trim($xpath->evaluate('string(//header//h1)')))->toBe($group->name);
    expect($xpath->query('//header//h1/script')->length)->toBe(0);
});

test('product header retains its title and ancestor breadcrumb without a duplicate page heading', function () {
    $group = Group::factory()->create(['name' => 'Product family']);
    $product = Product::factory()->for($group)->create(['product_name' => 'Actual product']);

    $html = $this->get(route('catalog.products.show', $product))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//h1')->length)->toBe(1);
    expect(trim($xpath->evaluate('string(//header//h1)')))->toBe('Actual product');
    expect($xpath->query('//*[@aria-label="Breadcrumb"]/ancestor::header')->length)->toBe(1);
});

function publicCatalogCards(Group $group, array $filters = [], ?string $preset = null): array
{
    $snapshot = app(CatalogSnapshots::class)->get($group->id);

    return app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => $filters, 'subGroupId' => $preset,
        'precedence' => [...($preset === null ? [] : ['subgroup:'.$preset]), ...array_map(fn ($key) => 'filter:'.$key, array_keys($filters))]], $snapshot->revision(), 'test-request');
}

function publicCatalogCardHtml(Group $group): string
{
    return implode('', publicCatalogCards($group)['htmlChunks']);
}

test('signed in users can open the catalog before products are imported', function () {
    $this->get(route('catalog.index'))->assertOk()->assertSeeLivewire(Index::class)->assertSee('The catalog is being prepared.')->assertDontSee('D060');
});

test('catalog navigation follows actual ancestors and branches without descendant cards', function () {
    $root = Group::factory()->create(['name' => 'Actual root']);
    $leaf = Group::factory()->for($root, 'parent')->create(['name' => 'Actual leaf']);
    $product = Product::factory()->for($leaf)->create(['product_name' => 'Actual product']);
    $this->get(route('catalog.index'))->assertSee('Actual root')->assertDontSee('Actual leaf');
    $this->get(route('catalog.groups.show', $root))->assertSee('Actual leaf')->assertDontSee('Actual product')->assertDontSee('data-catalog-snapshot');
    $this->get(route('catalog.groups.show', $leaf))->assertSeeInOrder(['Actual root', 'Actual leaf'])->assertSee('data-catalog-snapshot')->assertDontSee($product->product_code);
    $this->get(route('catalog.products.show', $product))->assertSee('Actual product')->assertSeeInOrder(['Actual root', 'Actual leaf']);
});

test('a leaf fetches all its own products in code order through a separate JSON action', function () {
    $group = Group::factory()->create();
    for ($i = 11; $i >= 1; $i--) {
        Product::factory()->for($group)->create(['product_code' => sprintf('CODE-%02d', $i)]);
    }
    $foreign = Product::factory()->create(['product_code' => 'FOREIGN']);
    Livewire::test(GroupShow::class, ['group' => $group])->assertDontSee('CODE-01')->assertDontSee('Product pagination')
        ->call('loadCards', ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], '1', 'cards-1')
        ->assertReturned(function ($response) use ($foreign): bool {
            $html = implode('', $response['htmlChunks']);
            expect($response['total'])->toBe(11)->and($response['status'])->toBe('ready');
            expect($html)->toContain('CODE-01', 'CODE-11')->not->toContain($foreign->product_code);
            expect(strpos($html, 'CODE-01'))->toBeLessThan(strpos($html, 'CODE-11'));

            return true;
        });
});

test('public product facts are escaped and unassigned configuration remains honest', function () {
    $product = Product::factory()->create(['product_name' => 'Visitor name', 'product_code' => '000123', 'description' => '<script>unsafe</script>', 'properties' => ['operating_pressure' => '25 bar']]);
    $this->get(route('catalog.products.show', $product))->assertOk()->assertSeeLivewire(ProductShow::class)->assertSee('Visitor name')->assertSee('000123')->assertSee('25 bar')
        ->assertSee('<script>unsafe</script>')->assertDontSee('<script>unsafe</script>', false)->assertSee('Configuration is not available for this product.')->assertDontSee('Configuration Code');
});

test('unknown catalog records return not found', function () {
    $this->get('/dashboard/catalog/groups/999999')->assertNotFound();
    $this->get('/dashboard/catalog/products/999999')->assertNotFound();
});

test('product cards preserve literal zero values and omit blank or malformed properties', function () {
    $group = Group::factory()->make(['id' => 2, 'name' => 'Actual leaf']);
    $product = Product::factory()->make(['id' => 1, 'product_code' => 'ZERO', 'properties' => ['Working_Pressure' => '0', 'Connection_Type' => '', 'Connection_Size' => null, 'Model' => ['invalid']]]);
    $fields = [['key' => 'Working_Pressure', 'label' => 'Pressure']];
    $appearance = CatalogPolicy::resultSettings(null);
    $html = view('components.catalog.product-card', compact('product', 'group', 'fields', 'appearance'))->render();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect(trim($xpath->evaluate('string(//dd)')))->toBe('0');
    expect($xpath->query('//dt[contains(@class,"sr-only")]')->length)->toBe(1);
    expect($html)->not->toContain('—', 'invalid');
});

test('group cards lead with code then real hierarchy and escaped property values without labels', function () {
    $root = Group::factory()->create(['name' => 'Main family']);
    $leaf = Group::factory()->for($root, 'parent')->create(['name' => 'Product series', 'result_settings' => ['card_properties' => ['Working_Pressure', 'Connection_Type', 'Model', 'C'], 'card_only_differences' => false]]);
    Product::factory()->for($leaf)->create(['product_code' => '000123', 'product_name' => 'Former card title', 'properties' => ['C' => '<final dimension>', 'Model' => 'Model 1', 'Working_Pressure' => '25 bar', 'Connection_Type' => 'Flange'], 'parts' => ['Part1' => 'Private part'], 'extra_data' => ['internal' => 'Private extra']]);
    $html = publicCatalogCardHtml($leaf);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $card = $xpath->query('//article')->item(0);
    expect($xpath->evaluate('string(.//h3)', $card))->toBe('000123');
    expect($card->textContent)->toContain('Main family', 'Product series', '25 bar', 'Flange', 'Model 1', '<final dimension>')->not->toContain('Former card title', 'Working_Pressure', 'Connection_Type', 'Private part', 'Private extra');
    expect($html)->toContain('&lt;final dimension&gt;')->not->toContain('<final dimension>');
    expect($xpath->query('.//dt[contains(@class,"sr-only")]', $card)->length)->toBe(4);
    expect(collect($xpath->query('.//dd', $card))->map(fn ($node) => trim($node->textContent))->all())->toBe(['25 bar', 'Flange', 'Model 1', '<final dimension>']);
    expect($xpath->query('.//a', $card)->length)->toBe(1);
    expect($xpath->query('.//a/header//h3', $card)->length)->toBe(1);
});

test('group cards display selected properties and show none when the default has no filters', function () {
    $group = Group::factory()->create(['result_settings' => ['card_properties' => ['Model', 'Working_Pressure'], 'card_only_differences' => false]]);
    $product = Product::factory()->for($group)->create(['product_code' => 'CARD-SETTINGS', 'properties' => ['Working_Pressure' => '25 bar', 'Connection_Type' => 'Flange', 'Model' => '<Model 1>']]);
    $html = publicCatalogCardHtml($group);
    expect($html)->toContain('&lt;Model 1&gt;', '25 bar')->not->toContain('<Model 1>', 'Flange');
    expect(strpos($html, '&lt;Model 1&gt;'))->toBeLessThan(strpos($html, '25 bar'));
    DB::transaction(function () use ($group): void {
        $group->update(['result_settings' => ['card_properties' => []]]);
        app(CatalogRevisions::class)->advance([$group->id]);
    });
    expect(publicCatalogCardHtml($group))->toContain($product->product_code)->not->toContain('25 bar', 'Model 1');
});

test('default card properties follow group filters and their order', function (array $settings) {
    $group = Group::factory()->create(['result_settings' => [...$settings, 'card_only_differences' => false]]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'sort_order' => 2]);
    $first = GroupFilter::factory()->for($group)->create(['property_key' => 'Connection_Type', 'sort_order' => 1]);
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '25 bar', 'Connection_Type' => 'Flange', 'Model' => 'Card-only model']]);
    $html = publicCatalogCardHtml($group);
    expect($html)->toContain('Flange', '25 bar')->not->toContain('Card-only model');
    expect(strpos($html, 'Flange'))->toBeLessThan(strpos($html, '25 bar'));
    DB::transaction(function () use ($first, $group): void {
        $first->update(['property_key' => 'Model']);
        app(CatalogRevisions::class)->advance([$group->id]);
    });
    $html = publicCatalogCardHtml($group);
    expect($html)->toContain('Card-only model', '25 bar')->not->toContain('Flange');
    expect(strpos($html, 'Card-only model'))->toBeLessThan(strpos($html, '25 bar'));
})->with(['unset' => [[]], 'empty selection' => [['card_properties' => []]]]);

test('presets are embedded once and card requests accept only an owned preset', function () {
    $group = Group::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Pressure']);
    Product::factory()->count(2)->for($group)->sequence(['properties' => ['Working_Pressure' => '10']], ['properties' => ['Working_Pressure' => '16']])->create();
    $low = SubGroup::factory()->for($group)->create(['label' => 'Low pressure', 'property_key' => 'Working_Pressure', 'allowed_values' => ['10']]);
    $high = SubGroup::factory()->for($group)->create(['label' => 'High pressure', 'property_key' => 'Working_Pressure', 'allowed_values' => ['16']]);
    $page = Livewire::test(GroupShow::class, ['group' => $group])->assertSee('Filters')->assertSee('Sub Groups')->assertSee('profiles')->assertSee('RESET')->assertDontSee('Clear subgroup')->assertDontSee('Clear filters')->assertSet('groupId', (string) $group->id);
    expect(substr_count($page->html(), 'data-catalog-snapshot'))->toBe(1);
    expect(publicCatalogCards($group)['total'])->toBe(2);
    expect(publicCatalogCards($group, preset: (string) $low->id)['total'])->toBe(1);
    expect(publicCatalogCards($group, preset: (string) $high->id)['total'])->toBe(1);
});

test('a result threshold fetches every match at the boundary and no cards above it', function () {
    $group = Group::factory()->create(['result_settings' => ['max_results' => 2, 'default_page_size' => 1]]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure']);
    Product::factory()->count(2)->for($group)->sequence(['product_code' => 'MATCH-01'], ['product_code' => 'MATCH-02'])->create(['properties' => ['Working_Pressure' => '25']]);
    Product::factory()->for($group)->create(['product_code' => 'OTHER-03', 'properties' => ['Working_Pressure' => '16']]);
    expect(publicCatalogCards($group))->toMatchArray(['status' => 'above_threshold', 'total' => 3, 'htmlChunks' => []]);
    $response = publicCatalogCards($group, ['Working_Pressure' => '25']);
    expect($response['status'])->toBe('ready')->and($response['total'])->toBe(2)->and(implode('', $response['htmlChunks']))->toContain('MATCH-01', 'MATCH-02')->not->toContain('OTHER-03');
    expect(publicCatalogCards($group)['htmlChunks'])->toBe([]);
});

test('all results bypass the threshold and return chunks of at most 24 without pagination', function () {
    $group = Group::factory()->create(['result_settings' => ['max_results' => 'all', 'default_page_size' => 24]]);
    Product::factory()->count(25)->for($group)->sequence(fn ($sequence): array => ['product_code' => sprintf('ALL-%02d', $sequence->index + 1)])->create();
    $response = publicCatalogCards($group);
    expect($response['total'])->toBe(25)->and($response['htmlChunks'])->toHaveCount(2);
    expect(substr_count($response['htmlChunks'][0], '<article'))->toBe(24)->and(substr_count($response['htmlChunks'][1], '<article'))->toBe(1);
    expect(implode('', $response['htmlChunks']))->toContain('ALL-01', 'ALL-25')->not->toContain('Product pagination');
});

test('the running catalog does not load retired POC routes or schema', function () {
    foreach (['/admin/config-engine-demo', '/admin/product-configurations', '/admin/option-rules'] as $url) {
        $this->get($url)->assertNotFound();
    }
    foreach (['catalog_groups', 'product_profiles', 'config_profiles', 'option_rules', 'product_configurations', 'file_attachments'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});

test('a difference in product twenty five is shown in every earlier chunk with stable missing slots', function () {
    $group = Group::factory()->create(['result_settings' => ['card_properties' => ['Model', 'Working_Pressure'], 'card_show_labels' => true]]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Pressure']);
    Product::factory()->count(24)->for($group)->sequence(fn ($sequence): array => ['product_code' => sprintf('DIFF-%02d', $sequence->index)])
        ->create(['properties' => ['Model' => 'Same', 'Working_Pressure' => '0']]);
    Product::factory()->for($group)->create(['product_code' => 'DIFF-24', 'properties' => ['Model' => null, 'Working_Pressure' => '0']]);

    $response = publicCatalogCards($group);
    expect($response['htmlChunks'])->toHaveCount(2);
    expect(substr_count($response['htmlChunks'][0], 'data-property-key="Model"'))->toBe(24);
    expect($response['htmlChunks'][0])->not->toContain('data-property-key="Working_Pressure"');
    expect($response['htmlChunks'][1])->toContain('data-property-key="Model"', '—');
    expect($response['propertiesNotice'])->toBeNull();
});

test('a card exposes one escaped native link with configured labels and appearance', function () {
    $group = Group::factory()->create(['name' => 'Leaf <script>bad</script>', 'result_settings' => [
        'card_properties' => ['Working_Pressure', 'Model'], 'card_only_differences' => false,
        'card_show_labels' => true, 'card_property_layout' => 'below_center',
        'card_property_columns' => 1, 'card_padding_block' => 0, 'card_padding_inline' => 20,
    ]]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Pressure <img onerror=bad>']);
    $product = Product::factory()->for($group)->create(['product_code' => 'LINK-01', 'properties' => ['Working_Pressure' => '01', 'Model' => '<script>alert(1)</script>']]);
    $html = publicCatalogCardHtml($group);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $link = $xpath->query('//article/a')->item(0);

    expect($link->getAttribute('href'))->toBe(route('catalog.products.show', $product->id));
    expect($xpath->query('//article//a')->length)->toBe(1);
    expect($xpath->query('//article//script | //article//img')->length)->toBe(0);
    expect($xpath->evaluate('string(//dt[1])'))->toBe('Pressure <img onerror=bad>');
    expect($xpath->evaluate('string(//dd[1])'))->toBe('01');
    expect($html)->toContain('data-property-layout="below_center"', 'data-property-columns="1"', '--catalog-card-padding-block:0px', '--catalog-card-padding-inline:20px');
    expect($link->getAttribute('aria-labelledby'))->toBe('catalog-card-title-'.$group->id.'-'.$product->id);
});

test('ordinary and preset matches determine the displayed differences using exact result identities', function () {
    $group = Group::factory()->create(['result_settings' => ['card_properties' => ['Model', 'Working_Pressure']]]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure']);
    $first = Product::factory()->for($group)->create(['product_code' => 'PAIR-A', 'properties' => ['Model' => 'A', 'Working_Pressure' => '10']]);
    $second = Product::factory()->for($group)->create(['product_code' => 'PAIR-B', 'properties' => ['Model' => 'B', 'Working_Pressure' => '10']]);
    Product::factory()->for($group)->create(['product_code' => 'OTHER', 'properties' => ['Model' => 'C', 'Working_Pressure' => '16']]);
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Model', 'allowed_values' => ['A', 'B']]);

    $response = publicCatalogCards($group, ['Working_Pressure' => '10'], (string) $preset->id);
    $html = implode('', $response['htmlChunks']);
    expect($response['total'])->toBe(2)->and($html)->toContain('data-product-id="'.$first->id.'"', 'data-product-id="'.$second->id.'"', 'data-property-key="Model"')->not->toContain('OTHER', 'data-property-key="Working_Pressure"');
});
