<?php

use App\Services\ConfiguratorDefinitionCompiler;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';
uses(TestCase::class);

test('Mapping modes normalize legacy definitions and reject explicitly invalid values', function () {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['rules'] = [fixtureMapping()];
    $compiler = app(ConfiguratorDefinitionCompiler::class);
    $legacy = $compiler->compile($data, $attributes, $options);
    expect($legacy->data['rules'][0]['sets'][0]['disallowed_target_behavior'])->toBe('Disable');
    foreach (['Disable', 'Hide'] as $mode) {
        $data['rules'][0]['sets'][0]['disallowed_target_behavior'] = $mode;
        $compiled = $compiler->compile($data, $attributes, $options);
        expect($compiled->rules[0]->sets[0]->disallowedTargetBehavior->value)->toBe($mode);
    }
    foreach ([null, '', 'hide', 'Allow', 0] as $mode) {
        $data['rules'][0]['sets'][0]['disallowed_target_behavior'] = $mode;
        try {
            $compiler->compile($data, $attributes, $options);
            $this->fail('Invalid Mapping mode was accepted.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('rules.0.sets.0.disallowed_target_behavior');
        }
    }
});

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
    'overlong product field key' => function (array &$d) {
        $c = fixtureCondition('c');
        $c['source_kind'] = 'ProductProperty';
        $c['source_configurator_attribute_id'] = null;
        $c['property_key'] = str_repeat('x', 101);
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

test('V25 compiler returns normalized intent while keeping runtime displayValue a string', function (array $subtitle, string $mode, ?string $stored, string $resolved) {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['attributes'][0]['options'][0] = [...$data['attributes'][0]['options'][0], ...$subtitle];
    $definition = app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);
    expect($definition->data['attributes'][0]['options'][0])->toMatchArray(['subtitle_mode' => $mode, 'display_value_override' => $stored]);
    expect($definition->attributes['new:A']->options['new:A0']->displayValue)->toBe($resolved);
})->with(['None' => [['subtitle_mode' => 'none', 'display_value_override' => 'Ignored'], 'none', null, ''], 'Master' => [['subtitle_mode' => 'master'], 'master', null, 'Value A0'], 'Custom maximum' => [['subtitle_mode' => 'custom', 'display_value_override' => str_repeat('s', 255)], 'custom', str_repeat('s', 255), str_repeat('s', 255)], 'Legacy blank' => [['display_value_override' => " \t "], 'none', null, '']]);

test('V25 full definitions reject invalid explicit mode or UI transport keys', function (array $subtitle, string $field) {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['attributes'][0]['options'][0] = [...$data['attributes'][0]['options'][0], ...$subtitle];
    try {
        app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);
        test()->fail('Expected invalid subtitle definition to be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('attributes.0.options.0'.$field);
    }
})->with(['explicit null' => [['subtitle_mode' => null], '.subtitle_mode'], 'explicit empty' => [['subtitle_mode' => ''], '.subtitle_mode'], 'explicit invalid' => [['subtitle_mode' => 'forged'], '.subtitle_mode'], 'Custom without text' => [['subtitle_mode' => 'custom'], '.display_value_override'], 'helper' => [['_subtitle_text' => 'forged'], '']]);
