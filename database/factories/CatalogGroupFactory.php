<?php

namespace Database\Factories;

use App\Models\CatalogGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'description' => fake()->text(),
            'parent_id' => null,
            'is_active' => fake()->boolean(),
            'sort_order' => fake()->numberBetween(-10000, 10000),
            'path' => fake()->word(),
        ];
    }
}
