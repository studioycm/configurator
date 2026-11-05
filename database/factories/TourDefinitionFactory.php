<?php

namespace Database\Factories;

use App\Models\TourDefinition;
use App\Services\GuidedTours;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourDefinition> */
class TourDefinitionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return array_replace(GuidedTours::starter(), ['key' => fake()->unique()->slug()]);
    }
}
