<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\Option;
use App\Models\Value;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Option> */
class OptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['attribute_id' => Attribute::factory(), 'value_id' => Value::factory(), 'code' => fake()->unique()->regexify('[A-Za-z0-9]{2}')];
    }
}
