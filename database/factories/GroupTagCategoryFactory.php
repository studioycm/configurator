<?php

namespace Database\Factories;

use App\Models\GroupTagCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GroupTagCategory> */
class GroupTagCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['label' => fake()->word(), 'sort_order' => 0];
    }
}
