<?php

namespace Database\Factories;

use App\Models\Configurator;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Configurator> */
class ConfiguratorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'description' => null, 'context_schema' => ['territory' => [], 'application' => []], 'policy_overrides' => []];
    }
}
