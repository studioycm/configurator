<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(fn () => $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work'])));

function retainedProductSnapshot(string $html): string
{
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    foreach ($xpath->query('//*[@*[name()="wire:snapshot"]]') as $element) {
        $snapshot = $element->getAttribute('wire:snapshot');
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === 'catalog.product-configurator') {
            return $snapshot;
        }
    }

    throw new RuntimeException('The genuine nested ProductConfigurator snapshot was not rendered.');
}

function retainedProductCall(string $snapshot, string $method, array $parameters = []): array
{
    return ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => $method, 'params' => $parameters]]]]];
}

test('retained independent child requests cannot expose newly withdrawn product facts', function (string $withdrawal, string $method) {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $ancestor = Group::factory()->create();
    $group = Group::factory()->for($ancestor, 'parent')->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create(['description' => 'Previously visible facts']);
    $snapshot = retainedProductSnapshot($this->get(route('catalog.products.show', $product))->assertOk()->getContent());

    $product->update(['description' => 'WITHDRAWN-NEW-PRIVATE-FACTS', 'is_active' => $withdrawal !== 'product']);
    if ($withdrawal === 'ancestor') {
        $ancestor->update(['is_active' => false]);
    }
    $parameters = $method === 'selectOption'
        ? [$data['attributes'][0]['id'], $data['attributes'][0]['options'][1]['id']]
        : [];

    $this->postJson(Livewire::getUpdateUri(), retainedProductCall($snapshot, $method, $parameters), ['X-Livewire' => 'true'])
        ->assertNotFound()->assertDontSee('WITHDRAWN-NEW-PRIVATE-FACTS');
})->with(['inactive product' => 'product', 'hidden ancestor' => 'ancestor'])
    ->with(['refresh' => '$refresh', 're-evaluate' => 'refreshDefinition', 'selection' => 'selectOption']);

test('retained visible children still render current facts without an assigned or active configurator', function (string $configuration) {
    $configurator = $configuration === 'unassigned' ? null : Configurator::factory()->create(['is_active' => $configuration === 'active', 'disabled_group_behavior' => 'visible']);
    $group = Group::factory()->create(['configurator_id' => $configurator?->id]);
    $product = Product::factory()->for($group)->create();
    $snapshot = retainedProductSnapshot($this->get(route('catalog.products.show', $product))->assertOk()->getContent());
    $product->update(['description' => 'VISIBLE-CURRENT-FACTS']);

    $this->postJson(Livewire::getUpdateUri(), retainedProductCall($snapshot, 'refreshDefinition'), ['X-Livewire' => 'true'])
        ->assertOk()->assertSee('VISIBLE-CURRENT-FACTS');
})->with(['unassigned', 'active', 'disabled-visible']);

test('retained child returns not found when its configurator hides the group or its product is deleted', function (string $withdrawal) {
    $configurator = Configurator::factory()->create();
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create();
    $snapshot = retainedProductSnapshot($this->get(route('catalog.products.show', $product))->assertOk()->getContent());
    if ($withdrawal === 'deleted') {
        $product->delete();
    } else {
        $configurator->update(['is_active' => false, 'disabled_group_behavior' => 'hide']);
    }

    $this->postJson(Livewire::getUpdateUri(), retainedProductCall($snapshot, '$refresh'), ['X-Livewire' => 'true'])->assertNotFound();
})->with(['deleted', 'hidden-configurator']);

test('product identity remains locked and the catalog remains authenticated', function () {
    $product = Product::factory()->create();
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->set('productId', $product->id + 1);
})->throws(CannotUpdateLockedPropertyException::class);

test('guest product requests do not expose a component snapshot', function () {
    auth()->logout();
    $this->get(route('catalog.products.show', Product::factory()->create()))->assertRedirect(route('login'));
});

test('administrator preview keeps its separate inspection boundary for inactive products', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $group = Group::factory()->create(['configurator_id' => $configurator->id, 'is_active' => false]);
    Product::factory()->for($group)->create(['is_active' => false]);

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->assertOk()->call('refreshDefinition')->assertOk();
});
