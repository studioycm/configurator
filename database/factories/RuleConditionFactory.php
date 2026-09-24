<?php

namespace Database\Factories;

use App\Models\ConfiguratorRule;
use App\Models\RuleCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RuleCondition> */
class RuleConditionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['rule_id' => ConfiguratorRule::factory(), 'source_kind' => 'ProductProperty', 'property_key' => 'Working_Pressure', 'operator' => 'Equals', 'operand' => '10', 'sort_order' => 0];
    }
}
