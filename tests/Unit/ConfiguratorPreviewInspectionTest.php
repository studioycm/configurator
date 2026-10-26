<?php

use App\DTO\ConfiguratorDefinition;
use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorEvaluationResult;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorEngine;
use App\Services\ConfiguratorPreviewInspection;
use App\Services\ConfiguratorPreviewNotes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);
require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

/** @return array{ConfiguratorEvaluationResult, ConfiguratorDefinition} */
function inspectionFixture(?Closure $alter = null, array $properties = [], array $selections = []): array
{
    [$data, $attributes, $options] = compiledDefinitionFixture();
    foreach ($options as &$option) {
        $option['semantic_id'] = strtolower($option['code']).'_value';
    }
    unset($option);
    if ($alter !== null) {
        $alter($data, $attributes, $options);
    }
    $definition = app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);

    return [app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, properties: $properties, selections: $selections, trace: true)), $definition];
}

test('inspection preserves Attribute display order and places hidden Values last', function () {
    [$result] = inspectionFixture(function (array &$data): void {
        $data['attributes'][1]['options'][0]['hidden_by_default'] = true;
        $data['attributes'][2]['options'][1]['disabled_by_default'] = true;
        $data['rules'] = [fixtureAdvanced('hide-a', [], 'A'), fixtureAdvanced('rename', [], 'B', 'SetLabel', ['new:B1'], 'Effective value'), fixtureAdvanced('hint', [], 'B', 'SetHint', [], 'Effective hint')];
    });

    $report = app(ConfiguratorPreviewInspection::class)->build($result);

    expect(array_keys($report['attributes']))->toBe(['new:A', 'new:B', 'new:C']);
    expect(array_keys($report['attributes']['new:B']['options']))->toBe(['new:B1', 'new:B0']);
    expect($report['attributes']['new:B'])->toMatchArray(['hint' => 'Effective hint', 'state' => 'available', 'selected_id' => 'new:B1', 'input_type' => 'toggle']);
    expect($report['attributes']['new:B']['options']['new:B1'])->toMatchArray(['label' => 'Effective value', 'code' => 'B1', 'semantic_id' => 'b1_value', 'state' => 'selected', 'legal' => true, 'selected' => true]);
    expect($report['attributes']['new:B']['options']['new:B0'])->toMatchArray(['state' => 'hidden', 'initial_hidden' => true, 'legal' => false]);
    expect($report['attributes']['new:C']['options']['new:C1']['state'])->toBe('disabled');
    expect($report['attributes']['new:A']['options']['new:A0']['state'])->toBe('hidden');
    expect($report['attributes']['new:A']['options']['new:A0']['causes'][0])->toMatchArray(['rule_id' => 'new:hide-a', 'category' => 'system', 'effect_kind' => 'HideAttribute']);
});

test('unavailable evaluation retains original lifecycle provenance without inventing legal choices', function () {
    [$result, $definition] = inspectionFixture(function (array &$data, array &$attributes, array &$options): void {
        $options[1]['is_active'] = false;
        $data['attributes'][1]['options'][1]['hidden_by_default'] = true;
    });

    $report = app(ConfiguratorPreviewInspection::class)->build($result, $definition);

    expect($result->definition)->toBeNull();
    expect($report['attributes']['new:A'])->toMatchArray(['state' => 'unavailable', 'selected_id' => null, 'applicable' => null]);
    expect($report['attributes']['new:A']['options']['new:A0'])->toMatchArray(['state' => 'unavailable', 'legal' => null, 'selected' => false, 'active' => false]);
    expect($report['attributes']['new:A']['options']['new:A0']['causes'][0]['target'])->toBeNull();
    expect($report['attributes']['new:B']['options']['new:B1']['initial_hidden'])->toBeTrue();
    expect(app(ConfiguratorPreviewInspection::class)->build(null)['diagnostics'][0]['code'])->toBe('preview_unavailable');
});

