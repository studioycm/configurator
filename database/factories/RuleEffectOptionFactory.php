<?php

namespace Database\Factories;

use App\Models\ConfiguratorOption;
use App\Models\RuleEffect;
use App\Models\RuleEffectOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RuleEffectOption> */
class RuleEffectOptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['effect_id' => RuleEffect::factory(), 'configurator_option_id' => ConfiguratorOption::factory()];
    }
}
