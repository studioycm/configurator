<?php

namespace App\Models;

use Database\Factories\CatalogAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CatalogAsset extends Model implements HasMedia
{
    /** @use HasFactory<CatalogAssetFactory> */
    use HasFactory, InteractsWithMedia;

    /** @var list<string> */
    protected $guarded = ['*'];

    /** @var array<string, mixed> */
    protected $attributes = ['preparation_status' => 'preparing'];

    /** @var list<string> */
    protected $hidden = ['source', 'preparation_token', 'verified_metadata', 'error_detail'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_by' => 'integer', 'original_media_id' => 'integer', 'source' => 'array', 'verified_metadata' => 'array', 'preparation_expires_at' => 'datetime', 'prepared_at' => 'datetime'];
    }

    /**
     * Phase 1 retains files for operator-managed cleanup, even when SQL rejects a deletion.
     */
    public function shouldDeletePreservingMedia(): bool
    {
        return true;
    }

    public function registerMediaCollections(): void
    {
        foreach (['preparation-attempts', 'original'] as $collection) {
            $this->addMediaCollection($collection)->useDisk('catalog-assets')->storeConversionsOnDisk('catalog-assets')
                ->acceptsMimeTypes(config('catalog-assets.image.mime_types'));
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        foreach (config('catalog-assets.image.conversion_sizes') as $size) {
            $this->addMediaConversion('image-'.$size)->fit(Fit::Max, $size, $size)
                ->performOnCollections('preparation-attempts', 'original')->nonQueued();
        }
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function originalMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'original_media_id');
    }
}
