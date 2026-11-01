<?php

use App\Actions\SaveConfiguratorDefinition;
use App\ConfiguratorIntentType;
use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorInteraction;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogJourney;
use App\Services\ConfigurationCodeRestorer;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

test('code restoration follows token identity and code order independently of dependency and display order', function () {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['attributes'][0]['code_order'] = 2;
    $data['attributes'][2]['code_order'] = 0;
    $data['rules'] = [fixtureAdvanced('hide-b', [fixtureCondition('a-zero')])];
    $definition = app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);
    $restorer = app(ConfigurationCodeRestorer::class);
    $previous = new ConfiguratorEvaluationInput($definition, selections: ['new:A' => 'new:A1'], remembered: ['new:B' => 'new:B1'], intent: new ConfiguratorInteraction(ConfiguratorIntentType::SelectOption, 'new:A', 'new:A1'));
    $restored = $restorer->restore($previous, 'C1-A0');
    $fresh = $restorer->restore(new ConfiguratorEvaluationInput($definition), 'C1-A0');

    expect($restored->state())->toBe($fresh->state())->and($restored->configurationCode)->toBe('C1-A0')
        ->and($restored->remembered)->toBe([]);
    $revealed = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, selections: $restored->selections, remembered: $restored->remembered, intent: new ConfiguratorInteraction(ConfiguratorIntentType::SelectOption, 'new:A', 'new:A1')));
    expect($revealed->configurationCode)->toBe('C1-B0-A1');
    expect(fn () => $restorer->restore($previous, 'C1-B1-A0'))->toThrow(ValidationException::class);
    $contextIntent = new ConfiguratorEvaluationInput($definition, selections: $previous->selections, remembered: $previous->remembered, intent: new ConfiguratorInteraction(ConfiguratorIntentType::ChangeContext, dimension: 'territory', choice: 'old'));
    expect($restorer->restore($contextIntent, 'C1-A0')->state())->toBe($fresh->state());
});

test('code restoration preserves exact case and numeric token identities', function () {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    foreach ([1 => '00', 2 => '10', 3 => 'a0', 4 => 'A0'] as $id => $code) {
        $options[$id]['code'] = $code;
    }
    $input = new ConfiguratorEvaluationInput(app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options));
    $restorer = app(ConfigurationCodeRestorer::class);
    expect($restorer->restore($input, '10-A0-C1')->configurationCode)->toBe('10-A0-C1')
        ->and($restorer->restore($input, '00-a0-C0')->configurationCode)->toBe('00-a0-C0');
});

test('code restoration rejects malformed unknown duplicate incomplete and noncanonical codes', function (string $code) {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $input = new ConfiguratorEvaluationInput(app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options));
    expect(fn () => app(ConfigurationCodeRestorer::class)->restore($input, $code))->toThrow(ValidationException::class);
})->with(['', 'a0-B0-C0', 'A0-B0-Z0', 'A0-A0-C0', 'A0-A1-C0', 'A0-B0', 'B0-A0-C0', ' A0-B0-C0', 'A0-B0-C0 ', 'A0--B0-C0', 'A0-B0-C0-X0', "A0-B0-C0\n", str_repeat('x', 32769)]);

test('unavailable definitions cannot restore a configuration', function () {
    expect(fn () => app(ConfigurationCodeRestorer::class)->restore(new ConfiguratorEvaluationInput(null), 'A0'))->toThrow(ValidationException::class);
});

/** @return array{User, Product, Configurator} */
function savedCodeProductFixture(?Closure $modify = null): array
{
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    [$configurator, $data] = canonicalDefinitionFixture();
    if ($modify !== null) {
        $modify($data);
    }
    app(SaveConfiguratorDefinition::class)->handle($actor, $configurator, $data);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();

    return [$actor, $product, $configurator];
}

test('saved Product entry consumes a code once and ordinary refresh does not replay it', function () {
    [$actor, $product] = savedCodeProductFixture();
    $this->actingAs($actor);
    $component = Livewire::withQueryParams(['configuration' => 'A1-B1-C1'])->test(ProductConfigurator::class, ['productId' => $product->id]);
    $component->assertSee('A1-B1-C1')->assertHasNoErrors();
    $definition = app(ConfiguratorDefinitionLoader::class)->forDashboardProduct($product->id)->definition;
    $a = array_values($definition->attributes)[0];
    $component->call('chooseConfigurationOption', $a->id, array_values($a->options)[0]->id)->assertSee('A0-B1-C1')
        ->call('reevaluateConfiguration')->assertSee('A0-B1-C1');
});