test('all three overlapping restrictions explain a four Value conflict independently of priority', function (bool $reversePriority) {
    [$result] = inspectionFixture(function (array &$data, array &$attributes, array &$options) use ($reversePriority): void {
        foreach ([2, 3] as $number) {
            $options[7 + $number] = ['attribute_id' => 2, 'code' => 'B'.$number, 'label' => 'Value B'.$number];
            $data['attributes'][1]['options'][] = [...$data['attributes'][1]['options'][1], 'id' => 'new:B'.$number, 'option_id' => 7 + $number, 'display_order' => $number];
        }
        $metal = fixtureAdvanced('metal', [], 'B', 'HideOptions', ['new:B1', 'new:B2', 'new:B3']);
        $inch = fixtureAdvanced('inch', [], 'B', 'HideOptions', ['new:B1', 'new:B2', 'new:B3']);
        $usa = fixtureAdvanced('usa', [fixtureCondition('selected-usa')], 'B', 'HideOptions', ['new:B0', 'new:B3']);
        $usa['priority'] = $reversePriority ? 100 : 0;
        $data['rules'] = [$metal, $inch, $usa];
    });

    $report = app(ConfiguratorPreviewInspection::class)->build($result);
    $attribute = $report['attributes']['new:B'];
    $inch = collect($attribute['causes'])->firstWhere('rule_id', 'new:inch');

    expect($attribute['state'])->toBe('no_legal_values');
    expect(array_column($attribute['causes'], 'rule_id'))->toBe(['new:metal', 'new:inch', 'new:usa']);
    expect($inch)->toMatchArray(['additional_options' => [], 'overlapping_options' => ['new:B1', 'new:B2', 'new:B3'], 'priority' => null]);
    expect($attribute['options']['new:B3']['causes'])->toHaveCount(3);
    expect($report['conflicts'][0]['rules'])->toBe(['metal', 'inch', 'usa']);
})->with([false, true]);

test('Mapping and AllowOptions causes retain matched set and every excluded Value', function () {
    [$result] = inspectionFixture(function (array &$data): void {
        $mapping = fixtureMapping('mapped');
        $mapping['sets'][0]['label'] = 'Source set';
        $data['rules'] = [$mapping, fixtureAdvanced('allow', [], 'B', 'AllowOptions', ['new:B0'])];
    });

    $report = app(ConfiguratorPreviewInspection::class)->build($result);
    $mapping = $report['attributes']['new:B']['causes'][0];

    expect($mapping)->toMatchArray(['set_id' => 'new:mappedset', 'set_label' => 'Source set', 'category' => 'selection', 'options' => ['new:B0'], 'target' => ['type' => 'rule', 'id' => 'new:mapped']]);
    expect($mapping['conditions'])->toContain('A', 'a0_value (A0)', 'Source set');
    expect($report['attributes']['new:B']['options']['new:B1']['causes'][0])->toMatchArray(['effect_kind' => 'AllowOptions', 'options' => ['new:B1']]);
});

test('presentation inspection includes candidates winner and base retained ties at both scopes', function (bool $tie) {
    [$result] = inspectionFixture(function (array &$data) use ($tie): void {
        $low = fixtureAdvanced('low', [], 'B', 'SetHint', [], 'Low');
        $low['priority'] = $tie ? 8 : 3;
        $high = fixtureAdvanced('high', [], 'B', 'SetHint', [], 'High');
        $high['priority'] = 8;
        $low['effects'][] = [...$low['effects'][0], 'id' => 'new:low-value', 'target_scope' => 'Options', 'option_ids' => ['new:B1'], 'display_value' => 'Low value'];
        $high['effects'][] = [...$high['effects'][0], 'id' => 'new:high-value', 'target_scope' => 'Options', 'option_ids' => ['new:B1'], 'display_value' => 'High value'];
        $data['rules'] = [$low, $high];
    });

    $report = app(ConfiguratorPreviewInspection::class)->build($result);
    $attribute = $report['attributes']['new:B']['presentation'][0];
    $value = $report['attributes']['new:B']['options']['new:B1']['presentation'][0];

    expect(array_column($attribute['candidates'], 'priority'))->toBe([$tie ? 8 : 3, 8]);
    expect($attribute)->toMatchArray(['field' => 'hint', 'target' => 'attribute', 'conflict' => $tie, 'effective_value' => $tie ? null : 'High', 'winner_rule_ids' => $tie ? ['new:low', 'new:high'] : ['new:high']]);
    expect($value)->toMatchArray(['target' => 'new:B1', 'conflict' => $tie, 'effective_value' => $tie ? null : 'High value']);
    expect(app(ConfiguratorEditorChanges::class)->blockingIssues($result))->toHaveCount($tie ? 1 : 0);
})->with([false, true]);

test('rule outcomes distinguish absent traces skipped restrictions disabled and excluded Rules', function () {
    [$result] = inspectionFixture(function (array &$data): void {
        $disabled = fixtureAdvanced('disabled', [], 'B', 'SetHint', [], 'Disabled');
        $disabled['is_active'] = false;
        $data['rules'] = [fixtureAdvanced('unmatched-presentation', [fixtureCondition('a1', 'A', 'A1')], 'B', 'SetHint', [], 'Unmatched'), fixtureAdvanced('unmatched-restriction', [fixtureCondition('a1-again', 'A', 'A1')], 'B', 'HideOptions', ['new:B1']), $disabled];
    });
    $excluded = fixtureAdvanced('excluded', [], 'B', 'SetHint', [], 'Public context');

    $report = app(ConfiguratorPreviewInspection::class)->build($result, excludedRules: [$excluded]);
    $rules = array_column($report['rules'], null, 'id');

    expect($rules['new:unmatched-presentation']['outcome'])->toBe('not_recorded');
    expect($rules['new:unmatched-restriction']['outcome'])->toBe('recorded_skipped');
    expect($rules['new:disabled'])->toMatchArray(['outcome' => 'not_recorded', 'disabled' => true, 'excluded' => false]);
    expect($rules['new:excluded'])->toMatchArray(['outcome' => 'not_recorded', 'disabled' => false, 'excluded' => true, 'target' => ['type' => 'rule', 'id' => 'new:excluded']]);
});

