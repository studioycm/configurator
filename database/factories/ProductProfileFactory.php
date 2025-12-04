<?php

namespace Database\Factories;

use App\Models\CatalogGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'catalog_group_id' => CatalogGroup::factory(),
            'name' => fake()->name(),
            'product_code' => fake()->word(),
            'slug' => fake()->slug(),
            'short_label' => fake()->word(),
            'is_active' => fake()->boolean(),
            'sort_order' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
