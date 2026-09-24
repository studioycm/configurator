<?php

use App\Services\ConfiguratorDefinitionCompiler;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';
uses(TestCase::class);

test('compiler accepts partial mappings overlapping targets and self presentation without confusing display and graph order', function () {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['attributes'][0]['display_order'] = 2;
    $data['attributes'][2]['display_order'] = 0;
    $data['rules'] = [fixtureMapping(), fixtureAdvanced('label', [fixtureCondition('read')], 'A', 'SetLabel', [], 'Alternate')];
    $data['rules'][1]['priority'] = 1;
    $data['rules'][0]['sets'][] = ['id' => 'new:second', 'label' => null, 'sort_order' => 1, 'source_option_ids' => ['new:A1'], 'target_option_ids' => ['new:B1']];
    $definition = app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);
    expect($definition->evaluationOrder)->toBe(['new:A', 'new:B', 'new:C'])->and($definition->attributes['new:A']->defaultOptionId)->toBe('new:A0')->and($definition->rules[0]->sets)->toHaveCount(2);
});

test('compiler rejects inactive cycles and reports the involved attributes', function () {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['rules'] = [fixtureMapping('one', 'A', 'B'), fixtureMapping('two', 'B', 'C'), fixtureMapping('three', 'C', 'A')];
    $data['rules'][2]['is_active'] = false;
    try {
        app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);
        $this->fail('Cycle accepted');
    } catch (ValidationException $exception) {
        expect($exception->errors()['rules'][0])->toContain('A', 'B', 'C');
    }
});

test('compiler rejects malformed references typed operands orders defaults and policy payloads', function (Closure $alter) {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $alter($data);
    expect(fn () => app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options))->toThrow(ValidationException::class);
})->with([
    'foreign default' => fn (array &$d) => $d['attributes'][0]['default_configurator_option_id'] = 'new:B0',
    'foreign canonical option' => fn (array &$d) => $d['attributes'][0]['options'][0]['option_id'] = 3,
    'duplicate code order' => fn (array &$d) => $d['attributes'][0]['code_order'] = 1,
    'duplicate option membership' => fn (array &$d) => $d['attributes'][0]['options'][1]['option_id'] = 1,
    'unknown policies' => fn (array &$d) => $d['policy_overrides'] = ['allow_empty' => true],
    'unregistered product path' => function (array &$d) {
        $c = fixtureCondition('c');
        $c['source_kind'] = 'ProductProperty';
        $c['source_configurator_attribute_id'] = null;
        $c['property_key'] = '$.secret';
        $c['operand'] = 'x';
        $c['option_ids'] = [];
        $d['rules'] = [fixtureAdvanced('r', [$c])];
    },
    'empty nested group' => fn (array &$d) => $d['rules'] = [fixtureAdvanced('r', [['id' => 'new:g', 'operator' => 'Any', 'conditions' => []]])],
    'deep nesting' => fn (array &$d) => $d['rules'] = [fixtureAdvanced('r', [['id' => 'new:g', 'operator' => 'All', 'conditions' => [['id' => 'new:g2', 'operator' => 'Any', 'conditions' => [fixtureCondition('c')]]]]])],
    'self applicability cycle' => fn (array &$d) => $d['rules'] = [fixtureAdvanced('r', [fixtureCondition('c')], 'A')],
    'unknown source' => function (array &$d) {
        $c = fixtureCondition('c');
        $c['source_kind'] = 'ConfigurationCode';
        $d['rules'] = [fixtureAdvanced('r', [$c])];
    },
    'empty target set' => function (array &$d) {
        $r = fixtureMapping();
        $r['sets'][0]['target_option_ids'] = [];
        $d['rules'] = [$r];
    },
    'duplicate mapping source' => function (array &$d) {
        $r = fixtureMapping();
        $r['sets'][] = [...$r['sets'][0], 'id' => 'new:other', 'sort_order' => 1];
        $d['rules'] = [$r];
    },
]);
