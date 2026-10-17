<?php

use App\Actions\SaveAdminAppearanceSettings;
use App\Actions\SaveConfiguratorDefinition;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Livewire\Catalog\ContextSelector;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAppearance;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
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
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('chooseProduct', $product->id)->assertSee(__('configurator.preview.saved_source'))->assertSee('A0-B1-C0');
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

test('saved edits reconcile Product choices silently and retain explanations in Preview', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture();
    $b = $draft['attributes'][1];
    $public = Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->call('selectOption', $b['id'], $b['options'][1]['id'])->assertSee('A0-B1-C0');
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('chooseProduct', $product->id)
        ->call('selectOption', $b['id'], $b['options'][1]['id']);
    $diagnostics = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('toggleTrace')
        ->call('selectOption', $b['id'], $b['options'][1]['id']);
    $draft['attributes'][1]['options'][1]['disabled_by_default'] = true;
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $draft);
    $public->call('refreshDefinition')->assertSee('A0-B0-C0')->assertDontSee('Choice for B was updated');
    $preview->call('refreshDefinition')->assertSee('A0-B0-C0')->assertSee('Choice for B was updated');
    $diagnostics->call('refreshDefinition')->assertSee('A0-B0-C0')->assertSee('Choice for B was updated');
    $product->group->update(['configurator_id' => null]);
    $public->call('refreshDefinition')->assertSee('Configuration is not available')->assertDontSee('A0-B0-C0');
});

test('Product configuration loading is hidden by default and enabled by the shared appearance setting', function () {
    [, $product] = publicConfigurationFixture();
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->assertDontSee(__('configurator.copy.updating_choices'));

    app(SaveAdminAppearanceSettings::class)->handle($this->actor, [...app(AdminAppearance::class)->defaults(), 'show_configurator_loading_indicator' => true]);

    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->assertSee(__('configurator.copy.updating_choices'));
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
        ->call('selectOption', $b['id'], $b['options'][0]['id'])->assertDontSee('not currently available')->assertSee('A0-B1-C0')
        ->assertSet('runtime.selections.'.$a['id'], $a['options'][0]['id']);
});

test('public identity and accepted runtime state are locked against direct client updates', function () {
    [$configurator, $product] = publicConfigurationFixture();
    expect(fn () => Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->set('productId', 999999))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->set('runtime.selections', []))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('dashboard and saved Preview ignore public context while preserving its stored definition', function () {
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

    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    foreach ([Livewire::test(ProductConfigurator::class, ['productId' => $product->id]), Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('chooseProduct', $product->id)] as $component) {
        $component->assertDontSeeLivewire(ContextSelector::class)->assertSee('A0-B0-C0')
            ->call('changeContext', 'territory', 'eu')->assertSet('runtime.context.territory', 'All')->assertSee('A0-B0-C0')
            ->call('changeContext', 'application', 'water')->assertSet('runtime.context.application', 'All')->assertSee('A0-B0-C0');
    }
    $public = app(ConfiguratorEngine::class)->evaluate(app(ConfiguratorDefinitionLoader::class)->forProduct($product->id, intent: ['kind' => 'ChangeContext', 'dimension' => 'territory', 'choice' => 'eu']));
    expect($public->configurationCode)->toBe('A0-B1-C0')
        ->and(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
});

test('dashboard drops complete context gated rules even with nested predicates and stale saved context', function () {
    [$configurator, $product] = publicConfigurationFixture(function (array &$draft): void {
        $draft['context_schema']['territory'] = [['value' => 'eu', 'label' => 'Europe']];
        $draft['attributes'][0]['label_override'] = 'Territory';
        $draft['rules'] = [fixtureAdvanced('public-context', [[
            'id' => 'new:any', 'operator' => 'Any', 'conditions' => [
                ['id' => 'new:territory', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'NotEquals', 'operand' => 'eu', 'option_ids' => []],
                fixtureCondition('a0', 'A', 'A0'),
            ],
        ]], 'B', 'DisableOptions', ['new:B0'])];
    });
    $loader = app(ConfiguratorDefinitionLoader::class);
    $before = $loader->draft($configurator->fresh());
    $input = $loader->forDashboardProduct($product->id, ['version' => 1, 'configurator_id' => $configurator->id, 'context' => ['territory' => 'eu', 'application' => 'All']]);
    $result = app(ConfiguratorEngine::class)->evaluate($input);
    expect($result->configurationCode)->toBe('A0-B0-C0')->and($result->context['territory'])->toBe('All')
        ->and($result->definition->contextSchema)->toBe(['territory' => [], 'application' => []])
        ->and($result->definition->rules)->toBe([])
        ->and($loader->draft($configurator->fresh()))->toBe($before);
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])->assertSee(__('configurator.labels.territory'))
        ->call('selectOption', $before['attributes'][0]['id'], $before['attributes'][0]['options'][1]['id'])->assertSee('A1-B0-C0');
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('chooseProduct', $product->id);
    expect($preview->instance()->form->getComponent('formState.context.territory'))->toBeNull();
});

