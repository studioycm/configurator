<?php

namespace App\Models;

use Database\Factories\CatalogAssetBatchItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogAssetBatchItem extends Model
{
    /** @use HasFactory<CatalogAssetBatchItemFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected $attributes = ['apply_status' => 'pending', 'attempts' => 0];

    protected $hidden = ['snapshot', 'record_fingerprint', 'error_detail', 'prepared_asset_id', 'processing_token', 'processing_expires_at', 'execution_fingerprint'];

    protected function casts(): array
    {
        return ['prepared_asset_id' => 'integer', 'accepted_preview_version' => 'integer', 'accepted_at' => 'datetime', 'processing_expires_at' => 'datetime', 'attempts' => 'integer', 'applied_at' => 'datetime', 'record_id' => 'integer', 'position' => 'integer', 'before_asset_id' => 'integer', 'snapshot' => 'array', 'included' => 'boolean'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CatalogAssetBatch::class, 'batch_id');
    }

    public function preparedAsset(): BelongsTo
    {
        return $this->belongsTo(CatalogAsset::class, 'prepared_asset_id');
    }

    public function beforeAsset(): BelongsTo
    {
        return $this->belongsTo(CatalogAsset::class, 'before_asset_id');
    }
}
