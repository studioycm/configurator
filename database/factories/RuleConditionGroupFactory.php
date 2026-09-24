<?php

namespace Database\Factories;

use App\Models\ConfiguratorRule;
use App\Models\RuleConditionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RuleConditionGroup> */
class RuleConditionGroupFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['rule_id' => ConfiguratorRule::factory(), 'operator' => 'All', 'sort_order' => 0];
    }
}