test('Product code changes preserve the Alpine owner initializer while updating the server seed', function () {
    [$actor, $product] = savedCodeProductFixture();
    $this->actingAs($actor);
    $component = Livewire::test(ProductConfigurator::class, ['productId' => $product->id]);
    $projection = function () use ($component): array {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
        $xpath = new DOMXPath($document);
        $owner = $xpath->query('//section[contains(@class, "catalog-product-page")]')->item(0);

        return [
            'initializer' => $owner->getAttribute('x-data'),
            'seed' => $owner->getAttribute('data-configuration-initial-code'),
            'code' => $xpath->query('//*[@data-configuration-code]')->item(0)->textContent,
        ];
    };
    $initial = $projection();
    $definition = app(ConfiguratorDefinitionLoader::class)->forDashboardProduct($product->id)->definition;
    $attribute = array_values($definition->attributes)[0];

    $component->call('chooseConfigurationOption', $attribute->id, array_values($attribute->options)[1]->id)->assertHasNoErrors();
    $chosen = $projection();

    expect($initial['code'])->toBe('A0-B0-C0');
    expect($chosen['code'])->toBe('A1-B0-C0');
    expect($chosen['initializer'])->toBe($initial['initializer']);
    expect($chosen['seed'])->toBe($chosen['code']);
    $component->call('loadConfigurationCode', $initial['code'])->assertHasNoErrors();
    $restored = $projection();
    expect($restored['initializer'])->toBe($initial['initializer']);
    expect($restored['code'])->toBe($initial['code']);
    expect($restored['seed'])->toBe($initial['code']);
    expect($initial['seed'])->toBe($initial['code']);
});

test('failed Load preserves the whole runtime through render and successful Load clears hidden memory', function () {
    [$actor, $product] = savedCodeProductFixture(function (array &$data) {
        $data['rules'] = [fixtureAdvanced('hide-b', [fixtureCondition('a-zero')])];
    });
    $this->actingAs($actor);
    $component = Livewire::test(ProductConfigurator::class, ['productId' => $product->id]);
    $component->call('loadConfigurationCode', 'A1-B1-C0')->assertHasNoErrors();
    $definition = app(ConfiguratorDefinitionLoader::class)->forDashboardProduct($product->id)->definition;
    $a = array_values($definition->attributes)[0];
    $component->call('chooseConfigurationOption', $a->id, array_values($a->options)[0]->id);
    $before = $component->get('runtime');
    expect($before['remembered'])->not->toBe([]);
    $component->call('loadConfigurationCode', 'invalid')->assertHasErrors('configuration')->assertSet('runtime', $before);
    $component->call('loadConfigurationCode', 'A0-C0')->assertHasNoErrors()->assertSet('runtime.remembered', []);
    $component->call('chooseConfigurationOption', $a->id, array_values($a->options)[1]->id)->assertSee('A1-B0-C0');
    $component->call('restoreConfigurationHistory', null)->assertSet('runtime.remembered', [])->assertSee('A0-C0');
});

test('invalid initial code retains a normal baseline and malformed explicit input is rejected', function (mixed $invalid) {
    [$actor, $product] = savedCodeProductFixture();
    $this->actingAs($actor);
    Livewire::withQueryParams(['configuration' => $invalid])->test(ProductConfigurator::class, ['productId' => $product->id])
        ->assertSee('A0-B0-C0')->assertHasErrors('configuration');
    $component = Livewire::withQueryParams([])->test(ProductConfigurator::class, ['productId' => $product->id]);
    $before = $component->get('runtime');
    $component->call('loadConfigurationCode', $invalid)->assertHasErrors('configuration')->assertSet('runtime', $before);
})->with([['invalid'], [''], [['A1-B1-C1']], [str_repeat('x', 32769)]]);

test('restoration uses current saved assignment and fails truthfully when that definition becomes unavailable', function () {
    [$actor, $product, $configurator] = savedCodeProductFixture();
    $this->actingAs($actor);
    $component = Livewire::test(ProductConfigurator::class, ['productId' => $product->id]);
    $component->call('loadConfigurationCode', 'A1-B1-C1');
    $before = $component->get('runtime');
    $configurator->update(['is_active' => false]);
    $component->call('loadConfigurationCode', 'A0-B0-C0')->assertHasErrors('configuration')->assertSet('runtime', $before)
        ->assertDontSee('data-configuration-code', false);
});

