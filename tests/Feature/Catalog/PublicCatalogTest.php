<?php

use App\Livewire\Catalog\GroupShow;
use App\Livewire\Catalog\Index;
use App\Livewire\Catalog\ProductShow;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\SubGroup;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

test('signed in users can open the catalog before products are imported', function () {
    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertSeeLivewire(Index::class)
        ->assertSee('The catalog is being prepared.')
        ->assertDontSee('D060');
});

test('catalog navigation follows actual ancestors and branches without descendant cards', function () {
    $root = Group::factory()->create(['name' => 'Actual root']);
    $leaf = Group::factory()->for($root, 'parent')->create(['name' => 'Actual leaf']);
    $product = Product::factory()->for($leaf)->create(['product_name' => 'Actual product']);
    $this->get(route('catalog.index'))->assertSee('Actual root')->assertDontSee('Actual leaf');
    $this->get(route('catalog.groups.show', $root))->assertSee('Actual leaf')->assertDontSee('Actual product');
    $this->get(route('catalog.groups.show', $leaf))->assertSeeInOrder(['Actual root', 'Actual leaf', $product->product_code]);
    $this->get(route('catalog.products.show', $product))->assertSee('Actual product')->assertSeeInOrder(['Actual root', 'Actual leaf']);
});

test('a leaf immediately paginates only its own products in code order', function () {
    $group = Group::factory()->create();
    for ($i = 11; $i >= 1; $i--) {
        Product::factory()->for($group)->create(['product_code' => sprintf('CODE-%02d', $i), 'product_name' => 'Public label '.$i]);
    }
    $foreign = Product::factory()->create(['product_name' => 'Foreign product']);
    Livewire::test(GroupShow::class, ['group' => $group])
        ->assertSeeInOrder(['CODE-01', 'CODE-02', 'CODE-10'])->assertDontSee('CODE-11')->assertDontSee($foreign->product_name)
        ->call('goToPage', 2)->assertSee('CODE-11')->assertDontSee('CODE-01');
});

test('public product facts are escaped and unassigned configuration remains honest', function () {
    $product = Product::factory()->create([
        'product_name' => 'Visitor name', 'product_code' => '000123',
        'description' => '<script>unsafe</script>', 'properties' => ['operating_pressure' => '25 bar'],
    ]);
    $this->get(route('catalog.products.show', $product))->assertOk()->assertSeeLivewire(ProductShow::class)
        ->assertSee('Visitor name')->assertSee('000123')->assertSee('25 bar')
        ->assertSee('<script>unsafe</script>')->assertDontSee('<script>unsafe</script>', false)
        ->assertSee('Configuration is not available for this product.')->assertDontSee('Configuration Code');
});

test('unknown catalog records return not found', function () {
    $this->get('/dashboard/catalog/groups/999999')->assertNotFound();
    $this->get('/dashboard/catalog/products/999999')->assertNotFound();
});

test('product cards preserve literal zero values and omit blank or malformed properties', function () {
    $group = Group::factory()->make(['name' => 'Actual leaf']);
    $product = Product::factory()->make(['id' => 1, 'product_code' => 'ZERO', 'properties' => [
        'Working_Pressure' => '0', 'Connection_Type' => '', 'Connection_Size' => null, 'Model' => ['invalid'],
    ]]);
    $propertyKeys = ['Working_Pressure', 'Connection_Type', 'Connection_Size', 'Model'];
    $html = view('components.catalog.product-card', compact('product', 'group', 'propertyKeys'))->render();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect(trim($xpath->evaluate('string(//*[@aria-label="Product properties"])')))->toBe('0');
    expect($html)->not->toContain('—')->not->toContain('invalid')->not->toContain('<dt>');
});

test('group cards lead with code then real Group hierarchy and all property values without labels', function () {
    $root = Group::factory()->create(['name' => 'Main family']);
    $leaf = Group::factory()->for($root, 'parent')->create(['name' => 'Product series', 'result_settings' => [
        'card_properties' => ['Working_Pressure', 'Connection_Type', 'Model', 'C'],
    ]]);
    $product = Product::factory()->for($leaf)->create([
        'product_code' => '000123', 'product_name' => 'Former card title',
        'properties' => ['C' => '<final dimension>', 'Model' => 'Model 1', 'Working_Pressure' => '25 bar', 'Connection_Type' => 'Flange'],
        'parts' => ['Part1' => 'Private part'], 'extra_data' => ['internal' => 'Private extra'],
    ]);
    $response = $this->get(route('catalog.groups.show', $leaf))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $card = $xpath->query('//article')->item(0);
    expect($xpath->evaluate('string(.//h3)', $card))->toBe('000123');
    expect($card->textContent)->toContain('Main family', 'Product series', '25 bar', 'Flange', 'Model 1', '<final dimension>')
        ->not->toContain('Former card title', 'Working_Pressure', 'Connection_Type', 'Private part', 'Private extra');
    $response->assertSee('<final dimension>')->assertDontSee('<final dimension>', false);
    expect($xpath->query('.//dt', $card)->length)->toBe(0);
    expect(trim($xpath->evaluate('string(.//*[@aria-label="Product properties"])', $card)))
        ->toBe('25 bar, Flange, Model 1, <final dimension>');
});

