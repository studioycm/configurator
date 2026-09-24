<?php

namespace Tests\Fixtures\Legacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\Legacy\Models\CatalogGroup;

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
