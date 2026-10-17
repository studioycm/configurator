<?php

namespace App\Services;

use App\ConditionJunction;
use App\ConditionOperator;
use App\ConditionSource;
use App\ConfiguratorIntentType;
use App\DTO\ConfiguratorConditionDTO;
use App\DTO\ConfiguratorConditionGroupDTO;
use App\DTO\ConfiguratorDefinition;
use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorEvaluationResult;
use App\DTO\ConfiguratorOptionDTO;
use App\MappingTargetBehavior;
use App\RuleEffectKind;
use App\RuleKind;
use App\RuleTargetScope;

final class ConfiguratorEngine
{
    public function evaluate(ConfiguratorEvaluationInput $input): ConfiguratorEvaluationResult
    {
        if ($input->definition === null) {
            return new ConfiguratorEvaluationResult(null, [], [], [], ['territory' => ConfiguratorPolicy::UNRESTRICTED_CONTEXT, 'application' => ConfiguratorPolicy::UNRESTRICTED_CONTEXT], $input->diagnostics === [] ? [['code' => 'unassigned', 'message' => __('configurator.messages.this_product_does_not_have_an_assigned')]] : $input->diagnostics, false, null, $input->configuratorId);
        }
        foreach ($input->definition->attributes as $attribute) {
            $default = $attribute->options[$attribute->defaultOptionId];
            if (! $attribute->active || ! $default->active || $default->lifecycleHidden) {
                $inactiveAttribute = ! $attribute->active;

                return new ConfiguratorEvaluationResult(null, [], [], [], ['territory' => ConfiguratorPolicy::UNRESTRICTED_CONTEXT, 'application' => ConfiguratorPolicy::UNRESTRICTED_CONTEXT], [...$input->diagnostics, ['code' => $inactiveAttribute ? 'inactive_attribute' : 'inactive_default', 'message' => $inactiveAttribute ? __('configurator.messages.a_required_attribute_is_disabled_repair_or') : __('configurator.messages.a_stored_default_value_is_disabled_or'), 'attribute_id' => $attribute->id]], false, null, $input->configuratorId);
            }
        }
        $context = [];
        $diagnostics = $input->diagnostics;
        foreach (['territory', 'application'] as $dimension) {
            $choice = $input->context[$dimension] ?? ConfiguratorPolicy::UNRESTRICTED_CONTEXT;
            $choices = [ConfiguratorPolicy::UNRESTRICTED_CONTEXT, ...array_column($input->definition->contextSchema[$dimension], 'value')];
            $context[$dimension] = is_string($choice) && in_array($choice, $choices, true) ? $choice : ConfiguratorPolicy::UNRESTRICTED_CONTEXT;
            if ($context[$dimension] !== $choice) {
                $diagnostics[] = ['code' => 'context_repaired', 'message' => __('configurator.messages.a_context_choice_is_no_longer_available')];
            }
        }
        $baseline = $this->settle($input, $context, $input->selections, $input->remembered, $diagnostics);
        $intent = $input->intent;
        if ($intent->malformed) {
            return $this->diagnostic($baseline, ['code' => 'interaction_rejected', 'message' => __('configurator.messages.the_interaction_was_malformed_current_choices_have')]);
        }
        if ($intent->kind === ConfiguratorIntentType::SelectOption) {
            $attribute = $baseline->attributes[$intent->attributeId] ?? null;
            if ($attribute === null || ! $attribute['applicable'] || ! in_array($intent->optionId, $attribute['legal'], true)) {
                return $this->diagnostic($baseline, ['code' => 'selection_rejected', 'message' => __('configurator.messages.that_value_is_not_currently_available_choices'), 'attribute_id' => $intent->attributeId]);
            }
            $selections = $baseline->selections;
            $selections[$intent->attributeId] = $intent->optionId;

            return $this->settle($input, $context, $selections, $baseline->remembered, $diagnostics);
        }
        if ($intent->kind === ConfiguratorIntentType::ChangeContext) {
            $choices = isset($input->definition->contextSchema[$intent->dimension]) ? [ConfiguratorPolicy::UNRESTRICTED_CONTEXT, ...array_column($input->definition->contextSchema[$intent->dimension], 'value')] : [];
            if (! in_array($intent->choice, $choices, true)) {
                return $this->diagnostic($baseline, ['code' => 'context_rejected', 'message' => __('configurator.messages.that_context_choice_is_not_available_current')]);
            }
            $context[$intent->dimension] = $intent->choice;

            return $this->settle($input, $context, $baseline->selections, $baseline->remembered, $diagnostics);
        }

        return $baseline;
    }

