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

    protected $hidden = ['snapshot', 'record_fingerprint', 'error_detail'];

    protected function casts(): array
    {
        return ['record_id' => 'integer', 'position' => 'integer', 'before_asset_id' => 'integer', 'snapshot' => 'array', 'included' => 'boolean'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CatalogAssetBatch::class, 'batch_id');
    }

    public function beforeAsset(): BelongsTo
    {
        return $this->belongsTo(CatalogAsset::class, 'before_asset_id');
    }
}
