<?php

namespace Database\Factories;

use App\Models\ConfiguratorOption;
use App\Models\RuleCondition;
use App\Models\RuleConditionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RuleConditionOption> */
class RuleConditionOptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['condition_id' => RuleCondition::factory(), 'configurator_option_id' => ConfiguratorOption::factory()];
    }
}
