<?php

namespace App\Models;

use App\FileAttachmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ProductProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'catalog_group_id',
        'name',
        'product_code',
        'slug',
        'short_label',
        'is_active',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'catalog_group_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function catalogGroup(): BelongsTo
    {
        return $this->belongsTo(CatalogGroup::class, 'catalog_group_id', 'id');
    }

    public function productConfigurations(): HasMany
    {
        return $this->hasMany(ProductConfiguration::class, 'product_profile_id', 'id');
    }

    public function configProfiles(): HasMany
    {
        return $this->hasMany(ConfigProfile::class, 'product_profile_id', 'id');
    }

    public function fileAttachments(): MorphMany
    {
        return $this->morphMany(FileAttachment::class, 'attachable');
    }

    public function mainImage(): MorphOne
    {
        return $this->morphOne(FileAttachment::class, 'attachable')
            ->where('file_type', FileAttachmentType::MainImage)
            ->orderBy('sort_order');
    }

    public function galleryImages(): MorphMany
    {
        return $this->morphMany(FileAttachment::class, 'attachable')
            ->where('file_type', FileAttachmentType::GalleryImage)
            ->orderBy('sort_order');
    }
}
