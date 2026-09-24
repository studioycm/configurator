<?php

namespace Database\Factories;

use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConfiguratorRule> */
class ConfiguratorRuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['configurator_id' => Configurator::factory(), 'label' => fake()->words(3, true), 'kind' => 'Advanced', 'priority' => 0, 'is_active' => true];
    }
}
