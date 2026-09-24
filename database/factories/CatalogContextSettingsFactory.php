<?php

namespace Database\Factories;

use App\Models\CatalogContextSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogContextSettings>
 */
class CatalogContextSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'choices' => ['territory' => [], 'application' => []],
        ];
    }
}
