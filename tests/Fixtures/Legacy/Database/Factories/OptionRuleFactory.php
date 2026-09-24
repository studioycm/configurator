<?php

namespace Tests\Fixtures\Legacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\Legacy\Models\ConfigAttribute;
use Tests\Fixtures\Legacy\Models\ConfigOption;
use Tests\Fixtures\Legacy\Models\ConfigProfile;

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
