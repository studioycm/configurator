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
use App\RuleEffectKind;
use App\RuleKind;
use App\RuleTargetScope;

final class ConfiguratorEngine
{
    public function evaluate(ConfiguratorEvaluationInput $input): ConfiguratorEvaluationResult
    {
        if ($input->definition === null) {
            return new ConfiguratorEvaluationResult(null, [], [], [], ['territory' => ConfiguratorPolicy::UNRESTRICTED_CONTEXT, 'application' => ConfiguratorPolicy::UNRESTRICTED_CONTEXT], $input->diagnostics === [] ? [['code' => 'unassigned', 'message' => 'This Product does not have an assigned Configurator.']] : $input->diagnostics, false, null, $input->configuratorId);
        }
        $context = [];
        $diagnostics = $input->diagnostics;
        foreach (['territory', 'application'] as $dimension) {
            $choice = $input->context[$dimension] ?? ConfiguratorPolicy::UNRESTRICTED_CONTEXT;
            $choices = [ConfiguratorPolicy::UNRESTRICTED_CONTEXT, ...array_column($input->definition->contextSchema[$dimension], 'value')];
            $context[$dimension] = is_string($choice) && in_array($choice, $choices, true) ? $choice : ConfiguratorPolicy::UNRESTRICTED_CONTEXT;
            if ($context[$dimension] !== $choice) {
                $diagnostics[] = ['code' => 'context_repaired', 'message' => 'A context choice is no longer available. Unrestricted context was restored.'];
            }
        }
        $baseline = $this->settle($input, $context, $input->selections, $input->remembered, $diagnostics);
        $intent = $input->intent;
        if ($intent->malformed) {
            return $this->diagnostic($baseline, ['code' => 'interaction_rejected', 'message' => 'The interaction was malformed. Current choices have been refreshed.']);
        }
        if ($intent->kind === ConfiguratorIntentType::SelectOption) {
            $attribute = $baseline->attributes[$intent->attributeId] ?? null;
            if ($attribute === null || ! $attribute['applicable'] || ! in_array($intent->optionId, $attribute['legal'], true)) {
                return $this->diagnostic($baseline, ['code' => 'selection_rejected', 'message' => 'That Option is not currently available. Choices have been refreshed.', 'attribute_id' => $intent->attributeId]);
            }
            $selections = $baseline->selections;
            $selections[$intent->attributeId] = $intent->optionId;

            return $this->settle($input, $context, $selections, $baseline->remembered, $diagnostics);
        }
        if ($intent->kind === ConfiguratorIntentType::ChangeContext) {
            $choices = isset($input->definition->contextSchema[$intent->dimension]) ? [ConfiguratorPolicy::UNRESTRICTED_CONTEXT, ...array_column($input->definition->contextSchema[$intent->dimension], 'value')] : [];
            if (! in_array($intent->choice, $choices, true)) {
                return $this->diagnostic($baseline, ['code' => 'context_rejected', 'message' => 'That context choice is not available. Current choices have been refreshed.']);
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
        foreach ($definition->evaluationOrder as $id) {
            $attribute = $definition->attributes[$id];
            $all = array_map('strval', array_keys($attribute->options));
            $hidden = array_map('strval', array_keys(array_filter($attribute->options, fn (ConfiguratorOptionDTO $option): bool => $option->hidden)));
            $disabled = array_map('strval', array_keys(array_filter($attribute->options, fn (ConfiguratorOptionDTO $option): bool => $option->disabled)));
            $legal = array_values(array_diff($all, $hidden, $disabled));
            $applicable = true;
            $contributors = [];
            foreach ($definition->rules as $rule) {
                if (! $rule->active || ! $this->matches($rule->conditions, $definition, $selections, $input->properties, $context)) {
                    continue;
                }
                if ($rule->kind === RuleKind::Mapping) {
                    if ($rule->targetId !== $id || ! isset($selections[$rule->driverId])) {
                        continue;
                    }
                    foreach ($rule->sets as $set) {
                        if (in_array($selections[$rule->driverId], $set->sources, true)) {
                            $legal = array_values(array_intersect($legal, $set->targets));
                            $disabled = [...$disabled, ...array_diff($all, $set->targets)];
                            $contributors[] = $rule->id;
                            break;
                        }
                    }

                    continue;
                }
                foreach ($rule->effects as $effect) {
                    if ($effect->attributeId !== $id) {
                        continue;
                    }
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
            if ($choice !== null) {
                $selections[$id] = $choice;
                if ((is_string($prior[$id] ?? null) || is_int($prior[$id] ?? null)) && (string) $prior[$id] !== $choice) {
                    $diagnostics[] = ['code' => 'selection_adjusted', 'message' => 'Choice for '.$attribute->label.' was updated to '.$attribute->options[$choice]->label.' because the previous choice is unavailable.', 'attribute_id' => $id];
                }
            } else {
                $diagnostics[] = ['code' => 'no_legal_options', 'message' => 'No legal Option remains for '.$attribute->label.'.', 'attribute_id' => $id, 'rule_ids' => array_values(array_unique($contributors))];
            }
        }
        $this->presentation($definition, $states, $selections, $input->properties, $context, $diagnostics);
        $applicable = array_filter($definition->attributes, fn ($attribute): bool => $states[$attribute->id]['applicable']);
        $complete = $applicable !== [] && count($selections) === count($applicable);
        $code = null;
        if ($complete) {
            uasort($applicable, fn ($a, $b): int => [$a->codeOrder, $a->id] <=> [$b->codeOrder, $b->id]);
            $code = implode($definition->policy->codeSeparator, array_map(fn ($attribute): string => $attribute->options[$selections[$attribute->id]]->code, $applicable));
        } elseif ($applicable === []) {
            $diagnostics[] = ['code' => 'unusable_definition', 'message' => 'This Configurator has no applicable Attributes.'];
        }

        return new ConfiguratorEvaluationResult($definition, $states, $selections, $remembered, $context, $diagnostics, $complete, $code, $input->configuratorId);
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
    private function presentation(ConfiguratorDefinition $definition, array &$states, array $selections, array $properties, array $context, array &$diagnostics): void
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
                        $diagnostics[] = ['code' => 'presentation_priority_tie', 'message' => 'Conflicting presentation rules have the same priority; the base '.$field.' is shown.', 'attribute_id' => (string) $attributeId, 'rule_ids' => array_column($winners, 'rule')];
                    } elseif ($target === 'attribute') {
                        $states[$attributeId][$field] = $winners[0]['value'];
                    } else {
                        $states[$attributeId]['options'][$target][$field] = $winners[0]['value'];
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
        return new ConfiguratorEvaluationResult($result->definition, $result->attributes, $result->selections, $result->remembered, $result->context, [...$result->diagnostics, $diagnostic], $result->isComplete, $result->configurationCode, $result->configuratorId);
    }
}