test('inspection builds repeatedly without SQL or trace growth and notes reuse its conflicts', function () {
    [$result] = inspectionFixture(fn (array &$data) => $data['rules'] = [fixtureAdvanced('block', [], 'B', 'DisableOptions', ['new:B0', 'new:B1'])]);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $first = app(ConfiguratorPreviewInspection::class)->build($result);
    $second = app(ConfiguratorPreviewInspection::class)->build($result);
    $notes = app(ConfiguratorPreviewNotes::class)->build($result, $first);

    expect($queries)->toBe([]);
    expect($second)->toBe($first);
    expect($first['trace'])->toBe($result->trace);
    expect($notes['conflicts'])->toBe($first['conflicts']);
});

test('Value inspection references its selection and every presentation trace with truthful Rule outcomes', function () {
    [$result] = inspectionFixture(function (array &$data): void {
        $low = fixtureAdvanced('low-value', [], 'B', 'SetHint', ['new:B1'], 'Low');
        $low['priority'] = 3;
        $high = fixtureAdvanced('high-value', [], 'B', 'SetHint', ['new:B1'], 'High');
        $high['priority'] = 8;
        $data['rules'] = [fixtureMapping('replace'), $low, $high];
    }, selections: ['new:B' => 'new:B0']);

    $report = app(ConfiguratorPreviewInspection::class)->build($result);
    $rules = array_column($report['rules'], null, 'id');
    $value = $report['attributes']['new:B']['options']['new:B1'];
    $valueTrace = array_map(fn (int $index): array => $report['trace'][$index], $value['trace_indices']);

    expect(array_column($valueTrace, 'type'))->toContain('selection', 'presentation');
    expect(array_column($valueTrace, 'rule_id'))->toContain('new:low-value', 'new:high-value');
    expect($rules['new:low-value']['outcome'])->toBe('recorded_candidate');
    expect($rules['new:high-value']['outcome'])->toBe('recorded_applied');
    expect($report['diagnostics'])->toBe($result->diagnostics);
    expect(array_column($report['diagnostics'], 'code'))->toContain('selection_adjusted');
});

