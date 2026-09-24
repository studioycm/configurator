<?php

namespace App\Models;

use Database\Factories\RuleConditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleCondition extends Model
{
    /** @use HasFactory<RuleConditionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['rule_id', 'condition_group_id', 'source_kind', 'source_configurator_attribute_id', 'property_key', 'context_dimension', 'operator', 'operand', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['operand' => 'array', 'sort_order' => 'integer'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorRule::class, 'rule_id');
    }

    public function optionReferences(): HasMany
    {
        return $this->hasMany(RuleConditionOption::class, 'condition_id');
    }
}
