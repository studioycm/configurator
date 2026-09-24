<?php

use App\DTO\ConfiguratorEvaluationInput;
use App\Services\ConfiguratorEngine;
use Tests\TestCase;

uses(TestCase::class);
require_once __DIR__.'/../ConfiguratorFixtures.php';

test('default selection picks defaults then first active', function () {
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput(retainedCapabilityDefinition()));
    expect($result->selections)->toBe(['new:A' => 'new:A0', 'new:B' => 'new:B0'])->and($result->configurationCode)->toBe('DI-EP');

});

test('base allowed returns all active option ids', function () {
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput(retainedCapabilityDefinition()));
    expect($result->attributes['new:A']['legal'])->toBe(['new:A0', 'new:A1'])->and($result->attributes['new:B']['legal'])->toBe(['new:B0', 'new:B1']);

});

test('recalculateAllowed intersects rules only', function () {
    $definition = retainedCapabilityDefinition(function (array &$data) {
        $rule = fixtureMapping();
        $rule['sets'][0]['target_option_ids'] = ['new:B0'];
        $data['rules'] = [$rule];
    });
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition));
    expect($result->attributes['new:B']['legal'])->toBe(['new:B0'])->and($result->attributes['new:A']['legal'])->toBe(['new:A0', 'new:A1']);

});

test('pruneInvalidSelections removes only invalid choices', function () {
    $definition = retainedCapabilityDefinition(function (array &$data) {
        $data['attributes'][0]['options'][1]['disabled_by_default'] = true;
    });
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, selections: ['new:A' => 'new:A1', 'new:B' => 'new:B1', '99' => '5']));
    expect($result->selections)->toBe(['new:A' => 'new:A0', 'new:B' => 'new:B1']);

});

test('fillMissingSelections auto picks default or first allowed when current is invalid', function () {
    $definition = retainedCapabilityDefinition(function (array &$data) {
        $data['attributes'][0]['options'][1]['disabled_by_default'] = true;
        $data['attributes'][1]['options'][0]['disabled_by_default'] = true;
    });
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, selections: ['new:A' => 'new:A1', 'new:B' => 'new:B0']));
    expect($result->selections)->toBe(['new:A' => 'new:A0', 'new:B' => 'new:B1']);

});

test('isComplete validates required stages filled', function () {
    $engine = app(ConfiguratorEngine::class);
    expect($engine->evaluate(new ConfiguratorEvaluationInput(retainedCapabilityDefinition()))->isComplete)->toBeTrue();
    $blocked = retainedCapabilityDefinition(function (array &$data) {
        foreach ($data['attributes'][1]['options'] as &$option) {
            $option['disabled_by_default'] = true;
        }
    });
    $result = $engine->evaluate(new ConfiguratorEvaluationInput($blocked));
    expect($result->isComplete)->toBeFalse()->and($result->configurationCode)->toBeNull();

});

test('buildConfigurationCode concatenates codes by segment index', function () {
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput(retainedCapabilityDefinition(), selections: ['new:A' => 'new:A1', 'new:B' => 'new:B1']));
    expect($result->configurationCode)->toBe('CS-VT');

});

test('evaluateState applies context aware ui state and rule priority', function () {
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput(retainedContextDefinition(), context: ['territory' => 'Europe']));
    expect($result->attributes['new:B']['legal'])->toBe(['new:B0'])
        ->and($result->attributes['new:B']['hidden'])->toBe(['new:B2'])
        ->and($result->attributes['new:B']['disabled'])->toBe(['new:B1'])
        ->and($result->attributes['new:B']['options']['new:B0'])->toBe(['label' => 'EPDM Premium', 'display_value' => 'EPDM Premium', 'hint' => 'Rule hint']);

});

test('evaluateState ignores context gated rules when the context does not match', function () {
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput(retainedContextDefinition(), context: ['territory' => 'USA']));
    expect($result->attributes['new:B']['legal'])->toBe(['new:B0', 'new:B1', 'new:B2'])
        ->and($result->attributes['new:B']['hidden'])->toBe([])
        ->and($result->attributes['new:B']['options']['new:B0']['label'])->toBe('EP')
        ->and($result->attributes['new:B']['options']['new:B0']['hint'])->toBe('Base hint');

});
