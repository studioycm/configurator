<?php

namespace Database\Factories;

use App\Models\CatalogAssetImportSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CatalogAssetImportSource> */
class CatalogAssetImportSourceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'actor_id' => null,
            'source_kind' => 'upload',
            'source' => ['label' => 'Isolated fixture', 'target_key' => 'product.main_image'],
        ];
    }
}
