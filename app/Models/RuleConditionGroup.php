<?php

namespace App\Models;

use Database\Factories\RuleConditionGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleConditionGroup extends Model
{
    /** @use HasFactory<RuleConditionGroupFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['rule_id', 'operator', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorRule::class, 'rule_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(RuleCondition::class, 'condition_group_id');
    }
}