test('Preview reauthorizes and rejects a Product outside its currently assigned Groups', function () {
    [$configurator, $product] = publicConfigurationFixture();
    $foreign = Product::factory()->create();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $preview->call('chooseProduct', $foreign->id)->assertHasErrors(['product']);
    $preview->call('chooseProduct', $product->id)->assertHasNoErrors()->assertSee('A0-B0-C0');
    $product->group->update(['configurator_id' => null]);
    $preview->call('refreshDefinition')->assertSee(__('configurator.copy.configuration_is_not_available_for_this_product'))->assertDontSee('A0-B0-C0')
        ->call('toggleTrace')->assertSee('no longer assigned');
    $this->actingAs(User::factory()->create());
    $preview->call('refreshDefinition')->assertForbidden();
});

test('Preview opens the selected dashboard Product and restores the ordered fallback when cleared', function () {
    [$configurator, $selected] = publicConfigurationFixture();
    $selected->group->update(['sort_order' => 10]);
    Group::factory()->create(['configurator_id' => $configurator->id, 'sort_order' => -1]);
    $firstGroup = Group::factory()->create(['configurator_id' => $configurator->id, 'sort_order' => 0]);
    Product::factory()->for($firstGroup)->create(['product_code' => 'ZZ-LAST']);
    $first = Product::factory()->for($firstGroup)->create(['product_code' => 'AA-FIRST']);
    Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id, 'sort_order' => 1]))->create(['product_code' => '00-LATER-GROUP']);

    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $preview->assertSee(__('configurator.copy.view_in_dashboard_catalog'))->assertSee(route('catalog.products.show', $first), false)->assertSet('productId', $first->id)
        ->assertSee('A0-B0-C0')
        ->call('chooseProduct', $selected->id)->assertSee(route('catalog.products.show', $selected), false)
        ->call('chooseProduct', null)->assertSee(route('catalog.products.show', $first), false)->assertSet('productId', null);
});

test('Preview dashboard links respect catalog visibility and current Group assignments', function () {
    [$configurator, $product] = publicConfigurationFixture();
    $hiddenParent = Group::factory()->create(['is_active' => false]);
    $hiddenGroup = Group::factory()->for($hiddenParent, 'parent')->create(['configurator_id' => $configurator->id, 'sort_order' => -2]);
    $hidden = Product::factory()->for($hiddenGroup)->create();
    $inactiveGroup = Group::factory()->create(['configurator_id' => $configurator->id, 'sort_order' => -1]);
    $inactive = Product::factory()->for($inactiveGroup)->create(['is_active' => false]);

    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $preview->assertSee(route('catalog.products.show', $product), false)
        ->assertDontSee(route('catalog.products.show', $hidden), false)->assertDontSee(route('catalog.products.show', $inactive), false)
        ->call('chooseProduct', $inactive->id)->assertDontSee(__('configurator.copy.view_in_dashboard_catalog'))->assertSee('The selected Product is not available for this Configurator in the dashboard catalog.')
        ->call('chooseProduct', $product->id);

    $product->group->update(['configurator_id' => null]);
    $preview->call('refreshDefinition')->assertDontSee(__('configurator.copy.view_in_dashboard_catalog'))->assertDontSee(route('catalog.products.show', $product), false);
});

