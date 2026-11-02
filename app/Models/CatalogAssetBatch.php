<?php

namespace App\Models;

use Database\Factories\CatalogAssetBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogAssetBatch extends Model
{
    /** @use HasFactory<CatalogAssetBatchFactory> */
    use HasFactory, HasUlids;

    protected $guarded = ['*'];

    protected $attributes = ['status' => 'building', 'build_position' => 0, 'build_revision' => 0, 'preview_version' => 1];

    protected $hidden = ['owner_context', 'recipe', 'creation_hash', 'scope_path', 'scope_sha256', 'manifest_path', 'manifest_sha256', 'build_token', 'build_expires_at', 'error_detail'];

    protected function casts(): array
    {
        return ['actor_id' => 'integer', 'owner_context' => 'array', 'recipe' => 'array', 'source_version' => 'integer', 'scope_count' => 'integer', 'build_position' => 'integer', 'build_revision' => 'integer', 'preview_version' => 'integer', 'build_expires_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CatalogAssetImportSource::class, 'source_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CatalogAssetBatchItem::class, 'batch_id');
    }
}
