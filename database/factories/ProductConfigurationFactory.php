<?php

namespace Database\Factories;

use App\Models\ProductProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductConfigurationFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'product_profile_id' => ProductProfile::factory(),
            'configuration_code' => fake()->word(),
            'name' => fake()->name(),
            'is_active' => fake()->boolean(),
            'drawing_image_path' => fake()->word(),
            'config_data' => '{}',
        ];
    }
}
