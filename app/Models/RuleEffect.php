<?php

namespace App\Models;

use Database\Factories\RuleEffectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleEffect extends Model
{
    /** @use HasFactory<RuleEffectFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['rule_id', 'target_configurator_attribute_id', 'kind', 'target_scope', 'display_value'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorRule::class, 'rule_id');
    }

    public function optionReferences(): HasMany
    {
        return $this->hasMany(RuleEffectOption::class, 'effect_id');
    }

    public function targetAttribute(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorAttribute::class, 'target_configurator_attribute_id');
    }
}
