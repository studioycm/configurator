<?php

namespace App\Models;

use App\FileAttachmentType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FileAttachment extends Model  implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'attachable_id',
        'attachable_type',
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
            'attachable_id' => 'integer',
            'file_type' => FileAttachmentType::class,
            'is_primary' => 'boolean',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function filePath(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolvePublicUrl($value),
        );
    }

    protected function publicUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolvePublicUrl($value),
        );
    }

    protected function mimeType(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->getFirstMedia('default')?->mime_type ?? $value,
        );
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('default')
            ->useDisk(config('media-library.disk_name', 'public'))
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('preview')
            ->fit(Fit::Contain, 300, 300);
    }

    private function resolvePublicUrl(?string $value): ?string
    {
        if ($media = $this->getFirstMedia('default')) {
            return $media->getUrl();
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
