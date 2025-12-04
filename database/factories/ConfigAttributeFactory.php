<?php

namespace Database\Factories;

use App\Models\ConfigProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConfigAttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'config_profile_id' => ConfigProfile::factory(),
            'name' => fake()->name(),
            'label' => fake()->word(),
            'slug' => fake()->slug(),
            'input_type' => fake()->word(),
            'sort_order' => fake()->numberBetween(-10000, 10000),
            'is_required' => fake()->boolean(),
            'segment_index' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
