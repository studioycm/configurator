<?php

namespace App\Models;

use Database\Factories\SubGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubGroup extends Model
{
    /** @use HasFactory<SubGroupFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['group_id', 'label', 'property_key', 'allowed_values', 'force_hide', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['group_id' => 'integer', 'sort_order' => 'integer', 'allowed_values' => 'array', 'force_hide' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