test('Preview explains when no assigned dashboard Product is available', function () {
    $configurator = Configurator::factory()->create();
    Product::factory()->create();

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->assertDontSee(__('configurator.copy.view_in_dashboard_catalog'))->assertSee('No dashboard Product is available in the assigned Groups.')->assertSet('productId', null);
});

test('unassigned Product pages keep facts separate from the unavailable configuration', function () {
    $product = Product::factory()->create(['product_name' => 'Real imported name', 'product_code' => 'REAL-001']);
    $this->get(route('catalog.products.show', $product))->assertOk()->assertSee('Real imported name')->assertSee('REAL-001')->assertSee('Configuration is not available');
});

test('Preview initializes the default Product and reacts to choices and Product changes', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $preview->assertSet('productId', $product->id)->assertSee('A0-B0-C0');
    $a = $draft['attributes'][0];
    $preview->set('formState.choices.'.$a['id'], $a['options'][1]['id'])->assertSee('A1-B0-C0');
    $preview->call('chooseProduct', null)->assertSet('productId', null)->assertDontSee('A1-B0-C0');
    $preview->call('chooseProduct', $product->id)->assertSee('A0-B0-C0');
});

test('Preview Product search ignores casing while preserving codes and assigned Group scope', function () {
    [$configurator, $product] = publicConfigurationFixture();
    $mixed = Product::factory()->for($product->group)->create(['product_code' => 'pRoDuCt-MiXeD']);
    Product::factory()->create(['product_code' => 'PRODUCT-FOREIGN']);
    $connection = DB::connection();
    if ($connection->getDriverName() === 'sqlite') {
        $connection->statement('PRAGMA case_sensitive_like = ON');
    }

    try {
        $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
        foreach (['product-', 'PRODUCT-', 'pRoDuCt-'] as $search) {
            $preview->call('searchProducts', $search)->assertReturned([
                ['id' => (string) $product->id, 'label' => 'PRODUCT-ONLY'],
                ['id' => (string) $mixed->id, 'label' => 'pRoDuCt-MiXeD'],
            ]);
        }
        $preview->call('searchProducts', "' OR 1=1 --")->assertReturned([]);
        expect($mixed->fresh()->product_code)->toBe('pRoDuCt-MiXeD');
    } finally {
        if ($connection->getDriverName() === 'sqlite') {
            $connection->statement('PRAGMA case_sensitive_like = OFF');
        }
    }
});

test('Preview search is capped and reauthorizes each request', function () {
    [$configurator, $product] = publicConfigurationFixture();
    Product::factory()->count(51)->for($product->group)->sequence(fn ($sequence) => ['product_code' => sprintf('MATCH-%02d', $sequence->index)])->create();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);

    $preview->call('searchProducts', 'match-')->assertReturned(fn (array $results): bool => count($results) === 50 && $results[0]['label'] === 'MATCH-00' && $results[49]['label'] === 'MATCH-49');

    $this->actingAs(User::factory()->create());
    $preview->call('searchProducts', 'match-')->assertForbidden();
});

test('saved Preview marks mutations stale and refreshes with an optional engine trace without writes', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture(function (array &$draft): void {
        $rule = fixtureMapping();
        $rule['sets'][0]['disallowed_target_behavior'] = 'Hide';
        $draft['rules'] = [$rule];
    });
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('chooseProduct', $product->id);
    $preview->assertDontSeeText(__('configurator.copy.rule_diagnostics'))->assertDontSeeText(__('configurator.copy.engine_trace'));
    $baseline = $preview->get('runtime');
    $writes = [];
    DB::listen(function ($event) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $event->sql)) {
            $writes[] = $event->sql;
        }
    });
    $preview->dispatch('configurator-updated')->assertSet('definitionStale', true)->assertSee('Saved changes are available')
        ->call('refreshDefinition')->assertSet('definitionStale', false)->call('toggleTrace')->assertSee('Hide disallowed targets')
        ->assertSee(__('configurator.copy.rule_diagnostics'))->assertSee(__('configurator.copy.engine_trace'))
        ->call('toggleTrace')->assertDontSeeText(__('configurator.copy.rule_diagnostics'))->assertSee('A0-B1-C0');
    expect($preview->get('runtime'))->toBe($baseline)->and($writes)->toBe([]);
});

