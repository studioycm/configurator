<?php

namespace Tests\Fixtures\Legacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\Legacy\Models\ProductConfiguration;

class ConfigurationSpecificationFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'product_configuration_id' => ProductConfiguration::factory(),
            'spec_group' => fake()->word(),
            'key' => fake()->word(),
            'value' => fake()->word(),
            'unit' => fake()->word(),
            'sort_order' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
