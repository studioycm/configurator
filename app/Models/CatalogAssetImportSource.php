<?php

namespace App\Models;

use Database\Factories\CatalogAssetImportSourceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogAssetImportSource extends Model
{
    /** @use HasFactory<CatalogAssetImportSourceFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $guarded = ['*'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'uploading', 'scan_version' => 1, 'scan_revision' => 0, 'cursor' => '[]', 'upload_ledger' => '[]', 'traversal_complete' => false, 'uploads_sealed' => false, 'unsettled_uploads' => 0, 'file_count' => 0, 'byte_count' => 0, 'visited_nodes' => 0];

    /** @var list<string> */
    protected $hidden = ['source', 'cursor', 'upload_ledger', 'scan_token', 'manifest_path', 'manifest_sha256', 'error_detail'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['actor_id' => 'integer', 'source' => 'array', 'cursor' => 'array', 'upload_ledger' => 'array', 'scan_version' => 'integer', 'scan_revision' => 'integer', 'traversal_complete' => 'boolean', 'uploads_sealed' => 'boolean', 'unsettled_uploads' => 'integer', 'file_count' => 'integer', 'byte_count' => 'integer', 'visited_nodes' => 'integer', 'scan_expires_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