test('saved Preview keeps authored guidance in diagnostics and public rendering escapes it', function () {
    [$configurator, $product] = publicConfigurationFixture(function (array &$draft): void {
        $draft['attributes'][0]['help_text'] = 'Attribute guidance';
        $draft['attributes'][0]['options'][0]['display_value_override'] = 'Operating value';
        $draft['attributes'][0]['options'][0]['hint'] = '<b>Option guidance</b>';
    });
    $public = Livewire::test(ProductConfigurator::class, ['productId' => $product->id]);
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->assertDontSeeText('Operating value')->assertDontSeeText('Attribute guidance')->assertDontSeeText('<b>Option guidance</b>')
        ->call('toggleTrace');
    foreach ([$public, $preview] as $component) {
        $component->assertSee('Operating value')->assertSee('Attribute guidance')->assertSee('<b>Option guidance</b>')->assertDontSee('<b>Option guidance</b>', false);
    }
});

test('Preview keeps direct selection warnings visible while diagnostics are hidden', function () {
    [$configurator, , $draft] = publicConfigurationFixture(fn (array &$draft) => $draft['rules'] = [fixtureMapping()]);
    $target = $draft['attributes'][1];

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('selectOption', $target['id'], $target['options'][0]['id'])
        ->assertSee('That value is not currently available. Choices have been refreshed.')
        ->assertDontSeeText(__('configurator.copy.rule_diagnostics'));
});

test('Preview explains selection effects with semantic IDs and retains replacement warnings across diagnostics toggles', function () {
    [$configurator, $product, $draft] = publicConfigurationFixture(function (array &$draft): void {
        $rule = fixtureMapping('selection-map');
        $rule['label'] = 'Selection mapping';
        $rule['sets'][0]['disallowed_target_behavior'] = 'Hide';
        $rule['sets'][] = ['id' => 'new:second-set', 'label' => null, 'sort_order' => 1, 'source_option_ids' => ['new:A1'], 'target_option_ids' => ['new:B0']];
        $draft['rules'] = [$rule];
    });
    $a = $draft['attributes'][0];
    $b = $draft['attributes'][1];
    Option::findOrFail($b['options'][0]['option_id'])->value->update(['semantic_id' => 'b_zero']);
    Option::findOrFail($b['options'][1]['option_id'])->value->update(['semantic_id' => 'b_one']);

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->assertSee('Hidden values')->assertSee('b_zero (B0)')->assertSee('Selection mapping')
        ->assertDontSeeText(__('configurator.copy.rule_diagnostics'))
        ->call('selectOption', $a['id'], $a['options'][1]['id'])
        ->assertSee('Choice for B was updated')
        ->call('toggleTrace')->assertSee('Choice for B was updated')
        ->call('toggleTrace')->assertSee('Choice for B was updated')
        ->call('refreshDefinition')->assertDontSeeText('Choice for B was updated')
        ->call('chooseProduct', null)->assertDontSeeText('Selection mapping')->assertDontSeeText('b_zero (B0)');
});

test('Preview escapes selection driven explanatory text', function () {
    [$configurator] = publicConfigurationFixture(fn (array &$draft) => $draft['rules'] = [fixtureAdvanced('hint-rule', [fixtureCondition('a0')], 'B', 'SetHint', [], '<b>Selection explanation</b>')]);

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->assertSee('Selection note')->assertSee('<b>Selection explanation</b>')->assertDontSee('<b>Selection explanation</b>', false);
});
