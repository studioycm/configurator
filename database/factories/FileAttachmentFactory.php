<?php

namespace Database\Factories;

use App\Models\CatalogGroup;
use App\Models\ProductConfiguration;
use App\Models\ProductProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class FileAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'catalog_group_id' => CatalogGroup::factory(),
            'product_profile_id' => ProductProfile::factory(),
            'product_configuration_id' => ProductConfiguration::factory(),
            'title' => fake()->sentence(4),
            'file_path' => fake()->word(),
            'file_type' => fake()->word(),
            'mime_type' => fake()->word(),
            'sort_order' => fake()->numberBetween(-10000, 10000),
            'is_primary' => fake()->boolean(),
        ];
    }
}