    /** @param array<string, string> $context @param array<string, mixed> $prior @param array<string, mixed> $memory @param list<array<string, mixed>> $diagnostics */
    private function settle(ConfiguratorEvaluationInput $input, array $context, array $prior, array $memory, array $diagnostics): ConfiguratorEvaluationResult
    {
        $definition = $input->definition;
        $states = [];
        $selections = [];
        $remembered = [];
        $trace = [];
        foreach ($definition->evaluationOrder as $id) {
            $attribute = $definition->attributes[$id];
            $all = array_map('strval', array_keys($attribute->options));
            $hidden = array_map('strval', array_keys(array_filter($attribute->options, fn (ConfiguratorOptionDTO $option): bool => $option->hidden || $option->lifecycleHidden)));
            $disabled = array_map('strval', array_keys(array_filter($attribute->options, fn (ConfiguratorOptionDTO $option): bool => $option->disabled || ! $option->active)));
            $legal = array_values(array_diff($all, $hidden, $disabled));
            $applicable = true;
            $contributors = [];
            foreach ($definition->rules as $rule) {
                $affects = $input->trace && ($rule->kind === RuleKind::Mapping ? $rule->targetId === $id
                    : count(array_filter($rule->effects, fn ($effect): bool => $effect->attributeId === $id && ! in_array($effect->kind, [RuleEffectKind::SetLabel, RuleEffectKind::SetDisplayValue, RuleEffectKind::SetHint], true))) > 0);
                if (! $rule->active || ! $this->matches($rule->conditions, $definition, $selections, $input->properties, $context)) {
                    if ($input->trace && $affects) {
                        $trace[] = ['type' => 'rule', 'attribute_id' => $id, 'rule_id' => $rule->id, 'message' => __($rule->active ? 'configurator.trace.conditions_not_matched' : 'configurator.trace.disabled_rule_skipped', ['rule' => $rule->label])];
                    }

                    continue;
                }
                if ($rule->kind === RuleKind::Mapping) {
                    if ($rule->targetId !== $id || ! isset($selections[$rule->driverId])) {
                        continue;
                    }
                    $matchedSet = false;
                    foreach ($rule->sets as $set) {
                        if (in_array($selections[$rule->driverId], $set->sources, true)) {
                            $matchedSet = true;
                            $previousHidden = $hidden;
                            $previousDisabled = $disabled;
                            $previousLegal = $legal;
                            $legal = array_values(array_intersect($legal, $set->targets));
                            if ($set->disallowedTargetBehavior === MappingTargetBehavior::Hide) {
                                $hidden = [...$hidden, ...array_diff($all, $set->targets)];
                            } else {
                                $disabled = [...$disabled, ...array_diff($all, $set->targets)];
                            }
                            $contributors[] = $rule->id;
                            if ($input->trace) {
                                $trace[] = ['type' => 'mapping', 'attribute_id' => $id, 'rule_id' => $rule->id, 'set_id' => $set->id,
                                    'message' => __('configurator.messages.set_disallowed_targets', ['value1' => $rule->label, 'value2' => $set->id, 'value3' => $set->disallowedTargetBehavior->value]),
                                    'excluded' => array_values(array_diff($all, $set->targets)), 'legal' => $legal,
                                    'effect_kind' => $set->disallowedTargetBehavior === MappingTargetBehavior::Hide ? RuleEffectKind::HideOptions->value : RuleEffectKind::DisableOptions->value,
                                    'restricted_options' => array_values(array_diff($previousLegal, $legal)),
                                    'affected_options' => $set->disallowedTargetBehavior === MappingTargetBehavior::Hide ? array_values(array_diff($hidden, $previousHidden)) : array_values(array_diff($disabled, $previousDisabled, $hidden))];
                            }
                            break;
                        }
                    }
                    if ($input->trace && ! $matchedSet) {
                        $trace[] = ['type' => 'mapping', 'attribute_id' => $id, 'rule_id' => $rule->id, 'message' => __('configurator.messages.unmapped_source_no_added_restriction', ['value1' => $rule->label])];
                    }

                    continue;
                }
                foreach ($rule->effects as $effect) {
                    if ($effect->attributeId !== $id) {
                        continue;
                    }
                    $previousHidden = $hidden;
                    $previousDisabled = $disabled;
                    $previousApplicable = $applicable;
                    $previousLegal = $legal;
                    if ($effect->kind === RuleEffectKind::HideAttribute) {
                        $applicable = false;
                        $contributors[] = $rule->id;
                    } elseif ($effect->kind === RuleEffectKind::AllowOptions) {
                        $legal = array_values(array_intersect($legal, $effect->optionIds));
                        $disabled = [...$disabled, ...array_diff($all, $effect->optionIds)];
                        $contributors[] = $rule->id;
                    } elseif (in_array($effect->kind, [RuleEffectKind::ExcludeOptions, RuleEffectKind::HideOptions, RuleEffectKind::DisableOptions], true)) {
                        $legal = array_values(array_diff($legal, $effect->optionIds));
                        $contributors[] = $rule->id;
                        if ($effect->kind === RuleEffectKind::HideOptions) {
                            $hidden = [...$hidden, ...$effect->optionIds];
                        } else {
                            $disabled = [...$disabled, ...$effect->optionIds];
                        }
                    }
                    if ($input->trace && ! in_array($effect->kind, [RuleEffectKind::SetLabel, RuleEffectKind::SetDisplayValue, RuleEffectKind::SetHint], true)) {
                        $trace[] = ['type' => 'effect', 'attribute_id' => $id, 'rule_id' => $rule->id, 'message' => $rule->label.': '.$effect->kind->getLabel(), 'options' => $effect->optionIds,
                            'effect_kind' => $effect->kind->value, 'changed_applicability' => $previousApplicable !== $applicable,
                            'restricted_options' => array_values(array_diff($previousLegal, $legal)),
                            'affected_options' => $effect->kind === RuleEffectKind::HideOptions ? array_values(array_diff($hidden, $previousHidden)) : array_values(array_diff($disabled, $previousDisabled, $hidden))];
                    }
                }
            }
            $current = $this->included($prior[$id] ?? null, $all);
            $rememberedChoice = $this->included($memory[$id] ?? null, $all);
            $optionPresentation = [];
            foreach ($attribute->options as $option) {
                $optionPresentation[$option->id] = ['label' => $option->label, 'display_value' => $option->displayValue, 'hint' => $option->hint];
            }
            $states[$id] = ['applicable' => $applicable, 'legal' => $applicable ? $legal : [], 'hidden' => array_values(array_unique($hidden)), 'disabled' => array_values(array_unique($disabled)), 'label' => $attribute->label, 'display_value' => null, 'hint' => $attribute->help, 'options' => $optionPresentation];
            if (! $applicable) {
                $candidate = $current ?? $rememberedChoice;
                if ($candidate !== null) {
                    $remembered[$id] = $candidate;
                }

                continue;
            }
            $choice = $definition->policy->selection($legal, $current, $rememberedChoice, $attribute->defaultOptionId);
            if ($input->trace) {
                $reason = match (true) {
                    $choice === null => __('configurator.trace.no_legal_choice'),
                    $choice === $current => __('configurator.trace.choice_retained'),
                    $current === null && $choice === $rememberedChoice => __('configurator.trace.choice_restored'),
                    $choice === $attribute->defaultOptionId => __('configurator.trace.default_used'),
                    default => __('configurator.trace.fallback_used'),
                };
                $trace[] = ['type' => 'selection', 'attribute_id' => $id, 'message' => $reason, 'choice_id' => $choice];
            }
            if ($choice !== null) {
                $selections[$id] = $choice;
                if ((is_string($prior[$id] ?? null) || is_int($prior[$id] ?? null)) && (string) $prior[$id] !== $choice) {
                    $diagnostics[] = ['code' => 'selection_adjusted', 'message' => __('configurator.messages.choice_for_was_updated_to_because_the', ['value1' => $attribute->label, 'value2' => $attribute->options[$choice]->label]), 'attribute_id' => $id,
                        'rule_ids' => array_values(array_unique($contributors)), 'previous_choice_id' => (string) $prior[$id], 'choice_id' => $choice];
                }
            } else {
                $diagnostics[] = ['code' => 'no_legal_options', 'message' => __('configurator.messages.no_legal_value_remains_for', ['value1' => $attribute->label]), 'attribute_id' => $id, 'rule_ids' => array_values(array_unique($contributors))];
            }
        }
        $this->presentation($definition, $states, $selections, $input->properties, $context, $diagnostics, $input->trace, $trace);
        $applicable = array_filter($definition->attributes, fn ($attribute): bool => $states[$attribute->id]['applicable']);
        $complete = $applicable !== [] && count($selections) === count($applicable);
        $code = null;
        if ($complete) {
            uasort($applicable, fn ($a, $b): int => [$a->codeOrder, $a->id] <=> [$b->codeOrder, $b->id]);
            $code = implode($definition->policy->codeSeparator, array_map(fn ($attribute): string => $attribute->options[$selections[$attribute->id]]->code, $applicable));
        } elseif ($applicable === []) {
            $diagnostics[] = ['code' => 'unusable_definition', 'message' => __('configurator.messages.this_configurator_has_no_applicable_attributes')];
        }

        return new ConfiguratorEvaluationResult($definition, $states, $selections, $remembered, $context, $diagnostics, $complete, $code, $input->configuratorId, $trace);
    }

