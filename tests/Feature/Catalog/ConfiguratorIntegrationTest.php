<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Livewire\Catalog\ContextSelector;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

function publicConfigurationFixture(?Closure $change = null): array
{
    [$configurator, $draft] = canonicalDefinitionFixture();
    if ($change !== null) {
        $change($draft);
    }
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create(['product_code' => 'PRODUCT-ONLY']);

    return [$configurator, $product, app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())];
}

test('public and saved Preview share the same settled selections code and context without database writes', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture(fn (array &$draft) => $draft['rules'] = [fixtureMapping()]);
    $public = Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->assertSee('A0-B1-C0');
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('chooseProduct', $product->id)->assertSee('Saved definition')->assertSee('A0-B1-C0');
    $writes = [];
    DB::listen(function ($event) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $event->sql)) {
            $writes[] = $event->sql;
        }
    });
    $a = $draft['attributes'][0];
    $b = $draft['attributes'][1];
    foreach ([$public, $preview] as $component) {
        $component->call('selectOption', $a['id'], $a['options'][1]['id'])->call('selectOption', $b['id'], $b['options'][0]['id'])->assertSee('A1-B0-C0');
    }
    expect($preview->get('runtime'))->toBe($public->get('runtime'))->and($writes)->toBe([]);
});

test('current saved edits reconcile choices with a notice and unassignment removes configuration immediately', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture();
    $b = $draft['attributes'][1];
    $public = Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->call('selectOption', $b['id'], $b['options'][1]['id'])->assertSee('A0-B1-C0');
    $draft['attributes'][1]['options'][1]['disabled_by_default'] = true;
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $draft);
    $public->call('refreshDefinition')->assertSee('A0-B0-C0')->assertSee('Choice for B was updated');
    $product->group->update(['configurator_id' => null]);
    $public->call('refreshDefinition')->assertSee('Configuration is not available')->assertDontSee('A0-B0-C0');
});

test('hidden attributes restore remembered choices through real component interactions', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture(fn (array &$draft) => $draft['rules'] = [fixtureAdvanced('hide', [fixtureCondition('a1', 'A', 'A1')])]);
    $a = $draft['attributes'][0];
    $b = $draft['attributes'][1];
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])
        ->call('selectOption', $b['id'], $b['options'][1]['id'])->assertSee('A0-B1-C0')
        ->call('selectOption', $a['id'], $a['options'][1]['id'])->assertSee('A1-C0')->assertSet('runtime.remembered.'.$b['id'], $b['options'][1]['id'])
        ->call('selectOption', $a['id'], $a['options'][0]['id'])->assertSee('A0-B1-C0');
});

test('forged unavailable choices cannot clear the upstream mapping selection', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture(fn (array &$draft) => $draft['rules'] = [fixtureMapping()]);
    $a = $draft['attributes'][0];
    $b = $draft['attributes'][1];
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])
        ->call('selectOption', $b['id'], $b['options'][0]['id'])->assertSee('not currently available')->assertSee('A0-B1-C0')
        ->assertSet('runtime.selections.'.$a['id'], $a['options'][0]['id']);
});

test('public identity and accepted runtime state are locked against direct client updates', function () {
    [$configurator, $product] = publicConfigurationFixture();
    expect(fn () => Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->set('productId', 999999))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->set('runtime.selections', []))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('context choices update configuration and reject choices outside the current schema', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture(function (array &$draft): void {
        $draft['context_schema'] = [
            'territory' => [['value' => 'eu', 'label' => 'European Union']],
            'application' => [['value' => 'water', 'label' => 'Drinking water']],
        ];
        $draft['rules'] = [fixtureAdvanced('europe', [[
            'id' => 'new:europe', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null,
            'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'Equals', 'operand' => 'eu', 'option_ids' => [],
        ]], 'B', 'DisableOptions', ['new:B0'])];
    });

    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])
        ->assertSeeLivewire(ContextSelector::class)->assertSee('A0-B0-C0')
        ->call('changeContext', 'territory', 'eu')->assertSet('runtime.context.territory', 'eu')->assertSee('A0-B1-C0')
        ->call('changeContext', 'application', 'water')->assertSet('runtime.context.application', 'water')->assertSee('A0-B1-C0')
        ->call('changeContext', 'territory', 'unavailable')->assertSet('runtime.context.territory', 'eu')
        ->assertSee('That context choice is not available.')->assertSee('A0-B1-C0')
        ->call('changeContext', 'territory', 'All')->assertSet('runtime.context.territory', 'All')
        ->call('selectOption', $draft['attributes'][1]['id'], $draft['attributes'][1]['options'][0]['id'])->assertSee('A0-B0-C0');
});

test('Preview reauthorizes and rejects a Product outside its currently assigned Groups', function () {
    [$configurator, $product] = publicConfigurationFixture();
    $foreign = Product::factory()->create();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $preview->call('chooseProduct', $foreign->id)->assertHasErrors(['product']);
    $preview->call('chooseProduct', $product->id)->assertSee('A0-B0-C0');
    $product->group->update(['configurator_id' => null]);
    $preview->call('refreshDefinition')->assertSee('no longer assigned')->assertDontSee('A0-B0-C0');
    $this->actingAs(User::factory()->create());
    $preview->call('refreshDefinition')->assertForbidden();
});

test('unassigned Product pages keep facts separate from the unavailable configuration', function () {
    $product = Product::factory()->create(['product_name' => 'Real imported name', 'product_code' => 'REAL-001']);
    $this->get(route('catalog.products.show', $product))->assertOk()->assertSee('Real imported name')->assertSee('REAL-001')->assertSee('Configuration is not available');
});

test('Preview initializes and reacts to the actual form paths used by the browser', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    expect($preview->get('formState'))->toHaveKey('product');
    $preview->set('formState.product', $product->id)->assertSee('A0-B0-C0');
    $a = $draft['attributes'][0];
    $preview->set('formState.choices.'.$a['id'], $a['options'][1]['id'])->assertSee('A1-B0-C0');
    $preview->set('formState.product', null)->assertSet('productId', null)->assertDontSee('A1-B0-C0');
    expect($preview->get('formState'))->toHaveKey('product');
});

test('saved Preview shows escaped current option presentation alongside public rendering', function () {
    [$configurator, $product] = publicConfigurationFixture(function (array &$draft): void {
        $draft['attributes'][0]['help_text'] = 'Attribute guidance';
        $draft['attributes'][0]['options'][0]['display_value_override'] = 'Operating value';
        $draft['attributes'][0]['options'][0]['hint'] = '<b>Option guidance</b>';
    });
    foreach ([Livewire::test(ProductConfigurator::class, ['productId' => $product->id]), Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('chooseProduct', $product->id)] as $component) {
        $component->assertSee('Operating value')->assertSee('Attribute guidance')->assertSee('<b>Option guidance</b>')->assertDontSee('<b>Option guidance</b>', false);
    }
});
