<?php

namespace Database\Factories;

use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GroupTagValue> */
class GroupTagValueFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['group_tag_category_id' => GroupTagCategory::factory(), 'label' => fake()->word(), 'sort_order' => 0];
    }
}
