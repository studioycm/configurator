<?php

namespace Database\Factories;

use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetBatchItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CatalogAssetBatchItem> */
class CatalogAssetBatchItemFactory extends Factory
{
    public function definition(): array
    {
        return ['batch_id' => CatalogAssetBatch::factory(), 'record_id' => 1, 'position' => 0, 'record_fingerprint' => null, 'snapshot' => ['title' => 'Unavailable record', 'reason' => 'record_unavailable'], 'match_status' => 'invalid', 'included' => false];
    }
}
