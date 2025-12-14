<?php

namespace App\Models;

use App\FileAttachmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FileAttachment extends Model
{
    use HasFactory;

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
}
