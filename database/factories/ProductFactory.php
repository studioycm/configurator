<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['legacy_id' => (string) fake()->unique()->numberBetween(10000, 9999999), 'legacy_group_id' => 'fixture-group', 'group_id' => Group::factory(), 'product_code' => fake()->unique()->bothify('TEST-####??'), 'product_name' => 'Test product', 'description' => '', 'properties' => [], 'parts' => [], 'extra_data' => []];
    }
}
