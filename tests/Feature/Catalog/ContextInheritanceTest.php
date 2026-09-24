<?php

use App\Actions\SaveCatalogContextSettings;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorSettings;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
});

test('configurators inherit global choices and later changes without storing copies', function () {
    $configurator = Configurator::factory()->create();
    $loader = app(ConfiguratorDefinitionLoader::class);
    expect(array_column($loader->load($configurator->id)->contextSchema['territory'], 'value'))->toContain('USA', 'Europe');
    expect(array_column($loader->load($configurator->id)->contextSchema['application'], 'value'))->toContain('Industry', 'Water Supply');
    $choices = ['territory' => [['value' => 'eu', 'label' => 'Europe']], 'application' => [['value' => 'water', 'label' => 'Water']]];
    app(SaveCatalogContextSettings::class)->handle($this->actor, $choices);
    expect($loader->load($configurator->id)->contextSchema)->toBe($choices);
    expect($loader->draft($configurator)['context_schema'])->toBe(['territory' => [], 'application' => []]);
});

test('local additions and hidden global options affect only their configurator and survive duplication', function () {
    $configurator = Configurator::factory()->create();
    $other = Configurator::factory()->create();
    $settings = [
        'hidden_context_options' => ['territory' => ['USA'], 'application' => ['Industry']],
        'context_schema' => ['territory' => [['value' => 'local', 'label' => 'Local territory']], 'application' => []],
    ];
    app(SaveConfiguratorSettings::class)->handle($this->actor, $configurator, $settings);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $effective = $loader->load($configurator->id)->contextSchema;
    expect(array_column($effective['territory'], 'value'))->toContain('Europe', 'local')->not->toContain('USA');
    expect(array_column($effective['application'], 'value'))->not->toContain('Industry');
    expect(array_column($loader->load($other->id)->contextSchema['territory'], 'value'))->toContain('USA')->not->toContain('local');
    $copy = app(SaveConfiguratorDefinition::class)->duplicate($this->actor, $configurator, 'Copy with exceptions');
    expect($loader->load($copy->id)->contextSchema)->toBe($effective);
    expect($copy->context_schema)->toEqual($settings['context_schema']);
    expect($copy->hidden_context_options)->toBe($settings['hidden_context_options']);
});

test('existing local values remain available and can customize inherited labels without duplicates', function () {
    $configurator = Configurator::factory()->create(['context_schema' => [
        'territory' => [['value' => 'USA', 'label' => 'United States'], ['value' => 'existing', 'label' => 'Existing choice']],
        'application' => [],
    ]]);
    $choices = app(ConfiguratorDefinitionLoader::class)->load($configurator->id)->contextSchema['territory'];
    expect(array_count_values(array_column($choices, 'value'))['USA'])->toBe(1);
    expect(array_column($choices, 'label', 'value'))->toMatchArray(['USA' => 'United States', 'existing' => 'Existing choice']);
    app(SaveConfiguratorSettings::class)->handle($this->actor, $configurator, ['hidden_context_options' => ['territory' => ['USA'], 'application' => []]]);
    expect(array_column(app(ConfiguratorDefinitionLoader::class)->load($configurator->id)->contextSchema['territory'], 'value'))->not->toContain('USA');
});

test('global context choices participate in rules and cannot be removed or hidden while referenced', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureAdvanced('global-territory-restriction', [[
        'id' => 'new:global-territory', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null,
        'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'Equals', 'operand' => 'Europe', 'option_ids' => [],
    ]], 'B', 'DisableOptions', ['new:B0'])];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $draft);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    $loader = app(ConfiguratorDefinitionLoader::class);
    $result = app(ConfiguratorEngine::class)->evaluate($loader->forProduct($product->id, intent: ['kind' => 'ChangeContext', 'dimension' => 'territory', 'choice' => 'Europe']));
    expect($result->context['territory'])->toBe('Europe');
    expect($result->configurationCode)->toBe('A0-B1-C0');
    $before = CatalogContextSettings::current()->choices;
    $withoutEurope = $before;
    $withoutEurope['territory'] = array_values(array_filter($before['territory'], fn (array $choice): bool => $choice['value'] !== 'Europe'));
    expect(fn () => app(SaveCatalogContextSettings::class)->handle($this->actor, $withoutEurope))->toThrow(ValidationException::class);
    expect(CatalogContextSettings::current()->choices)->toBe($before);
    expect(fn () => app(SaveConfiguratorSettings::class)->handle($this->actor, $configurator, [
        'hidden_context_options' => ['territory' => ['Europe'], 'application' => []],
    ]))->toThrow(ValidationException::class);
    expect($loader->load($configurator->id)->contextSchema['territory'])->toEqual($before['territory']);
});

test('an unused global choice can be removed and stale runtime context resets to All', function () {
    $configurator = Configurator::factory()->create();
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    app(SaveCatalogContextSettings::class)->handle($this->actor, ['territory' => [], 'application' => []]);
    $result = app(ConfiguratorEngine::class)->evaluate(app(ConfiguratorDefinitionLoader::class)->forProduct($product->id, [
        'version' => 1, 'configurator_id' => $configurator->id, 'context' => ['territory' => 'Europe', 'application' => 'Industry'],
    ]));
    expect($result->context)->toBe(['territory' => 'All', 'application' => 'All']);
});

test('global settings reject unauthorized writes and reserved or duplicate values', function () {
    $action = app(SaveCatalogContextSettings::class);
    $before = CatalogContextSettings::current()->choices;
    expect(fn () => $action->handle(User::factory()->create(), ['territory' => [], 'application' => []]))->toThrow(AuthorizationException::class);
    foreach ([['value' => 'All', 'label' => 'Invalid'], ['value' => 'USA', 'label' => 'Duplicate']] as $invalid) {
        expect(fn () => $action->handle($this->actor, ['territory' => [['value' => 'USA', 'label' => 'USA'], $invalid], 'application' => []]))->toThrow(ValidationException::class);
    }
    expect(CatalogContextSettings::current()->choices)->toBe($before);
});
