<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);
require_once __DIR__.'/../ConfiguratorFixtures.php';

test('option rule override summaries can reference any option in the same configurator', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['attributes'][2]['label_override'] = 'Override Attribute';
    $data['attributes'][2]['options'][0]['label_override'] = 'Override Option';
    $rule = fixtureAdvanced('cross-target', [fixtureCondition('trigger')], 'B', 'AllowOptions', ['new:B0']);
    foreach (['SetLabel' => 'Partner Label', 'SetDisplayValue' => 'Partner Value', 'SetHint' => 'Partner Hint'] as $kind => $text) {
        $rule['effects'][] = ['id' => 'new:'.$kind, 'target_configurator_attribute_id' => 'new:C', 'kind' => $kind, 'target_scope' => 'Options', 'option_ids' => ['new:C0'], 'display_value' => $text];
    }
    $data['rules'] = [$rule];
    app(SaveConfiguratorDefinition::class)->handle(User::factory()->create(['email' => 'ycm@data4.work']), $configurator, $data);
    expect($configurator->rules()->sole()->presentationSummaries())->toBe([
        'SetLabel' => ['Override Attribute — Override Option → Partner Label'],
        'SetDisplayValue' => ['Override Attribute — Override Option → Partner Value'],
        'SetHint' => ['Override Attribute — Override Option → Partner Hint'],
    ]);

});
