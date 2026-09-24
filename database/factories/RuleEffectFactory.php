<?php

namespace Database\Factories;

use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorRule;
use App\Models\RuleEffect;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RuleEffect> */
class RuleEffectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['rule_id' => ConfiguratorRule::factory(), 'target_configurator_attribute_id' => ConfiguratorAttribute::factory(), 'kind' => 'SetLabel', 'target_scope' => 'Attribute', 'display_value' => 'Display label'];
    }
}
