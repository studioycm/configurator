<?php

namespace Database\Factories;

use App\Models\Value;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Value> */
class ValueFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['label' => fake()->words(2, true), 'description' => null];
    }
}
