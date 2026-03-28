<?php

use App\Models\ConfigProfile;
use App\Models\OptionRule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);

test('option rule override summaries can reference any option in the same configurator', function () {
    $profile = ConfigProfile::factory()->create();

    $triggerAttribute = $profile->attributes()->create([
        'name' => 'Trigger Attribute',
        'label' => 'Trigger Attribute',
        'slug' => 'trigger-attribute',
        'input_type' => 'toggle',
        'sort_order' => 1,
        'is_required' => true,
        'segment_index' => 1,
    ]);

    $targetAttribute = $profile->attributes()->create([
        'name' => 'Target Attribute',
        'label' => 'Target Attribute',
        'slug' => 'target-attribute',
        'input_type' => 'toggle',
        'sort_order' => 2,
        'is_required' => true,
        'segment_index' => 2,
    ]);

    $overrideAttribute = $profile->attributes()->create([
        'name' => 'Override Attribute',
        'label' => 'Override Attribute',
        'slug' => 'override-attribute',
        'input_type' => 'select',
        'sort_order' => 3,
        'is_required' => false,
        'segment_index' => 3,
    ]);

    $triggerOption = $triggerAttribute->options()->create([
        'label' => 'Trigger Option',
        'code' => 'TR',
        'sort_order' => 1,
        'is_default' => true,
        'is_active' => true,
    ]);

    $targetOption = $targetAttribute->options()->create([
        'label' => 'Target Option',
        'code' => 'TG',
        'sort_order' => 1,
        'is_default' => true,
        'is_active' => true,
    ]);

    $overrideOption = $overrideAttribute->options()->create([
        'label' => 'Override Option',
        'code' => 'OV',
        'sort_order' => 1,
        'is_default' => false,
        'is_active' => true,
    ]);

    $rule = OptionRule::query()->create([
        'config_profile_id' => $profile->id,
        'config_option_id' => $triggerOption->id,
        'target_attribute_id' => $targetAttribute->id,
        'allowed_option_ids' => [$targetOption->id],
        'dependency_type' => 'disabled',
        'is_active' => true,
        'priority' => 0,
        'rule_payload' => [
            'label_overrides' => [
                ['option_id' => $overrideOption->id, 'label' => 'Partner Label'],
            ],
            'value_overrides' => [
                ['option_id' => $overrideOption->id, 'value' => 'Partner Value'],
            ],
            'hints' => [
                ['option_id' => $overrideOption->id, 'hint' => 'Partner Hint'],
            ],
        ],
    ]);

    expect($rule->configuratorOptionLabelsFor([$overrideOption->id]))
        ->toBe(['Override Attribute — Override Option'])
        ->and($rule->labelOverrideSummaries())
        ->toBe(['Override Attribute — Override Option → Partner Label'])
        ->and($rule->valueOverrideSummaries())
        ->toBe(['Override Attribute — Override Option → Partner Value'])
        ->and($rule->hintOverrideSummaries())
        ->toBe(['Override Attribute — Override Option → Partner Hint']);
});
