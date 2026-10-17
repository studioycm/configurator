<?php

use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorEvaluationResult;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorEngine;
use App\Services\ConfiguratorPreviewNotes;
use Tests\TestCase;

uses(TestCase::class);
require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

function previewNotesResult(Closure $alter, array $properties = [], array $selections = []): ConfiguratorEvaluationResult
{
    [$data, $attributes, $options] = compiledDefinitionFixture();
    foreach ($options as &$option) {
        $option['semantic_id'] = strtolower($option['code']).'_value';
    }
    unset($option);
    $alter($data);
    $definition = app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);

    return app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, properties: $properties, selections: $selections, trace: true));
}

test('Preview explains a Mapping restriction using source and affected Value semantic IDs', function () {
    $result = previewNotesResult(function (array &$data): void {
        $rule = fixtureMapping('selection-map');
        $rule['sets'][0]['disallowed_target_behavior'] = 'Hide';
        $data['rules'] = [$rule];
    });

    $notes = app(ConfiguratorPreviewNotes::class)->build($result);

    expect($notes['effects'])->toHaveCount(1);
    expect($notes['effects'][0])->toMatchArray(['attribute' => 'B', 'values' => ['b0_value (B0)'], 'sources' => ['A: a0_value (A0)'], 'rule' => 'selection-map']);
    expect($notes['selected_values'])->toBe([['attribute' => 'A', 'value' => 'a0_value (A0)'], ['attribute' => 'B', 'value' => 'b1_value (B1)'], ['attribute' => 'C', 'value' => 'c0_value (C0)']]);
});

