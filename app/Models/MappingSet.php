<?php

namespace App\Models;

use Database\Factories\MappingSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MappingSet extends Model
{
    /** @use HasFactory<MappingSetFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['rule_id', 'label', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorRule::class, 'rule_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(MappingSetSource::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(MappingSetTarget::class);
    }
}
