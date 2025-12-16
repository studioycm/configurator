<?php

namespace App\Models;

use App\FileAttachmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ProductConfiguration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_profile_id',
        'configuration_code',
        'name',
        'is_active',
        'drawing_image_path',
        'config_data',
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
            'product_profile_id' => 'integer',
            'is_active' => 'boolean',
            'config_data' => 'array',
        ];
    }

    public function productProfile(): BelongsTo
    {
        return $this->belongsTo(ProductProfile::class, 'product_profile_id', 'id');
    }

    public function configurationParts(): HasMany
    {
        return $this->hasMany(ConfigurationPart::class, 'product_configuration_id', 'id');
    }

    public function configurationSpecifications(): HasMany
    {
        return $this->hasMany(ConfigurationSpecification::class, 'product_configuration_id', 'id');
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
