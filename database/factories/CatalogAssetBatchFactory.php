<?php

namespace Database\Factories;

use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetImportSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CatalogAssetBatch> */
class CatalogAssetBatchFactory extends Factory
{
    public function definition(): array
    {
        return ['actor_id' => null, 'target_type' => 'product', 'role' => 'main_image', 'operation' => 'folder_matches', 'owner_context' => [],
            'recipe' => ['parameters' => [], 'mode' => 'fill_missing', 'review_selection' => []], 'creation_hash' => str_repeat('a', 64),
            'source_id' => CatalogAssetImportSource::factory(), 'source_version' => 1, 'scope_path' => 'test/scope.json', 'scope_sha256' => str_repeat('b', 64), 'scope_count' => 1,
            'manifest_path' => 'test/manifest.json', 'manifest_sha256' => str_repeat('c', 64)];
    }
}
