<?php

namespace App\Models;

use Database\Factories\ConfiguratorRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfiguratorRule extends Model
{
    /** @use HasFactory<ConfiguratorRuleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['configurator_id', 'label', 'kind', 'is_active', 'priority', 'driver_configurator_attribute_id', 'target_configurator_attribute_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'priority' => 'integer'];
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }

    public function driverAttribute(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorAttribute::class, 'driver_configurator_attribute_id');
    }

    public function targetAttribute(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorAttribute::class, 'target_configurator_attribute_id');
    }

    public function conditionGroups(): HasMany
    {
        return $this->hasMany(RuleConditionGroup::class, 'rule_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(RuleCondition::class, 'rule_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(RuleEffect::class, 'rule_id');
    }

    public function mappingSets(): HasMany
    {
        return $this->hasMany(MappingSet::class, 'rule_id');
    }

    /** @return array<string, list<string>> */
    public function presentationSummaries(): array
    {
        $this->loadMissing(['effects.targetAttribute.attribute', 'effects.optionReferences.configuratorOption.option.value']);
        $summaries = ['SetLabel' => [], 'SetDisplayValue' => [], 'SetHint' => []];
        foreach ($this->effects->sortBy('id') as $effect) {
            if (! array_key_exists($effect->kind, $summaries)) {
                continue;
            }
            $attribute = $effect->targetAttribute;
            $label = $attribute->label_override ?? $attribute->attribute->label;
            if ($effect->target_scope === 'Attribute') {
                $summaries[$effect->kind][] = $label.' → '.$effect->display_value;
            } else {
                foreach ($effect->optionReferences->sortBy('id') as $reference) {
                    $option = $reference->configuratorOption;
                    $summaries[$effect->kind][] = $label.' — '.($option->label_override ?? $option->option->value->label).' → '.$effect->display_value;
                }
            }
        }

        return $summaries;
    }
}
