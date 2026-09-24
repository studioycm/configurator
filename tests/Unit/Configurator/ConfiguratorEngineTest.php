<?php

use App\ConfiguratorIntentType;
use App\DTO\ConfiguratorDefinition;
use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorInteraction;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorEngine;
use Tests\TestCase;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';
uses(TestCase::class);

function engineDefinition(?Closure $alter = null): ConfiguratorDefinition
{
    [$data, $attributes, $options] = compiledDefinitionFixture();
    if ($alter !== null) {
        $alter($data);
    }

    return app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);
}

/** @return array<string, mixed> */
function propertyCondition(string $operator, string|array $operand): array
{
    return ['id' => 'new:property', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Working_Pressure', 'context_dimension' => null, 'operator' => $operator, 'operand' => $operand, 'option_ids' => []];
}

test('one DAG evaluation settles A to B to C and preserves the latest accepted upstream choice', function () {
    $definition = engineDefinition(function (array &$d) {
        $first = fixtureMapping('ab');
        $first['sets'][] = ['id' => 'new:ab2', 'label' => null, 'sort_order' => 1, 'source_option_ids' => ['new:A1'], 'target_option_ids' => ['new:B0']];
        $second = fixtureMapping('bc', 'B', 'C');
        $second['sets'][0]['source_option_ids'] = ['new:B1'];
        $second['sets'][] = ['id' => 'new:bc2', 'label' => null, 'sort_order' => 1, 'source_option_ids' => ['new:B0'], 'target_option_ids' => ['new:C0']];
        $d['rules'] = [$first, $second];
        $d['attributes'][0]['display_order'] = 2;
        $d['attributes'][2]['display_order'] = 0;
    });
    $engine = app(ConfiguratorEngine::class);
    $first = $engine->evaluate(new ConfiguratorEvaluationInput($definition));
    expect($first->selections)->toBe(['new:A' => 'new:A0', 'new:B' => 'new:B1', 'new:C' => 'new:C1'])->and($first->configurationCode)->toBe('A0-B1-C1');
    $next = $engine->evaluate(new ConfiguratorEvaluationInput($definition, selections: $first->selections, intent: new ConfiguratorInteraction(ConfiguratorIntentType::SelectOption, 'new:A', 'new:A1')));
    expect($next->selections)->toBe(['new:A' => 'new:A1', 'new:B' => 'new:B0', 'new:C' => 'new:C0'])->and($next->configurationCode)->toBe('A1-B0-C0');
});

test('restrictions intersect across priorities and an empty target never clears the latest upstream choice', function () {
    $definition = engineDefinition(function (array &$d) {
        $one = fixtureAdvanced('one', [], 'B', 'AllowOptions', ['new:B0']);
        $one['priority'] = 100;
        $two = fixtureAdvanced('two', [fixtureCondition('a1', 'A', 'A1')], 'B', 'AllowOptions', ['new:B1']);
        $negative = fixtureCondition('missing', 'B', 'B0');
        $negative['operator'] = 'NotEquals';
        $d['rules'] = [$one, $two, fixtureAdvanced('outgoing', [$negative], 'C')];
    });
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, intent: new ConfiguratorInteraction(ConfiguratorIntentType::SelectOption, 'new:A', 'new:A1')));
    expect($result->selections['new:A'])->toBe('new:A1')->and($result->attributes['new:B']['legal'])->toBe([])->and($result->attributes['new:C']['applicable'])->toBeTrue()->and($result->isComplete)->toBeFalse()->and($result->configurationCode)->toBeNull();
    expect(array_column($result->diagnostics, 'code'))->toContain('no_legal_options');
});

test('hidden attributes remember valid choices restore them and contribute no outgoing selections or code', function () {
    $definition = engineDefinition(function (array &$d) {
        $d['attributes'][1]['default_configurator_option_id'] = 'new:B1';
        $d['rules'] = [fixtureAdvanced('hideB', [fixtureCondition('a', 'A', 'A1')]), fixtureAdvanced('hideC', [fixtureCondition('b', 'B', 'B1')], 'C')];
    });
    $engine = app(ConfiguratorEngine::class);
    $initial = $engine->evaluate(new ConfiguratorEvaluationInput($definition));
    expect($initial->configurationCode)->toBe('A0-B1');
    $hidden = $engine->evaluate(new ConfiguratorEvaluationInput($definition, selections: $initial->selections, intent: new ConfiguratorInteraction(ConfiguratorIntentType::SelectOption, 'new:A', 'new:A1')));
    expect($hidden->selections)->toBe(['new:A' => 'new:A1', 'new:C' => 'new:C0'])->and($hidden->remembered['new:B'])->toBe('new:B1')->and($hidden->configurationCode)->toBe('A1-C0');
    $restored = $engine->evaluate(new ConfiguratorEvaluationInput($definition, selections: $hidden->selections, remembered: $hidden->remembered, intent: new ConfiguratorInteraction(ConfiguratorIntentType::SelectOption, 'new:A', 'new:A0')));
    expect($restored->selections['new:B'])->toBe('new:B1')->and($restored->attributes['new:C']['applicable'])->toBeFalse()->and($restored->configurationCode)->toBe('A0-B1');
});