    /** @param array<string, string> $selections @param array<string, mixed> $properties @param array<string, string> $context */
    private function matches(ConfiguratorConditionDTO|ConfiguratorConditionGroupDTO $condition, ConfiguratorDefinition $definition, array $selections, array $properties, array $context): bool
    {
        if ($condition instanceof ConfiguratorConditionGroupDTO) {
            foreach ($condition->conditions as $child) {
                $matches = $this->matches($child, $definition, $selections, $properties, $context);
                if ($condition->operator === ConditionJunction::All && ! $matches) {
                    return false;
                }
                if ($condition->operator === ConditionJunction::Any && $matches) {
                    return true;
                }
            }

            return $condition->operator === ConditionJunction::All;
        }
        $operand = $condition->operand;
        if (in_array($condition->source, [ConditionSource::SelectionOption, ConditionSource::SelectionCode], true)) {
            $selected = $selections[$condition->attributeId] ?? null;
            if ($selected === null) {
                return false;
            }
            $value = $condition->source === ConditionSource::SelectionCode ? $definition->attributes[$condition->attributeId]->options[$selected]->code : $selected;
            $operands = $condition->source === ConditionSource::SelectionCode ? array_map(fn (string $id): string => $definition->attributes[$condition->attributeId]->options[$id]->code, $condition->optionIds) : $condition->optionIds;
            $operand = in_array($condition->operator, [ConditionOperator::In, ConditionOperator::NotIn], true) ? $operands : $operands[0];
        } elseif ($condition->source === ConditionSource::ProductProperty) {
            $value = $properties[$condition->propertyKey] ?? null;
            if (! is_string($value)) {
                return false;
            }
        } else {
            $value = $context[$condition->contextDimension] ?? ConfiguratorPolicy::UNRESTRICTED_CONTEXT;
            if ($value === ConfiguratorPolicy::UNRESTRICTED_CONTEXT) {
                return false;
            }
        }

        return match ($condition->operator) {
            ConditionOperator::Equals => $value === $operand,
            ConditionOperator::NotEquals => $value !== $operand,
            ConditionOperator::In => in_array($value, $operand, true),
            ConditionOperator::NotIn => ! in_array($value, $operand, true),
            ConditionOperator::Contains => str_contains($value, $operand),
        };
    }

