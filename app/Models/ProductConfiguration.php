<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return $this->belongsTo(ProductProfile::class);
    }

    public function configurationParts(): HasMany
    {
        return $this->hasMany(ConfigurationPart::class);
    }

    public function configurationSpecifications(): HasMany
    {
        return $this->hasMany(ConfigurationSpecification::class);
    }

    public function fileAttachments(): HasMany
    {
        return $this->hasMany(FileAttachment::class);
    }
}
