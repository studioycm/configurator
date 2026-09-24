<?php

namespace Tests\Fixtures\Legacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\Legacy\ConfigProfileScope;
use Tests\Fixtures\Legacy\Models\ProductProfile;

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
