<?php

namespace App\Models;

use App\ConditionSource;
use App\RuleEffectKind;
use Database\Factories\ConfiguratorRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public function isFuturePublicOnly(): bool
    {
        return $this->conditions->contains(fn (RuleCondition $condition): bool => in_array($condition->source_kind, [ConditionSource::Territory->value, ConditionSource::Application->value], true));
    }

    public function workspaceSummary(): string
    {
        $predicates = $this->conditions->whereNull('condition_group_id')->sortBy('sort_order')->map(fn (RuleCondition $condition): string => $this->conditionSummary($condition));
        foreach ($this->conditionGroups->sortBy('sort_order') as $group) {
            $predicates->push('('.$this->conditions->where('condition_group_id', $group->id)->sortBy('sort_order')
                ->map(fn (RuleCondition $condition): string => $this->conditionSummary($condition))->implode($group->operator === 'Any' ? __('configurator.rules.summary.or_join') : __('configurator.rules.summary.and_join')).')');
        }
        $when = $predicates->isEmpty() ? __('configurator.rules.summary.always') : $predicates->implode(__('configurator.rules.summary.and_join'));
        if ($this->kind === 'Mapping') {
            $modes = $this->mappingSets->map(fn (MappingSet $set): string => __($set->disallowed_target_behavior?->value === 'Hide' ? 'configurator.copy.hide' : 'configurator.copy.disable'))->unique()->implode(' / ');
            $then = trans_choice('configurator.rules.summary.mapping', $this->mappingSets->count(), ['driver' => $this->driverAttribute?->label_override ?? $this->driverAttribute?->attribute->label, 'target' => $this->targetAttribute?->label_override ?? $this->targetAttribute?->attribute->label, 'count' => $this->mappingSets->count(), 'modes' => $modes]);
        } else {
            $then = $this->effects->map(function (RuleEffect $effect): string {
                $target = $effect->targetAttribute?->label_override ?? $effect->targetAttribute?->attribute->label;
                $options = $effect->optionReferences->map(fn (RuleEffectOption $reference): string => $reference->configuratorOption->option->code)->implode(', ');

                return (RuleEffectKind::tryFrom($effect->kind)?->getLabel() ?? Str::headline($effect->kind)).' · '.$target.($options === '' ? '' : ' ['.$options.']').(filled($effect->display_value) ? ' → '.$effect->display_value : '');
            })->implode('; ');
        }

        return __('configurator.rules.summary.when_then', ['when' => $when, 'then' => $then]);
    }

    private function conditionSummary(RuleCondition $condition): string
    {
        $source = match ($condition->source_kind) {
            'SelectionOption', 'SelectionCode' => $condition->sourceAttribute?->label_override ?? $condition->sourceAttribute?->attribute->label ?? __('configurator.rules.summary.selection'),
            'ProductProperty' => __('configurator.rules.summary.product_field', ['key' => $condition->property_key]),
            default => ConditionSource::tryFrom($condition->source_kind)?->getLabel() ?? $condition->source_kind,
        };
        $operand = $condition->source_kind === 'SelectionOption'
            ? $condition->optionReferences->map(fn (RuleConditionOption $reference): string => $reference->configuratorOption->option->code)->implode(', ')
            : (is_array($condition->operand) ? implode(', ', $condition->operand) : (string) $condition->operand);

        return $source.' '.__('configurator.rules.summary.operators.'.$condition->operator).' '.$operand;
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
