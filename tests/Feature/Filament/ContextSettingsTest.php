<?php

use App\Actions\SaveCatalogContextSettings;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorSettings;
use App\Filament\Pages\ContextSettings;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Livewire\Catalog\ConfiguratorOverview;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleDraft;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('administrators manage global context choices on the settings page', function () {
    $configurator = Configurator::factory()->create();
    $this->get(ContextSettings::getUrl())->assertOk()->assertSee('Territory &amp; application', false);
    $choices = ['territory' => [['value' => 'eu', 'label' => 'European Union']], 'application' => [['value' => 'water', 'label' => 'Drinking water']]];
    Livewire::test(ContextSettings::class)->fillForm(['context_schema' => $choices])->call('save')->assertHasNoFormErrors()->assertNotified('Global choices saved');
    expect(CatalogContextSettings::current()->choices)->toEqual($choices);
    expect(app(ConfiguratorDefinitionLoader::class)->load($configurator->id)->contextSchema)->toBe($choices);
});

test('configurator overview saves only local context additions and exclusions', function () {
    $configurator = Configurator::factory()->create();
    Livewire::test(ConfiguratorOverview::class, ['configuratorId' => $configurator->id])
        ->assertSee('All global options are available by default')
        ->fillForm([
            'name' => $configurator->name,
            'context_schema' => ['territory' => [['value' => 'local', 'label' => 'Local region']], 'application' => []],
            'hidden_context_options' => ['territory' => ['USA'], 'application' => ['Industry']],
        ])->call('saveOverview')->assertHasNoFormErrors();
    $effective = app(ConfiguratorDefinitionLoader::class)->load($configurator->id)->contextSchema;
    expect(array_column($effective['territory'], 'value'))->toContain('local', 'Europe')->not->toContain('USA');
    expect(array_column($effective['application'], 'value'))->not->toContain('Industry');
    expect(array_column(CatalogContextSettings::current()->choices['territory'], 'value'))->toContain('USA')->not->toContain('local');
});

test('global validation preserves the staged settings and stored choices', function () {
    $before = CatalogContextSettings::current()->choices;
    Livewire::test(ContextSettings::class)->fillForm(['context_schema' => [
        'territory' => [['value' => 'All', 'label' => 'Reserved choice']], 'application' => [],
    ]])->call('save')->assertHasFormErrors()->assertSee('All is reserved for unrestricted context.');
    expect(CatalogContextSettings::current()->choices)->toBe($before);
});

test('ordinary signed-in users cannot open or submit global settings', function () {
    $component = Livewire::test(ContextSettings::class);
    $before = CatalogContextSettings::current()->choices;
    $this->actingAs(User::factory()->create());
    $this->get(ContextSettings::getUrl())->assertForbidden();
    $component->call('save')->assertForbidden();
    expect(CatalogContextSettings::current()->choices)->toBe($before);
});

test('removed global choices can still be unhidden without blocking other overview edits', function () {
    $configurator = Configurator::factory()->create();
    app(SaveConfiguratorSettings::class)->handle(auth()->user(), $configurator, ['hidden_context_options' => ['territory' => ['USA'], 'application' => []]]);
    app(SaveCatalogContextSettings::class)->handle(auth()->user(), ['territory' => [], 'application' => []]);
    Livewire::test(ConfiguratorOverview::class, ['configuratorId' => $configurator->id])
        ->assertSee('USA (no longer global)')->fillForm(['name' => 'Renamed after removal'])->call('saveOverview')->assertHasNoFormErrors()
        ->fillForm(['hidden_context_options' => ['territory' => [], 'application' => []]])->call('saveOverview')->assertHasNoFormErrors();
    expect($configurator->fresh()->name)->toBe('Renamed after removal');
    expect($configurator->fresh()->hidden_context_options['territory'])->toBe([]);
});

test('numeric stable values can be hidden through the configurator form', function () {
    app(SaveCatalogContextSettings::class)->handle(auth()->user(), ['territory' => [['value' => '123', 'label' => 'Numeric region']], 'application' => []]);
    $configurator = Configurator::factory()->create();
    Livewire::test(ConfiguratorOverview::class, ['configuratorId' => $configurator->id])
        ->fillForm(['hidden_context_options' => ['territory' => ['123'], 'application' => []]])->call('saveOverview')->assertHasNoFormErrors();
    expect($configurator->fresh()->hidden_context_options['territory'])->toBe(['123']);
    expect(app(ConfiguratorDefinitionLoader::class)->load($configurator->id)->contextSchema['territory'])->toBe([]);
});

test('rule editor offers inherited context choices and preserves their string identities', function (string $value, string $operator) {
    require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';
    app(SaveCatalogContextSettings::class)->handle(auth()->user(), ['territory' => [['value' => $value, 'label' => 'Global region']], 'application' => []]);
    [$configurator, $data] = canonicalDefinitionFixture();
    $operand = $operator === 'In' ? [$value] : $value;
    $data['rules'] = [fixtureAdvanced('context-rule', [[
        'id' => 'new:context', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null,
        'property_key' => null, 'context_dimension' => 'territory', 'operator' => $operator, 'operand' => $operand, 'option_ids' => [],
    ]], 'B', 'DisableOptions', ['new:B0'])];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $ruleDraft = app(ConfiguratorRuleDraft::class)->fromRule($saved['rules'][0]);
    $ruleDraft['label'] = 'Edited context rule';
    Livewire::test(RulesRelationManager::class, [
        'ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class,
    ])->callTableAction('edit', $configurator->rules()->sole(), data: $ruleDraft)->assertHasNoTableActionErrors();
    expect($configurator->rules()->sole()->conditions()->sole()->operand)->toBe($operand);
    expect($configurator->rules()->sole()->label)->toBe('Edited context rule');
})->with([
    'named global choice' => ['Europe', 'Equals'],
    'numeric global choice' => ['123', 'Equals'],
    'numeric global choices list' => ['123', 'In'],
]);
