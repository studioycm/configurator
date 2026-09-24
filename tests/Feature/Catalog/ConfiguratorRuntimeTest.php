<?php

use App\Actions\SaveCanonicalOption;
use App\Actions\SaveConfiguratorDefinition;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
});

test('runtime loads trusted Product facts and current assignment and discards foreign prior state', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $condition = ['id' => 'new:pressure', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Working_Pressure', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => '10', 'option_ids' => []];
    $data['rules'] = [fixtureAdvanced('pressure', [$condition], 'B', 'AllowOptions', ['new:B1'])];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10']]);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $engine = app(ConfiguratorEngine::class);
    $result = $engine->evaluate($loader->forProduct($product->id, ['properties' => ['Working_Pressure' => 'forged'], 'configuration_code' => 'forged']));
    expect($result->configurationCode)->toBe('A0-B1-C0')->and($result->state())->not->toHaveKeys(['properties', 'configuration_code']);
    $copy = app(SaveConfiguratorDefinition::class)->duplicate($this->actor, $configurator, 'New assignment');
    $group->update(['configurator_id' => $copy->id]);
    $changed = $engine->evaluate($loader->forProduct($product->id, $result->state()));
    expect($changed->configuratorId)->toBe($copy->id)->and(array_intersect(array_keys($changed->selections), array_keys($result->selections)))->toBe([])->and(array_column($changed->diagnostics, 'code'))->toContain('definition_changed');
    $group->update(['configurator_id' => null]);
    $unassigned = $engine->evaluate($loader->forProduct($product->id, $changed->state()));
    expect($unassigned->configurationCode)->toBeNull()->and($unassigned->selections)->toBe([]);
});

test('each request observes canonical code and availability edits before accepting stale input', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create();
    $loader = app(ConfiguratorDefinitionLoader::class);
    $engine = app(ConfiguratorEngine::class);
    $first = $engine->evaluate($loader->forProduct($product->id));
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $local = $attribute->options()->orderBy('display_order')->first();
    app(SaveCanonicalOption::class)->handle($this->actor, $local->option, $local->option->attribute_id, $local->option->value_id, '00');
    expect($engine->evaluate($loader->forProduct($product->id, $first->state()))->configurationCode)->toBe('00-B0-C0');
    $draft = $loader->draft($configurator->fresh());
    $draft['attributes'][0]['options'][0]['disabled_by_default'] = true;
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $draft);
    $updated = $engine->evaluate($loader->forProduct($product->id, $first->state(), ['kind' => 'SelectOption', 'attribute_id' => (string) $attribute->id, 'option_id' => (string) $local->id]));
    expect($updated->configurationCode)->toBe('A1-B0-C0')->and(array_column($updated->diagnostics, 'code'))->toContain('selection_rejected');
});

test('an invalid current definition withholds code without breaking the Product page boundary', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    DB::table('configurator_attributes')->where('configurator_id', $configurator->id)->update(['code_order' => 0]);
    $result = app(ConfiguratorEngine::class)->evaluate(app(ConfiguratorDefinitionLoader::class)->forProduct($product->id));
    expect($result->isComplete)->toBeFalse()->and($result->configurationCode)->toBeNull()->and(array_column($result->diagnostics, 'code'))->toContain('invalid_definition');
});
