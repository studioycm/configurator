<?php

namespace Database\Factories;

use App\Models\ConfigAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConfigOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'config_attribute_id' => ConfigAttribute::factory(),
            'label' => fake()->word(),
            'code' => fake()->regexify('[A-Za-z0-9]{4}'),
            'sort_order' => fake()->numberBetween(-10000, 10000),
            'is_default' => fake()->boolean(),
            'is_active' => fake()->boolean(),
        ];
    }
}