    /** @param array<string, array<string, mixed>> $states @param array<string, string> $selections @param array<string, mixed> $properties @param array<string, string> $context @param list<array<string, mixed>> $diagnostics */
    private function presentation(ConfiguratorDefinition $definition, array &$states, array $selections, array $properties, array $context, array &$diagnostics, bool $traceEnabled, array &$trace): void
    {
        $outcomes = [];
        foreach ($definition->rules as $rule) {
            if (! $rule->active || ! $this->matches($rule->conditions, $definition, $selections, $properties, $context)) {
                continue;
            }
            foreach ($rule->effects as $effect) {
                $field = match ($effect->kind) {
                    RuleEffectKind::SetLabel => 'label', RuleEffectKind::SetDisplayValue => 'display_value', RuleEffectKind::SetHint => 'hint', default => null
                };
                if ($field === null) {
                    continue;
                }
                if ($traceEnabled) {
                    $trace[] = ['type' => 'presentation', 'attribute_id' => $effect->attributeId, 'rule_id' => $rule->id,
                        'message' => __('configurator.messages.candidate_at_priority', ['value1' => $rule->label, 'value2' => $effect->kind->getLabel(), 'value3' => $rule->priority])];
                }
                foreach ($effect->scope === RuleTargetScope::Attribute ? ['attribute'] : $effect->optionIds as $target) {
                    $outcomes[$effect->attributeId][$target][$field][] = ['priority' => $rule->priority, 'value' => $effect->value, 'rule' => $rule->id];
                }
            }
        }
        foreach ($outcomes as $attributeId => $targets) {
            foreach ($targets as $target => $fields) {
                foreach ($fields as $field => $values) {
                    $priority = max(array_column($values, 'priority'));
                    $winners = array_values(array_filter($values, fn (array $value): bool => $value['priority'] === $priority));
                    if (count(array_unique(array_column($winners, 'value'), SORT_STRING)) > 1) {
                        $diagnostics[] = ['code' => 'presentation_priority_tie', 'message' => __('configurator.messages.conflicting_presentation_rules_have_the_same_priority', ['value1' => $field]), 'attribute_id' => (string) $attributeId, 'rule_ids' => array_column($winners, 'rule')];
                    } elseif ($target === 'attribute') {
                        $states[$attributeId][$field] = $winners[0]['value'];
                    } else {
                        $states[$attributeId]['options'][$target][$field] = $winners[0]['value'];
                    }
                    if ($traceEnabled) {
                        $trace[] = ['type' => 'presentation', 'attribute_id' => (string) $attributeId,
                            'message' => count(array_unique(array_column($winners, 'value'), SORT_STRING)) > 1 ? __('configurator.messages.presentation_tie_base_retained', ['value1' => $field]) : __('configurator.messages.presentation_from_rule', ['value1' => $field, 'value2' => implode(', ', array_column($winners, 'rule'))]),
                            'rule_ids' => array_column($winners, 'rule'), 'field' => $field, 'target' => $target,
                            'value' => $winners[0]['value'], 'conflict' => count(array_unique(array_column($winners, 'value'), SORT_STRING)) > 1];
                    }
                }
            }
        }
    }

    /** @param list<string> $options */
    private function included(mixed $value, array $options): ?string
    {
        return (is_string($value) || is_int($value)) && in_array((string) $value, $options, true) ? (string) $value : null;
    }

    /** @param array<string, mixed> $diagnostic */
    private function diagnostic(ConfiguratorEvaluationResult $result, array $diagnostic): ConfiguratorEvaluationResult
    {
        return new ConfiguratorEvaluationResult($result->definition, $result->attributes, $result->selections, $result->remembered, $result->context, [...$result->diagnostics, $diagnostic], $result->isComplete, $result->configurationCode, $result->configuratorId, $result->trace);
    }
}
