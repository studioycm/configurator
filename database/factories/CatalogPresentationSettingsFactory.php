<?php

namespace Database\Factories;

use App\Models\CatalogPresentationSettings;
use App\Services\CatalogPresentation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogPresentationSettings>
 */
class CatalogPresentationSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['id' => 1, 'settings' => app(CatalogPresentation::class)->defaults(), 'version' => (string) Str::ulid()];
    }
}
