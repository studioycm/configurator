<?php

namespace Database\Factories;

use App\ConfigProfileScope;
use App\Models\ProductProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConfigProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'product_profile_id' => ProductProfile::factory(),
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'description' => fake()->text(),
            'scope' => fake()->randomElement(ConfigProfileScope::cases()),
            'is_active' => fake()->boolean(),
            'extra_rules_json' => [],
        ];
    }
}
