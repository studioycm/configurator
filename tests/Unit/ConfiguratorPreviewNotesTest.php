<?php

use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorEvaluationResult;
use App\Services\ConfiguratorDefinitionCompiler;
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

    expect($notes['effects'])->toBe([])->and($notes['warnings'])->toBe([]);
    expect(array_column($notes['diagnostics'], 'code'))->toContain('selection_adjusted');
})->with(['selection first' => false, 'product first' => true]);

test('Preview reports selection driven adjustments and conflicts without treating default repairs as user effects', function () {
    $result = previewNotesResult(fn (array &$data) => $data['rules'] = [fixtureMapping('replace')], selections: ['new:A' => 'new:A0', 'new:B' => 'new:B0']);
    $notes = app(ConfiguratorPreviewNotes::class)->build($result);

    expect($notes['warnings'])->toHaveCount(1);
    expect($notes['warnings'][0]['message'])->toContain('b0_value (B0)', 'b1_value (B1)');
    expect($notes['diagnostics'])->toBe([]);

    $conflict = previewNotesResult(fn (array &$data) => $data['rules'] = [fixtureAdvanced('block-all', [fixtureCondition('a0')], 'B', 'DisableOptions', ['new:B0', 'new:B1'])]);
    expect(array_column(app(ConfiguratorPreviewNotes::class)->build($conflict)['warnings'], 'code'))->toContain('no_legal_options');

    $system = previewNotesResult(fn (array &$data) => $data['attributes'][1]['options'][0]['disabled_by_default'] = true, selections: ['new:B' => 'new:B0']);
    expect(app(ConfiguratorPreviewNotes::class)->build($system)['warnings'])->toBe([]);

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