test('every saved-only inherited command rejects Preview without changing its source or runtime', function (string $method, array $args) {
    [$actor, $product, $configurator] = savedCodeProductFixture();
    $this->actingAs($actor);
    $component = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $before = $component->get('runtime');
    $source = $component->get('previewSource');
    $view = $component->get('previewView');
    $instance = $component->instance();
    try {
        $instance->$method(...$args);
        $this->fail('Saved-only Preview action must reject.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
    expect($instance->runtime)->toBe($before)->and($instance->previewSource)->toBe($source);
    expect($instance->previewView)->toBe($view);
    $component->call($method, ...$args)->assertForbidden();
})->with([
    ['loadConfigurationCode', ['A1-B1-C1']], ['restoreConfigurationHistory', [null]],
    ['chooseConfigurationOption', ['1', '2']], ['reevaluateConfiguration', []],
]);

test('legacy Product forwarding retains exact code alongside route-bound return state and rejects nonscalar code', function () {
    [$actor, $product] = savedCodeProductFixture();
    $journey = app(CatalogJourney::class);
    $query = $journey->compatibilityQuery($actor, ['configuration' => 'A1-B1-C1', 'r' => 'invalid'], 'product', $product->id);
    expect($query)->toBe(['r' => 'invalid', 'configuration' => 'A1-B1-C1']);
    expect(fn () => $journey->compatibilityQuery($actor, ['configuration' => ['A0']], 'product', $product->id))->toThrow(ValidationException::class);
});

test('native and legacy Product links load code while retaining valid route-bound return context', function () {
    [$actor, $product] = savedCodeProductFixture();
    $this->actingAs($actor);
    $state = ['version' => 1, 'groupId' => (string) $product->group_id, 'productId' => (string) $product->id,
        'catalog' => ['query' => ['branchId' => null, 'search' => '', 'tagValueIds' => [], 'mode' => 'tree', 'page' => 1], 'anchor' => 'model-'.$product->group_id],
        'products' => ['criteria' => ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], 'anchor' => 'product-'.$product->id]];
    $r = app(CatalogJourney::class)->encode($state);
    $native = route('catalog.products.show', ['product' => $product->id, 'r' => $r, 'configuration' => 'A1-B1-C1']);
    $this->get('/catalog/products/'.$product->id.'?'.http_build_query(['r' => $r, 'configuration' => 'A1-B1-C1']))->assertRedirect($native);
    $this->get($native)->assertOk()->assertSee('data-configuration-code>A1-B1-C1', false)
        ->assertSee(__('configurator.copy.load_configuration_help'))->assertDontSee(__('catalog.journey.reset'));
});

test('restoration uses trusted current Product facts with dashboard context exclusions and effective noncontext Rules', function () {
    [$actor, $product] = savedCodeProductFixture(function (array &$data) {
        $pressure = ['id' => 'new:pressure', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Working_Pressure', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => '10', 'option_ids' => []];
        $territory = ['id' => 'new:territory', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'Equals', 'operand' => 'Europe', 'option_ids' => []];
        $data['context_schema']['territory'] = [['value' => 'Europe', 'label' => 'Europe']];
        $data['rules'] = [fixtureAdvanced('pressure', [$pressure], 'B', 'AllowOptions', ['new:B1']), fixtureAdvanced('territory', [$territory], 'C')];
        $data['rules'][1]['priority'] = 1;
    });
    $this->actingAs($actor);
    $component = Livewire::test(ProductConfigurator::class, ['productId' => $product->id]);
    $product->update(['profile_fields' => ['Working_Pressure' => '10']]);
    $before = $component->get('runtime');
    $component->call('loadConfigurationCode', 'A1-B0-C1')->assertHasErrors('configuration')->assertSet('runtime', $before);
    $component->call('loadConfigurationCode', 'A1-B1-C1')->assertHasNoErrors()->assertSee('data-configuration-code>A1-B1-C1', false);
});

test('changed saved assignment replaces old local identities and invalid history preserves current runtime', function () {
    [$actor, $product, $configurator] = savedCodeProductFixture();
    $this->actingAs($actor);
    $component = Livewire::test(ProductConfigurator::class, ['productId' => $product->id]);
    $before = $component->get('runtime');
    $copy = app(SaveConfiguratorDefinition::class)->duplicate($actor, $configurator, 'New saved assignment');
    $product->group->update(['configurator_id' => $copy->id]);
    $component->call('loadConfigurationCode', 'A1-B1-C1')->assertHasNoErrors()->assertSet('runtime.configurator_id', $copy->id);
    $current = $component->get('runtime');
    expect(array_intersect(array_keys($before['selections']), array_keys($current['selections'])))->toBe([]);
    $component->call('restoreConfigurationHistory', 'bad')->assertHasErrors('configuration')->assertSet('runtime', $current);
    $component->call('restoreConfigurationHistory', null)->assertHasNoErrors()->assertSee('data-configuration-code>A0-B0-C0', false);
});

test('initial HTTP links cannot trim code bytes or silently accept duplicate query values', function (string $query) {
    [$actor, $product] = savedCodeProductFixture();
    $this->actingAs($actor);
    $this->get(route('catalog.products.show', $product).'?'.$query)->assertOk()
        ->assertSee('data-configuration-code>A0-B0-C0', false)->assertSee(__('configurator.copy.invalid_configuration_code'));
})->with(['configuration=%20A1-B1-C1%20', 'configuration=A0-B0-C0&configuration=A1-B1-C1', 'configuration[]=A1-B1-C1']);

test('legacy HTTP redirects preserve untrimmed code for native validation', function () {
    [$actor, $product] = savedCodeProductFixture();
    $this->actingAs($actor)->get('/catalog/products/'.$product->id.'?configuration=%20A1-B1-C1%20')
        ->assertRedirect(route('catalog.products.show', ['product' => $product->id, 'configuration' => ' A1-B1-C1 ']));
});
