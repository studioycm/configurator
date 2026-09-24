<?php

use App\Livewire\Catalog\Index;

test('visitors can open the public catalog before products are imported', function () {
    $this->get('/catalog')
        ->assertOk()
        ->assertSeeLivewire(Index::class)
        ->assertSee('The catalog is being prepared.')
        ->assertDontSee('D060');
});

use App\Livewire\Catalog\GroupShow;
use App\Livewire\Catalog\ProductShow;
use App\Models\Group;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('catalog navigation follows actual ancestors and branches without descendant cards', function () {
    $root = Group::factory()->create(['name' => 'Actual root']);
    $leaf = Group::factory()->for($root, 'parent')->create(['name' => 'Actual leaf']);
    $product = Product::factory()->for($leaf)->create(['product_name' => 'Actual product']);
    $this->get(route('catalog.index'))->assertSee('Actual root')->assertDontSee('Actual leaf');
    $this->get(route('catalog.groups.show', $root))->assertSee('Actual leaf')->assertDontSee('Actual product');
    $this->get(route('catalog.groups.show', $leaf))->assertSeeInOrder(['Actual root', 'Actual leaf', 'Actual product']);
    $this->get(route('catalog.products.show', $product))->assertSeeInOrder(['Actual root', 'Actual leaf', 'Actual product']);
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
    $this->get('/catalog/groups/999999')->assertNotFound();
    $this->get('/catalog/products/999999')->assertNotFound();
});

test('product cards preserve literal zero values and distinguish blanks', function () {
    $product = (object) ['id' => 1, 'product_code' => 'ZERO', 'product_name' => '0', 'pressure' => '0', 'connection' => '0'];
    $html = view('components.catalog.product-card', compact('product'))->render();
    expect($html)->toContain('<dd>0</dd>')->not->toContain('—')->not->toContain('Unnamed product');
    $product->pressure = '';
    $product->connection = null;
    expect(view('components.catalog.product-card', compact('product'))->render())->toContain('<dd>—</dd>');
});

test('the running catalog does not load retired POC routes or schema', function () {
    $this->get('/admin/config-engine-demo')->assertNotFound();
    $this->get('/admin/product-configurations')->assertNotFound();
    $this->get('/admin/option-rules')->assertNotFound();

    foreach (['catalog_groups', 'product_profiles', 'config_profiles', 'option_rules', 'product_configurations', 'file_attachments'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});