test('fallback uses stored default then local option order without changing code order', function () {
    $definition = engineDefinition(function (array &$d) {
        $d['attributes'][0]['options'][0]['hidden_by_default'] = true;
        $d['attributes'][1]['default_configurator_option_id'] = 'new:B1';
        $d['attributes'][1]['options'][1]['disabled_by_default'] = true;
        $d['attributes'][0]['code_order'] = 2;
        $d['attributes'][2]['code_order'] = 0;
    });
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, selections: ['forged' => 'new:A0', 'new:A' => ['bad']]));
    expect($result->selections)->toBe(['new:A' => 'new:A1', 'new:B' => 'new:B0', 'new:C' => 'new:C0'])->and($result->configurationCode)->toBe('C0-B0-A1')->and($result->attributes['new:A']['hidden'])->toBe(['new:A0'])->and($result->attributes['new:B']['disabled'])->toContain('new:B1');
});

test('forged disabled hidden and foreign selection commands are rejected against refreshed legal state', function (string $attribute, string $option) {
    $definition = engineDefinition(function (array &$d) {
        $d['attributes'][0]['options'][1]['disabled_by_default'] = true;
        $d['attributes'][1]['options'][1]['hidden_by_default'] = true;
    });
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, intent: new ConfiguratorInteraction(ConfiguratorIntentType::SelectOption, $attribute, $option)));
    expect($result->configurationCode)->toBe('A0-B0-C0')->and(array_column($result->diagnostics, 'code'))->toContain('selection_rejected');
})->with([['new:A', 'new:A1'], ['new:B', 'new:B1'], ['new:A', 'new:C0'], ['other', 'new:A0']]);

test('missing null and empty Product values have distinct strict predicate semantics', function (array $properties, string $operator, string|array $operand, bool $matches) {
    $definition = engineDefinition(fn (array &$d) => $d['rules'] = [fixtureAdvanced('property', [propertyCondition($operator, $operand)], 'B', 'ExcludeOptions', ['new:B0'])]);
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, properties: $properties));
    expect($result->selections['new:B'])->toBe($matches ? 'new:B1' : 'new:B0');
})->with([
    [[], 'NotEquals', 'x', false], [['Working_Pressure' => null], 'NotIn', ['x'], false],
    [['Working_Pressure' => ''], 'Equals', '', true], [['Working_Pressure' => '0'], 'Equals', '0', true],
    [['Working_Pressure' => 0], 'Equals', '0', false], [['Working_Pressure' => 'PN 16'], 'Contains', 'PN', true],
    [['Working_Pressure' => 'PN 16'], 'Contains', 'pn', false], [['Working_Pressure' => 'a.b'], 'Contains', '.', true],
]);

test('All context never satisfies a negative predicate while another Any branch may activate it', function () {
    $definition = engineDefinition(function (array &$d) {
        $d['context_schema']['territory'] = [['value' => 'west', 'label' => 'West'], ['value' => 'east', 'label' => 'East']];
        $condition = ['id' => 'new:territory', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'NotEquals', 'operand' => 'west', 'option_ids' => []];
        $d['rules'] = [fixtureAdvanced('context', [['id' => 'new:any', 'operator' => 'Any', 'conditions' => [$condition, propertyCondition('Equals', '10')]]], 'B', 'ExcludeOptions', ['new:B0'])];
    });
    $engine = app(ConfiguratorEngine::class);
    expect($engine->evaluate(new ConfiguratorEvaluationInput($definition))->selections['new:B'])->toBe('new:B0');
    expect($engine->evaluate(new ConfiguratorEvaluationInput($definition, context: ['territory' => 'east']))->selections['new:B'])->toBe('new:B1');
    expect($engine->evaluate(new ConfiguratorEvaluationInput($definition, properties: ['Working_Pressure' => '10']))->selections['new:B'])->toBe('new:B1');
    $changed = $engine->evaluate(new ConfiguratorEvaluationInput($definition, intent: new ConfiguratorInteraction(ConfiguratorIntentType::ChangeContext, dimension: 'territory', choice: 'east')));
    expect($changed->context['territory'])->toBe('east')->and($changed->selections['new:B'])->toBe('new:B1');
});

