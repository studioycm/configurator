<?php

use App\DTO\ConfiguratorEvaluationInput;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorEngine;
use Tests\TestCase;

require_once __DIR__.'/../ConfiguratorFixtures.php';
uses(TestCase::class);

test('baseAllowedFromManifest returns only active option ids per attribute', function () {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['attributes'][0]['options'][1]['disabled_by_default'] = true;
    $data['attributes'][1]['options'][1]['hidden_by_default'] = true;
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput(app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options)));
    expect($result->attributes['new:A']['legal'])->toBe(['new:A0'])->and($result->attributes['new:B']['legal'])->toBe(['new:B0']);

});

test('recalculateAllowedFromManifest applies restrict_allowed_options rules', function () {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['attributes'][1]['options'][0]['disabled_by_default'] = true;
    $rule = fixtureMapping();
    $rule['sets'][0]['source_option_ids'] = ['new:A1'];
    $rule['sets'][0]['target_option_ids'] = ['new:B0', 'new:B1'];
    $data['rules'] = [$rule];
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput(app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options), selections: ['new:A' => 'new:A1']));
    expect($result->attributes['new:B']['legal'])->toBe(['new:B1']);

});