test('Preview keeps product mixed and unconditional effects out of selection notes including nested predicates', function () {
    $property = ['id' => 'new:product', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Working_Pressure', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => '1', 'option_ids' => []];
    $result = previewNotesResult(function (array &$data) use ($property): void {
        $data['rules'] = [
            fixtureAdvanced('product-only', [$property], 'B', 'DisableOptions', ['new:B0']),
            fixtureAdvanced('mixed', [['id' => 'new:nested', 'operator' => 'Any', 'conditions' => [fixtureCondition('a0'), [...$property, 'id' => 'new:other-property']]]], 'C', 'HideAttribute'),
            fixtureAdvanced('system', [], 'B', 'DisableOptions', ['new:B1']),
        ];
    }, ['Working_Pressure' => '1']);

    $notes = app(ConfiguratorPreviewNotes::class)->build($result);

    expect($notes['effects'])->toBe([])->and($notes['warnings'])->toBe([]);
    expect(array_column($notes['diagnostics'], 'code'))->toContain('no_legal_options');
});

test('Preview omits restrictions already imposed by defaults or earlier product rules', function () {
    $result = previewNotesResult(function (array &$data): void {
        $data['attributes'][1]['options'][0]['disabled_by_default'] = true;
        $data['rules'] = [fixtureMapping('no-new-restriction')];
    });

    expect(app(ConfiguratorPreviewNotes::class)->build($result)['effects'])->toBe([]);

    $blocked = previewNotesResult(function (array &$data): void {
        $data['attributes'][1]['options'][0]['disabled_by_default'] = true;
        $data['attributes'][1]['options'][1]['disabled_by_default'] = true;
        $data['rules'] = [fixtureMapping('no-new-restriction')];
    });
    expect(app(ConfiguratorPreviewNotes::class)->build($blocked)['warnings'])->toBe([]);
});

test('overlapping product restrictions stay in diagnostics regardless of rule order', function (bool $productFirst) {
    $result = previewNotesResult(function (array &$data) use ($productFirst): void {
        $selection = fixtureAdvanced('selection-block', [fixtureCondition('a0')], 'B', 'DisableOptions', ['new:B0']);
        $product = fixtureAdvanced('product-block', [['id' => 'new:pressure', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Working_Pressure', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => '1', 'option_ids' => []]], 'B', 'DisableOptions', ['new:B0']);
        $selection['priority'] = $productFirst ? 1 : 0;
        $product['priority'] = $productFirst ? 0 : 1;
        $data['rules'] = $productFirst ? [$product, $selection] : [$selection, $product];
    }, ['Working_Pressure' => '1'], ['new:B' => 'new:B0']);

    $notes = app(ConfiguratorPreviewNotes::class)->build($result);

    expect($notes['effects'])->toBe([]);
    expect(array_column($notes['warnings'], 'code'))->toContain('selection_adjusted');
    expect(array_column($notes['diagnostics'], 'code'))->not->toContain('selection_adjusted');
})->with(['selection first' => false, 'product first' => true]);

test('Preview reports all choice adjustments and selection conflicts in state notes', function () {
    $result = previewNotesResult(fn (array &$data) => $data['rules'] = [fixtureMapping('replace')], selections: ['new:A' => 'new:A0', 'new:B' => 'new:B0']);
    $notes = app(ConfiguratorPreviewNotes::class)->build($result);

    expect($notes['warnings'])->toHaveCount(1);
    expect($notes['warnings'][0]['message'])->toContain('Choice for B was updated');
    expect($notes['diagnostics'])->toBe([]);

    $conflict = previewNotesResult(fn (array &$data) => $data['rules'] = [fixtureAdvanced('block-all', [fixtureCondition('a0')], 'B', 'DisableOptions', ['new:B0', 'new:B1'])]);
    expect(array_column(app(ConfiguratorPreviewNotes::class)->build($conflict)['warnings'], 'code'))->toContain('no_legal_options');

    $system = previewNotesResult(fn (array &$data) => $data['attributes'][1]['options'][0]['disabled_by_default'] = true, selections: ['new:B' => 'new:B0']);
    expect(array_column(app(ConfiguratorPreviewNotes::class)->build($system)['warnings'], 'code'))->toContain('selection_adjusted');

    $noop = previewNotesResult(fn (array &$data) => $data['rules'] = [fixtureAdvanced('selection', [fixtureCondition('a0')], 'B', 'DisableOptions', ['new:B0']), fixtureAdvanced('system-noop', [], 'B', 'AllowOptions', ['new:B0', 'new:B1'])], selections: ['new:B' => 'new:B0']);
    expect(array_column(app(ConfiguratorPreviewNotes::class)->build($noop)['warnings'], 'code'))->toContain('selection_adjusted');
});

test('Preview shows only winning selection driven presentation and leaves product winners and ties in diagnostics', function () {
    $result = previewNotesResult(function (array &$data): void {
        $data['rules'] = [
            fixtureAdvanced('selected-hint', [fixtureCondition('a0')], 'B', 'SetHint', ['new:B1'], 'Useful selection explanation'),
            fixtureAdvanced('selected-hidden', [fixtureCondition('a0-second')], 'C'),
        ];
    }, selections: ['new:B' => 'new:B1']);

    $notes = app(ConfiguratorPreviewNotes::class)->build($result);

    expect(array_column($notes['effects'], 'text'))->toContain('Useful selection explanation');
    expect(array_column($notes['effects'], 'attribute'))->toContain('C');

    $overridden = previewNotesResult(function (array &$data): void {
        $product = fixtureAdvanced('product-winner', [['id' => 'new:pressure', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Working_Pressure', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => '1', 'option_ids' => []]], 'B', 'SetHint', [], 'Product explanation');
        $product['priority'] = 1;
        $data['rules'] = [fixtureAdvanced('selection-loser', [fixtureCondition('a0')], 'B', 'SetHint', [], 'Selection explanation'), $product];
    }, ['Working_Pressure' => '1']);
    expect(app(ConfiguratorPreviewNotes::class)->build($overridden)['effects'])->toBe([]);

    $tie = previewNotesResult(fn (array &$data) => $data['rules'] = [fixtureAdvanced('one', [fixtureCondition('a0')], 'B', 'SetHint', [], 'One'), fixtureAdvanced('two', [fixtureCondition('a0-again')], 'B', 'SetHint', [], 'Two')]);
    $tieNotes = app(ConfiguratorPreviewNotes::class)->build($tie);
    expect($tieNotes['effects'])->toBe([])->and(array_column($tieNotes['diagnostics'], 'code'))->toContain('presentation_priority_tie');
});

test('Preview traces every overlapping restriction when no legal values remain', function (bool $productFirst) {
    $result = previewNotesResult(function (array &$data) use ($productFirst): void {
        $selection = fixtureAdvanced('usa-body', [fixtureCondition('usa')], 'B', 'HideOptions', ['new:B0', 'new:B1']);
        $selection['label'] = 'USA hides body';
        $product = fixtureAdvanced('metal-body', [['id' => 'new:metal', 'source_kind' => 'ProductProperty', 'property_key' => 'Automatic_Type', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => 'Metal Shell', 'source_configurator_attribute_id' => null, 'option_ids' => []]], 'B', 'HideOptions', ['new:B1']);
        $product['label'] = 'Metal Shell restriction';
        $selection['priority'] = $productFirst ? 1 : 0;
        $product['priority'] = $productFirst ? 0 : 1;
        $data['rules'] = [$selection, $product];
    }, ['Automatic_Type' => 'Metal Shell']);

    $conflict = app(ConfiguratorPreviewNotes::class)->build($result)['conflicts'][0];

    expect($conflict['attribute'])->toBe('B');
    expect($conflict['rules'])->toContain('USA hides body', 'Metal Shell restriction');
    expect($conflict['values'][0]['reasons'])->toHaveCount(1);
    expect(implode(' ', $conflict['values'][1]['reasons']))->toContain('USA hides body', 'Metal Shell restriction');
    expect($conflict['values'][1]['value'])->toBe('b1_value (B1)');
})->with([false, true]);

test('Preview explains initial hidden values even without a restricting Rule', function () {
    $result = previewNotesResult(function (array &$data): void {
        foreach ($data['attributes'][1]['options'] as &$value) {
            $value['hidden_by_default'] = true;
        }
    });

    $conflict = app(ConfiguratorPreviewNotes::class)->build($result)['conflicts'][0];
    expect($conflict['rules'])->toBe([]);
    expect($conflict['values'][0]['reasons'])->toContain(__('configurator.preview.initially_hidden_reason'));
});

test('a replacement notice stays informational when another Attribute has a blocking conflict', function () {
    $result = previewNotesResult(function (array &$data): void {
        $data['rules'] = [fixtureMapping('replace-b'), fixtureAdvanced('block-c', [], 'C', 'HideOptions', ['new:C0', 'new:C1'])];
    }, selections: ['new:B' => 'new:B0']);

    $issues = app(ConfiguratorEditorChanges::class)->blockingIssues($result);

    expect($result->isComplete)->toBeFalse();
    expect($issues)->toHaveCount(1)->and($issues[0])->toContain('No legal value remains for C');
});

test('a Mapping conflict identifies its matched driver and set rather than an unconditional cause', function () {
    $result = previewNotesResult(function (array &$data): void {
        $mapping = fixtureMapping('driver-map');
        $mapping['sets'][0]['label'] = 'Selected source set';
        $data['rules'] = [$mapping, fixtureAdvanced('block-remaining', [], 'B', 'HideOptions', ['new:B1'])];
    });

    $conflict = app(ConfiguratorPreviewNotes::class)->build($result)['conflicts'][0];
    $mapping = collect($conflict['causes'])->firstWhere('rule', 'driver-map');
    expect($mapping['conditions'])->toContain('A', 'a0_value (A0)', 'Selected source set');
});

test('conflict navigation identifies Rule and local Value causes separately', function () {
    $result = previewNotesResult(function (array &$data): void {
        $data['attributes'][1]['options'][0]['hidden_by_default'] = true;
        $data['rules'] = [fixtureAdvanced('restrict-b', [], 'B', 'HideOptions', ['new:B1'])];
    });
    $conflict = app(ConfiguratorPreviewNotes::class)->build($result)['conflicts'][0];

    expect($conflict['causes'][0]['target'])->toBe(['type' => 'rule', 'id' => 'new:restrict-b']);
    expect($conflict['values'][0]['causes'][0]['target'])->toBe(['type' => 'value', 'attribute_id' => 'new:B', 'id' => 'new:B0']);
    expect($conflict['values'][1]['causes'][0]['target'])->toBe(['type' => 'rule', 'id' => 'new:restrict-b']);
});

test('shared library unavailability does not offer a misleading local Value editor', function () {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['attributes'][1]['options'][0]['hidden_by_default'] = true;
    $options[4]['is_hidden'] = true;
    $options[7] = [...$options[4], 'code' => 'B2', 'is_hidden' => false, 'is_active' => false];
    $data['attributes'][1]['options'][] = [...$data['attributes'][1]['options'][1], 'id' => 'new:B2', 'option_id' => 7, 'display_order' => 2];
    $definition = app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, trace: true));
    $conflict = app(ConfiguratorPreviewNotes::class)->build($result)['conflicts'][0];

    expect($conflict['values'][1]['causes'][0]['target'])->toBeNull();
    expect($conflict['values'][2]['causes'][0]['target'])->toBeNull();
    expect($conflict['values'][1]['causes'][0]['message'])->toBe(__('configurator.preview.canonical_unavailable_reason'));
});
