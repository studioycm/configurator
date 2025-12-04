<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAttachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'catalog_group_id',
        'product_profile_id',
        'product_configuration_id',
        'title',
        'file_path',
        'file_type',
        'mime_type',
        'sort_order',
        'is_primary',
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
            'product_profile_id' => 'integer',
            'product_configuration_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function catalogGroup(): BelongsTo
    {
        return $this->belongsTo(CatalogGroup::class);
    }

    public function productProfile(): BelongsTo
    {
        return $this->belongsTo(ProductProfile::class);
    }

    public function productConfiguration(): BelongsTo
    {
        return $this->belongsTo(ProductConfiguration::class);
    }
}
