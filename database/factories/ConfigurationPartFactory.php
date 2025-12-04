<?php

namespace Database\Factories;

use App\Models\Part;
use App\Models\ProductConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConfigurationPartFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'product_configuration_id' => ProductConfiguration::factory(),
            'part_id' => Part::factory(),
            'part_number' => fake()->numberBetween(-10000, 10000),
            'label' => fake()->word(),
            'material' => fake()->word(),
            'quantity' => fake()->randomFloat(3, 0, 99999.999),
            'unit' => fake()->word(),
            'segment_index' => fake()->numberBetween(-10000, 10000),
            'notes' => fake()->text(),
            'sort_order' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
