<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attribute> */
class AttributeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['key' => fake()->unique()->lexify('attribute_????????'), 'label' => fake()->words(2, true)];
    }
}
