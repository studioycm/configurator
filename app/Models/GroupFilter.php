<?php

namespace App\Models;

use Database\Factories\GroupFilterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupFilter extends Model
{
    /** @use HasFactory<GroupFilterFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['group_id', 'property_key', 'label', 'sort_order', 'value_order', 'value_labels'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['group_id' => 'integer', 'sort_order' => 'integer', 'value_order' => 'array', 'value_labels' => 'array'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
