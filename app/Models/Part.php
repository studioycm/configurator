<?php

namespace App\Models;

use App\FileAttachmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Part extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'default_material',
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    public function configurationParts(): HasMany
    {
        return $this->hasMany(ConfigurationPart::class, 'part_id', 'id');
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