test('presentation uses highest priority and conflicting ties fall back without widening legal choices', function () {
    $definition = engineDefinition(function (array &$d) {
        $low = fixtureAdvanced('low', [fixtureCondition('self')], 'A', 'SetLabel', [], 'Low');
        $high = fixtureAdvanced('high', [], 'A', 'SetLabel', [], 'High');
        $high['priority'] = 2;
        $tie = fixtureAdvanced('tie', [], 'B', 'SetHint', ['new:B1'], 'First');
        $tie['priority'] = 5;
        $tie2 = fixtureAdvanced('tie2', [], 'B', 'SetHint', ['new:B1'], 'Second');
        $tie2['priority'] = 5;
        $d['rules'] = [$low, $high, $tie, $tie2, fixtureAdvanced('restrict', [], 'B', 'AllowOptions', ['new:B1'])];
    });
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition));
    expect($result->attributes['new:A']['label'])->toBe('High')->and($result->attributes['new:B']['options']['new:B1']['hint'])->toBeNull()->and($result->attributes['new:B']['legal'])->toBe(['new:B1'])->and($result->configurationCode)->toBe('A0-B1-C0')->and(array_column($result->diagnostics, 'code'))->toContain('presentation_priority_tie');
});

test('unmapped drivers and inactive rules add no restriction while selected code conditions use exact current codes', function () {
    $definition = engineDefinition(function (array &$d) {
        $mapping = fixtureMapping();
        $mapping['is_active'] = false;
        $condition = fixtureCondition('code', 'A', 'A1');
        $condition['source_kind'] = 'SelectionCode';
        $d['rules'] = [$mapping, fixtureAdvanced('code', [$condition], 'C', 'AllowOptions', ['new:C1'])];
    });
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, selections: ['new:A' => 'new:A1']));
    expect($result->configurationCode)->toBe('A1-B0-C1');
    $partial = engineDefinition(fn (array &$d) => $d['rules'] = [fixtureMapping()]);
    expect(app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($partial, selections: ['new:A' => 'new:A1']))->attributes['new:B']['legal'])->toBe(['new:B0', 'new:B1']);
});

test('empty unassigned and entirely hidden definitions never produce a valid looking empty code', function () {
    $engine = app(ConfiguratorEngine::class);
    foreach ([null, engineDefinition(function (array &$d) {
        $d['attributes'] = [];
    }), engineDefinition(function (array &$d) {
        $d['rules'] = [fixtureAdvanced('a', [], 'A'), fixtureAdvanced('b', [], 'B'), fixtureAdvanced('c', [], 'C')];
    })] as $definition) {
        $result = $engine->evaluate(new ConfiguratorEvaluationInput($definition));
        expect($result->isComplete)->toBeFalse()->and($result->configurationCode)->toBeNull();
    }
});

test('temporary hidden restrictions do not erase the remembered former valid choice', function () {
    $definition = engineDefinition(function (array &$d) {
        $d['attributes'][1]['default_configurator_option_id'] = 'new:B1';
        $condition = fixtureCondition('hide', 'A', 'A1');
        $d['rules'] = [fixtureAdvanced('hide', [$condition]), fixtureAdvanced('restrict', [fixtureCondition('restrict', 'A', 'A1')], 'B', 'AllowOptions', ['new:B0'])];
    });
    $engine = app(ConfiguratorEngine::class);
    $initial = $engine->evaluate(new ConfiguratorEvaluationInput($definition));
    $hidden = $engine->evaluate(new ConfiguratorEvaluationInput($definition, selections: $initial->selections, intent: new ConfiguratorInteraction(ConfiguratorIntentType::SelectOption, 'new:A', 'new:A1')));
    expect($hidden->remembered['new:B'] ?? null)->toBe('new:B1');
    $restored = $engine->evaluate(new ConfiguratorEvaluationInput($definition, selections: $hidden->selections, remembered: $hidden->remembered, intent: new ConfiguratorInteraction(ConfiguratorIntentType::SelectOption, 'new:A', 'new:A0')));
    expect($restored->selections['new:B'])->toBe('new:B1');
});
