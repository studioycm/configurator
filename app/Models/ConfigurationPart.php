<?php

namespace App\Models;

use App\FileAttachmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;

class ConfigurationPart extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_configuration_id',
        'part_id',
        'part_number',
        'label',
        'material',
        'quantity',
        'unit',
        'segment_index',
        'notes',
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
            'product_configuration_id' => 'integer',
            'part_id' => 'integer',
            'quantity' => 'decimal:3',
        ];
    }

    public function productConfiguration(): BelongsTo
    {
        return $this->belongsTo(ProductConfiguration::class, 'product_configuration_id', 'id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id', 'id');
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

    protected function mainImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->mainImage?->file_path,
        );
    }

    protected function galleryImageUrls(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => ($this->galleryImages ?? collect())
                ->pluck('file_path')
                ->filter()
                ->values(),
        );
    }
}