test('group cards display selected properties and show none when the default has no filters', function () {
    $group = Group::factory()->create(['result_settings' => ['card_properties' => ['Model', 'Working_Pressure']]]);
    $product = Product::factory()->for($group)->create(['product_code' => 'CARD-SETTINGS', 'properties' => [
        'Working_Pressure' => '25 bar', 'Connection_Type' => 'Flange', 'Model' => '<Model 1>',
    ]]);

    $this->get(route('catalog.groups.show', $group))
        ->assertSee('<Model 1>, 25 bar')->assertDontSee('<Model 1>', false)->assertDontSee('Flange');

    $group->update(['result_settings' => ['card_properties' => []]]);
    $this->get(route('catalog.groups.show', $group))
        ->assertSee($product->product_code)->assertDontSee('25 bar')->assertDontSee('<Model 1>');
});

test('default card properties follow the group filters and their order', function (array $settings) {
    $group = Group::factory()->create(['result_settings' => $settings]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'sort_order' => 2]);
    $firstFilter = GroupFilter::factory()->for($group)->create(['property_key' => 'Connection_Type', 'sort_order' => 1]);
    Product::factory()->for($group)->create(['properties' => [
        'Working_Pressure' => '25 bar', 'Connection_Type' => 'Flange', 'Model' => 'Card-only model',
    ]]);

    $this->get(route('catalog.groups.show', $group))
        ->assertSee('Flange, 25 bar')->assertDontSee('Card-only model');

    $firstFilter->update(['property_key' => 'Model']);
    $this->get(route('catalog.groups.show', $group))
        ->assertSee('Card-only model, 25 bar')->assertDontSee('Flange');
})->with(['unset' => [[]], 'empty selection' => [['card_properties' => []]]]);

test('subgroup buttons expose one active choice and a clear action returns to all products', function () {
    $group = Group::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Pressure']);
    Product::factory()->count(2)->for($group)->sequence(
        ['properties' => ['Working_Pressure' => '10']],
        ['properties' => ['Working_Pressure' => '16']],
    )->create();
    $low = SubGroup::factory()->for($group)->create(['label' => 'Low pressure', 'property_key' => 'Working_Pressure', 'allowed_values' => ['10']]);
    $high = SubGroup::factory()->for($group)->create(['label' => 'High pressure', 'property_key' => 'Working_Pressure', 'allowed_values' => ['16']]);
    $activeButtons = function (string $html): array {
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);

        return array_map(fn (DOMNode $label): string => trim($label->textContent), iterator_to_array($xpath->query('//fieldset[legend//span[text()="Pressure sub-group"]]//button[@aria-pressed="true"]/span[1]')));
    };

    $page = Livewire::test(GroupShow::class, ['group' => $group])->assertSee('2 products')->assertSee('Filters')->assertDontSee('Find a product')
        ->assertSeeHtml('aria-label="Clear subgroup"');
    expect($activeButtons($page->html()))->toBe([]);

    $page->call('selectSubGroup', $low->id)->assertSee('1 product');
    expect($activeButtons($page->html()))->toBe(['Low pressure']);

    $page->call('selectSubGroup', $high->id)->assertSet('discovery.subGroupId', $high->id);
    expect($activeButtons($page->html()))->toBe(['High pressure']);

    $page->call('selectSubGroup', null)->assertSee('2 products');
    expect($activeButtons($page->html()))->toBe([]);
});

test('a result threshold reveals cards at the boundary and hides them again when filters are cleared', function () {
    $group = Group::factory()->create(['result_settings' => ['max_results' => 2, 'default_page_size' => 1]]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Pressure']);
    Product::factory()->count(2)->for($group)->sequence(
        ['product_code' => 'MATCH-01'], ['product_code' => 'MATCH-02'],
    )->create(['properties' => ['Working_Pressure' => '25']]);
    Product::factory()->for($group)->create(['product_code' => 'OTHER-03', 'properties' => ['Working_Pressure' => '16']]);

    Livewire::test(GroupShow::class, ['group' => $group])
        ->assertSee('3 products')->assertSee('Use the filters to narrow your results to 2 products or fewer.')
        ->assertDontSee('MATCH-01')->assertDontSee('OTHER-03')->assertDontSee('Product pagination')
        ->call('selectFilter', 'Working_Pressure', '25')
        ->assertSee('2 products')->assertSee('MATCH-01')->assertDontSee('MATCH-02')
        ->assertDontSee('Use the filters to narrow your results')
        ->call('goToPage', 2)->assertSee('MATCH-02')->assertDontSee('MATCH-01')
        ->call('clearFilters')->assertSee('3 products')->assertDontSee('MATCH-01')->assertDontSee('MATCH-02');
});

test('all results bypass the display threshold while retaining pagination', function () {
    $group = Group::factory()->create(['result_settings' => ['max_results' => 'all', 'default_page_size' => 24]]);
    Product::factory()->count(25)->for($group)->sequence(fn ($sequence): array => [
        'product_code' => sprintf('ALL-%02d', $sequence->index + 1),
    ])->create();

    Livewire::test(GroupShow::class, ['group' => $group])
        ->assertSee('25 products')->assertSee('ALL-01')->assertSee('ALL-24')->assertDontSee('ALL-25')
        ->assertDontSee('Use the filters to narrow your results')
        ->call('goToPage', 2)->assertSee('ALL-25')->assertDontSee('ALL-01');
});

test('the running catalog does not load retired POC routes or schema', function () {
    $this->get('/admin/config-engine-demo')->assertNotFound();
    $this->get('/admin/product-configurations')->assertNotFound();
    $this->get('/admin/option-rules')->assertNotFound();

    foreach (['catalog_groups', 'product_profiles', 'config_profiles', 'option_rules', 'product_configurations', 'file_attachments'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});