test('inspection separates product context mixed selection and system sources without evaluating predicates', function () {
    $product = ['id' => 'new:product-condition', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Automatic_Type', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => 'Metal Shell', 'option_ids' => []];
    [$result] = inspectionFixture(function (array &$data) use ($product): void {
        $context = ['id' => 'new:context', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'Equals', 'operand' => 'USA', 'option_ids' => []];
        $data['context_schema']['territory'] = [['value' => 'USA', 'label' => 'USA']];
        $data['rules'] = [fixtureAdvanced('product', [$product], 'B', 'HideOptions', ['new:B1']), fixtureAdvanced('context', [$context], 'B', 'SetHint', [], 'Context'), fixtureAdvanced('mixed', [['id' => 'new:any', 'operator' => 'Any', 'conditions' => [fixtureCondition('selected'), [...$product, 'id' => 'new:mixed-product']]]], 'C', 'SetHint', [], 'Mixed')];
    }, ['Automatic_Type' => 'Metal Shell']);

    $report = app(ConfiguratorPreviewInspection::class)->build($result);
    $rules = array_column($report['rules'], null, 'id');

    expect($report['attributes']['new:B']['causes'][0]['category'])->toBe('product');
    expect($rules['new:context'])->toMatchArray(['category' => 'context', 'outcome' => 'not_recorded']);
    expect($rules['new:mixed']['category'])->toBe('mixed');
    expect($rules['new:mixed']['conditions'])->toContain('Any', 'A', 'Metal Shell');
});

test('Rule source identities link outgoing effects without claiming unmatched predicates succeeded', function () {
    [$result] = inspectionFixture(function (array &$data): void {
        $data['rules'] = [fixtureMapping('mapped'), fixtureAdvanced('unmatched', [fixtureCondition('a1-source', 'A', 'A1')], 'C', 'SetHint', ['new:C1'], 'Unmatched')];
    });

    $report = app(ConfiguratorPreviewInspection::class)->build($result);
    $rules = array_column($report['rules'], null, 'id');

    expect($rules['new:mapped'])->toMatchArray(['source_attribute_ids' => ['new:A'], 'source_option_ids' => ['new:A0'], 'affected_attribute_ids' => ['new:B']]);
    expect($rules['new:unmatched'])->toMatchArray(['source_attribute_ids' => ['new:A'], 'source_option_ids' => ['new:A1'], 'affected_attribute_ids' => ['new:C'], 'outcome' => 'not_recorded']);
});

test('several presentation effects retain their own candidate trace references', function () {
    [$result] = inspectionFixture(function (array &$data): void {
        $rule = fixtureAdvanced('several', [], 'B', 'SetHint', [], 'Attribute hint');
        $rule['effects'][] = [...$rule['effects'][0], 'id' => 'new:value-label', 'kind' => 'SetLabel', 'target_scope' => 'Options', 'option_ids' => ['new:B1'], 'display_value' => 'Value label'];
        $data['rules'] = [$rule];
    });

    $report = app(ConfiguratorPreviewInspection::class)->build($result);
    $attributeCandidate = $report['attributes']['new:B']['presentation'][0]['candidates'][0];
    $valueCandidate = $report['attributes']['new:B']['options']['new:B1']['presentation'][0]['candidates'][0];

    expect($attributeCandidate['trace_indices'])->toHaveCount(1);
    expect($valueCandidate['trace_indices'])->toHaveCount(1);
    expect($valueCandidate['trace_indices'])->not->toBe($attributeCandidate['trace_indices']);
});

test('inspection preserves distinct literal ProductProperty keys in compiled and excluded Rule conditions', function (bool $excluded) {
    $property = ['id' => 'new:literal-under-condition', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Body_Material', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => 'Metal', 'option_ids' => []];
    $rules = [fixtureAdvanced('literal-under', [$property], 'B', 'HideOptions', ['new:B1']), fixtureAdvanced('literal-space', [[...$property, 'id' => 'new:literal-space-condition', 'property_key' => 'Body Material']], 'C', 'HideOptions', ['new:C1'])];
    [$result] = inspectionFixture(function (array &$data) use ($excluded, $rules): void {
        $data['rules'] = $excluded ? [] : $rules;
    }, ['Body_Material' => 'Metal', 'Body Material' => 'Metal']);

    $report = app(ConfiguratorPreviewInspection::class)->build($result, excludedRules: $excluded ? $rules : []);
    $conditions = array_column($report['rules'], 'conditions', 'id');

    expect($conditions['new:literal-under'])->toContain('Body_Material');
    expect($conditions['new:literal-space'])->toContain('Body Material');
    expect($conditions['new:literal-under'])->not->toBe($conditions['new:literal-space']);
})->with(['compiled conditions' => false, 'excluded raw conditions' => true]);

test('V26 inspection retains independent base and effective subtitle text for matching presentation Rules', function () {
    [$result] = inspectionFixture(function (array &$data): void {
        $data['attributes'][1]['options'][1] = [...$data['attributes'][1]['options'][1], 'label_override' => 'Local main', 'subtitle_mode' => 'none'];
        $data['rules'] = [fixtureAdvanced('subtitle', [], 'B', 'SetDisplayValue', ['new:B1'], 'Rule subtitle')];
    }, selections: ['new:B' => 'new:B1']);
    $report = app(ConfiguratorPreviewInspection::class)->build($result);
    $value = $report['attributes']['new:B']['options']['new:B1'];

    expect($value)->toMatchArray(['label' => 'Local main', 'display_value' => 'Rule subtitle']);
    expect($result->definition->attributes['new:B']->options['new:B1']->displayValue)->toBe('');
    expect($value['presentation'][0])->toMatchArray(['field' => 'display_value', 'effective_value' => 'Rule subtitle', 'winner_rule_ids' => ['new:subtitle']]);
});

test('Mapping explanations retain zero whitespace and numeric looking set labels', function (?string $label, string $expected) {
    [$result] = inspectionFixture(function (array &$data) use ($label): void {
        $rule = fixtureMapping('exact-set');
        $rule['sets'][0]['label'] = $label;
        $data['rules'] = [$rule];
    });
    $report = app(ConfiguratorPreviewInspection::class)->build($result);
    expect($report['attributes']['new:B']['causes'][0]['set_label'])->toBe($expected);
})->with([['0', '0'], ['001', '001'], ['   ', '   '], [null, 'Set 1']]);

test('empty Mapping set labels still follow existing compiler validation', function () {
    expect(fn () => inspectionFixture(function (array &$data): void {
        $rule = fixtureMapping('invalid-empty-label');
        $rule['sets'][0]['label'] = '';
        $data['rules'] = [$rule];
    }))->toThrow(ValidationException::class);
});
