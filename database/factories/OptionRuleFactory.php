<?php

namespace Database\Factories;

use App\Models\ConfigAttribute;
use App\Models\ConfigOption;
use App\Models\ConfigProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class OptionRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'config_profile_id' => ConfigProfile::factory(),
            'config_option_id' => ConfigOption::factory(),
            'target_attribute_id' => ConfigAttribute::factory(),
            'allowed_option_ids' => [],
        ];
    }
}
