<?php

namespace Database\Factories;

use App\Models\AdminAppearanceSettings;
use App\Services\AdminAppearance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdminAppearanceSettings> */
class AdminAppearanceSettingsFactory extends Factory
{
    public function definition(): array
    {
        return ['settings' => app(AdminAppearance::class)->defaults(), 'version' => 1];
    }
}
