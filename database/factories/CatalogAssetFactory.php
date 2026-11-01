<?php

namespace Database\Factories;

use App\Models\CatalogAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CatalogAsset> */
class CatalogAssetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'created_by' => null,
            'source_kind' => 'upload',
            'source' => ['entry_key' => (string) Str::ulid(), 'filename' => 'fixture.jpg'],
            'sha256' => hash('sha256', fake()->uuid()),
            'import_key' => hash('sha256', fake()->uuid()),
        ];
    }
}
