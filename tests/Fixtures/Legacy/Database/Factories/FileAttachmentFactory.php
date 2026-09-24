<?php

namespace Tests\Fixtures\Legacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\Legacy\FileAttachmentType;
use Tests\Fixtures\Legacy\Models\CatalogGroup;
use Tests\Fixtures\Legacy\Models\ConfigurationPart;
use Tests\Fixtures\Legacy\Models\Part;
use Tests\Fixtures\Legacy\Models\ProductConfiguration;
use Tests\Fixtures\Legacy\Models\ProductProfile;

class FileAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'attachable_id' => CatalogGroup::factory(),
            'attachable_type' => CatalogGroup::class,
            'title' => fake()->sentence(4),
            'file_path' => fake()->word(),
            'file_type' => fake()->randomElement(FileAttachmentType::cases()),
            'mime_type' => fake()->word(),
            'sort_order' => fake()->numberBetween(-10000, 10000),
            'is_primary' => fake()->boolean(),
        ];
    }

    public function forCatalogGroup(): static
    {
        return $this->state(fn () => [
            'attachable_id' => CatalogGroup::factory(),
            'attachable_type' => CatalogGroup::class,
        ]);
    }

    public function forProductProfile(): static
    {
        return $this->state(fn () => [
            'attachable_id' => ProductProfile::factory(),
            'attachable_type' => ProductProfile::class,
        ]);
    }

    public function forProductConfiguration(): static
    {
        return $this->state(fn () => [
            'attachable_id' => ProductConfiguration::factory(),
            'attachable_type' => ProductConfiguration::class,
        ]);
    }

    public function forPart(): static
    {
        return $this->state(fn () => [
            'attachable_id' => Part::factory(),
            'attachable_type' => Part::class,
        ]);
    }

    public function forConfigurationPart(): static
    {
        return $this->state(fn () => [
            'attachable_id' => ConfigurationPart::factory(),
            'attachable_type' => ConfigurationPart::class,
        ]);
    }
}
