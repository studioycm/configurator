<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CatalogGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'is_active',
        'sort_order',
        'path',
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
            'parent_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function productProfiles(): HasMany
    {
        return $this->hasMany(ProductProfile::class, 'catalog_group_id', 'id');
    }

    public function fileAttachments(): MorphMany
    {
        return $this->morphMany(FileAttachment::class, 'attachable');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CatalogGroup::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CatalogGroup::class, 'parent_id', 'id');
    }
}
