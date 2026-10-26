<?php

namespace App\Services;

use App\ConditionOperator;
use App\ConditionSource;
use App\DTO\ConfiguratorAttributeDTO;
use App\DTO\ConfiguratorConditionDTO;
use App\DTO\ConfiguratorConditionGroupDTO;
use App\DTO\ConfiguratorDefinition;
use App\DTO\ConfiguratorEvaluationResult;
use App\DTO\ConfiguratorOptionDTO;
use App\DTO\ConfiguratorRuleDTO;
use App\RuleEffectKind;
use App\RuleTargetScope;

/**
 * @phpstan-type Cause array{category: string, rule_id: ?string, rule: ?string, set_id: ?string, set_label: ?string, priority: ?int, conditions: string, effect: string, effect_kind: ?string, message: string, options: list<string>, additional_options: list<string>, overlapping_options: list<string>, changed_applicability: bool, target: ?array<string, string>, trace_index: ?int}
 * @phpstan-type Presentation array{target: string, field: string, candidates: list<array<string, mixed>>, winner_rule_ids: list<string>, conflict: bool, effective_value: ?string, trace_indices: list<int>}
 * @phpstan-type ValueInspection array{id: string, label: string, hint: ?string, display_value: string, code: string, semantic_id: ?string, legal: ?bool, hidden: bool, selected: bool, state: string, active: bool, lifecycle_hidden: bool, initial_hidden: bool, initial_disabled: bool, display_order: int, causes: list<Cause>, trace_indices: list<int>, presentation: list<Presentation>}
 * @phpstan-type AttributeInspection array{id: string, label: string, hint: ?string, display_value: ?string, input_type: string, applicable: ?bool, hidden: bool, state: string, selected_id: ?string, active: bool, display_order: int, causes: list<Cause>, trace_indices: list<int>, presentation: list<Presentation>, options: array<string, ValueInspection>}
 * @phpstan-type RuleInspection array{id: string, label: string, kind: string, disabled: bool, excluded: bool, outcome: string, category: string, conditions: string, defined_condition: array, priority: int, target: array{type: string, id: string}, trace_indices: list<int>, source_attribute_ids: list<string>, source_option_ids: list<string>, affected_attribute_ids: list<string>}
 * @phpstan-type InspectionReport array{attributes: array<string, AttributeInspection>, rules: list<RuleInspection>, conflicts: list<array<string, mixed>>, diagnostics: list<array<string, mixed>>, trace: list<array<string, mixed>>}
 */
final class ConfiguratorPreviewInspection
{
    /**
     * @param  list<array<string, mixed>>  $excludedRules
     * @return InspectionReport
     */
    public function build(?ConfiguratorEvaluationResult $result, ?ConfiguratorDefinition $compiledDefinition = null, array $excludedRules = []): array
    {
        $definition = $result?->definition ?? $compiledDefinition;
        $report = ['attributes' => [], 'rules' => [], 'conflicts' => [], 'diagnostics' => $result?->diagnostics ?? [], 'trace' => $result?->trace ?? []];
        if ($definition === null) {
            if ($report['diagnostics'] === []) {
                $report['diagnostics'][] = ['code' => 'preview_unavailable', 'message' => __('configurator.preview.unavailable_value')];
            }

            return $report;
        }
        $evaluated = $result?->definition !== null;
        $rules = [];
        $rawRules = [];
        $sets = [];
        foreach ($definition->data['rules'] as $row) {
            $rawRules[$row['id']] = $row;
            foreach ($row['sets'] as $index => $set) {
                $sets[$row['id']][$set['id']] = ($set['label'] !== null && $set['label'] !== '' ? $set['label'] : __('configurator.preview.mapping_set', ['number' => $index + 1]));
            }
        }
        foreach ($definition->rules as $rule) {
            $rules[$rule->id] = $rule;
        }
        $byAttribute = [];
        $byRule = [];
        $byValue = [];
        $restrictions = [];
        $candidates = [];
        $outcomes = [];
        foreach ($report['trace'] as $index => $entry) {
            $attributeId = $entry['attribute_id'] ?? null;
            if ($attributeId === null || ! isset($definition->attributes[$attributeId])) {
                continue;
            }
            $byAttribute[$attributeId][] = $index;
            if (isset($entry['choice_id'])) {
                $byValue[$attributeId][$entry['choice_id']][] = $index;
            }
            foreach ($entry['rule_ids'] ?? (isset($entry['rule_id']) ? [$entry['rule_id']] : []) as $ruleId) {
                $byRule[$ruleId][] = $index;
            }
            if (isset($entry['effect_kind'], $entry['rule_id'], $rules[$entry['rule_id']])) {
                $rule = $rules[$entry['rule_id']];
                $kind = RuleEffectKind::from($entry['effect_kind']);
                $all = array_map('strval', array_keys($definition->attributes[$attributeId]->options));
                $options = $entry['excluded'] ?? match ($kind) {
                    RuleEffectKind::HideAttribute => $all,
                    RuleEffectKind::AllowOptions => array_values(array_diff($all, $entry['options'])),
                    default => $entry['options'],
                };
                foreach ($options as $optionId) {
                    $byValue[$attributeId][$optionId][] = $index;
                }
                $additional = $entry['restricted_options'];
                $conditions = $this->conditionDescription($rule->conditions, $definition, $result);
                $setId = $entry['set_id'] ?? null;
                $setLabel = $sets[$rule->id][$setId] ?? null;
                if ($setId !== null && $rule->driverId !== null) {
                    $driver = $definition->attributes[$rule->driverId];
                    $selected = $result?->selections[$rule->driverId] ?? null;
                    $matched = ($result?->attributes[$rule->driverId]['label'] ?? $driver->label).': '.$this->identity($driver->options[$selected] ?? null).' · '.$setLabel;
                    $conditions = $matched.($rule->conditions->conditions === [] ? '' : ' · '.$conditions);
                }
                $restrictions[$attributeId][] = [
                    'category' => $this->category($rule), 'rule_id' => $rule->id, 'rule' => $rule->label,
                    'set_id' => $setId, 'set_label' => $setLabel, 'priority' => null, 'conditions' => $conditions,
                    'effect' => $kind->getLabel(), 'effect_kind' => $kind->value, 'message' => $rule->label.' · '.$kind->getLabel(),
                    'options' => array_values($options), 'additional_options' => $additional,
                    'overlapping_options' => array_values(array_diff($options, $additional)),
                    'changed_applicability' => $entry['changed_applicability'] ?? false,
                    'target' => ['type' => 'rule', 'id' => $rule->id], 'trace_index' => $index,
                ];
            } elseif (($entry['type'] ?? null) === 'presentation') {
                if (isset($entry['field'])) {
                    $outcomes[$attributeId][$entry['target']][$entry['field']] = [...$entry, 'trace_index' => $index];
                } elseif (isset($entry['rule_id'])) {
                    $candidates[$entry['rule_id']][$attributeId][] = $index;
                }
            }
        }
        $presentation = $this->presentation($definition, $result, $candidates, $outcomes);
        foreach ($definition->rules as $rule) {
            $indices = array_values(array_unique($byRule[$rule->id] ?? []));
            $outcome = 'not_recorded';
            foreach ($indices as $index) {
                $entry = $report['trace'][$index];
                if (isset($entry['effect_kind']) || (isset($entry['field']) && ! $entry['conflict'])) {
                    $outcome = 'recorded_applied';
                    break;
                }
                if (($entry['type'] ?? null) === 'presentation') {
                    $outcome = 'recorded_candidate';
                } elseif ($outcome === 'not_recorded') {
                    $outcome = 'recorded_skipped';
                }
            }
            $report['rules'][] = ['id' => $rule->id, 'label' => $rule->label, 'kind' => $rule->kind->value, 'disabled' => ! $rule->active, 'excluded' => false, 'outcome' => $outcome, 'category' => $this->category($rule), 'conditions' => $this->conditionDescription($rule->conditions, $definition, $result), 'defined_condition' => $rawRules[$rule->id]['conditions'] ?? [], 'priority' => $rule->priority, 'target' => ['type' => 'rule', 'id' => $rule->id], 'trace_indices' => $indices, ...$this->ruleReferences($rawRules[$rule->id])];
        }
        foreach ($excludedRules as $row) {
            $id = (string) $row['id'];
            if (isset($rules[$id])) {
                continue;
            }
            $report['rules'][] = ['id' => $id, 'label' => $row['label'], 'kind' => $row['kind'], 'disabled' => ! $row['is_active'], 'excluded' => true, 'outcome' => 'not_recorded', 'category' => $this->rawCategory($row), 'conditions' => $this->rawConditionDescription($row['conditions'], $definition, $result), 'defined_condition' => $row['conditions'], 'priority' => $row['priority'], 'target' => ['type' => 'rule', 'id' => $id], 'trace_indices' => [], ...$this->ruleReferences($row)];
        }
        foreach ($definition->attributes as $id => $attribute) {
            $id = (string) $id;
            $state = $evaluated ? $result->attributes[$id] : null;
            $applicable = $state['applicable'] ?? null;
            $hidden = $applicable === false;
            $selected = $evaluated ? ($result->selections[$id] ?? null) : null;
            $attributeCauses = $restrictions[$id] ?? [];
            $inherited = array_values(array_filter($attributeCauses, fn (array $cause): bool => $cause['effect_kind'] === RuleEffectKind::HideAttribute->value));
            if (! $attribute->active) {
                $inherited[] = $this->initialCause(__('configurator.preview.canonical_unavailable_reason'), null);
                $attributeCauses[] = $inherited[array_key_last($inherited)];
            }
            $options = [];
            foreach ($attribute->options as $optionId => $option) {
                $optionId = (string) $optionId;
                $optionHidden = $hidden || ($evaluated ? in_array($optionId, $state['hidden'], true) : ($option->hidden || $option->lifecycleHidden));
                $legal = $evaluated ? $applicable && in_array($optionId, $state['legal'], true) : null;
                $causes = $this->initialCauses($id, $option);
                foreach ($inherited as $cause) {
                    $causes[] = $cause;
                }
                foreach ($attributeCauses as $cause) {
                    if ($cause['effect_kind'] !== RuleEffectKind::HideAttribute->value && in_array($optionId, $cause['options'], true)) {
                        $causes[] = $cause;
                    }
                }
                $effective = $state['options'][$optionId] ?? ['label' => $option->label, 'hint' => $option->hint, 'display_value' => $option->displayValue];
                $options[$optionId] = ['id' => $optionId, ...$effective, 'code' => $option->code, 'semantic_id' => $option->semanticId, 'legal' => $legal, 'hidden' => $optionHidden, 'selected' => $legal && $selected === $optionId, 'state' => ! $evaluated ? 'unavailable' : ($optionHidden ? 'hidden' : ($legal ? ($selected === $optionId ? 'selected' : 'available') : 'disabled')), 'active' => $option->active, 'lifecycle_hidden' => $option->lifecycleHidden, 'initial_hidden' => $option->hidden, 'initial_disabled' => $option->disabled, 'display_order' => $option->displayOrder, 'causes' => $causes, 'trace_indices' => $this->valueTraceIndices($byValue[$id][$optionId] ?? [], $presentation[$id][$optionId] ?? []), 'presentation' => $presentation[$id][$optionId] ?? []];
            }
            uasort($options, fn (array $first, array $second): int => [$first['hidden'], $first['display_order'], $first['id']] <=> [$second['hidden'], $second['display_order'], $second['id']]);
            $report['attributes'][$id] = ['id' => $id, 'label' => $state['label'] ?? $attribute->label, 'hint' => $state['hint'] ?? $attribute->help, 'display_value' => $state['display_value'] ?? null, 'input_type' => $attribute->inputType, 'applicable' => $applicable, 'hidden' => $hidden, 'state' => ! $evaluated ? 'unavailable' : ($hidden ? 'hidden' : ($state['legal'] === [] ? 'no_legal_values' : 'available')), 'selected_id' => $selected, 'active' => $attribute->active, 'display_order' => $attribute->displayOrder, 'causes' => $attributeCauses, 'trace_indices' => $byAttribute[$id] ?? [], 'presentation' => $presentation[$id]['attribute'] ?? [], 'options' => $options];
            if ($evaluated && $applicable && $state['legal'] === []) {
                $report['conflicts'][] = $this->conflict($report['attributes'][$id], $attribute);
            }
        }
        uasort($report['attributes'], fn (array $first, array $second): int => [$first['display_order'], $first['id']] <=> [$second['display_order'], $second['id']]);

        return $report;
    }

    /** @return array<string, array<string, list<Presentation>>> */
    private function presentation(ConfiguratorDefinition $definition, ?ConfiguratorEvaluationResult $result, array $recorded, array $outcomes): array
    {
        $groups = [];
        $candidateOffsets = [];
        foreach ($definition->rules as $rule) {
            foreach ($rule->effects as $effect) {
                $field = match ($effect->kind) {
                    RuleEffectKind::SetLabel => 'label', RuleEffectKind::SetDisplayValue => 'display_value', RuleEffectKind::SetHint => 'hint', default => null,
                };
                if ($field === null || ! isset($recorded[$rule->id][$effect->attributeId])) {
                    continue;
                }
                $offset = $candidateOffsets[$rule->id][$effect->attributeId] ?? 0;
                $traceIndex = $recorded[$rule->id][$effect->attributeId][$offset];
                $candidateOffsets[$rule->id][$effect->attributeId] = $offset + 1;
                foreach ($effect->scope === RuleTargetScope::Attribute ? ['attribute'] : $effect->optionIds as $target) {
                    $groups[$effect->attributeId][$target][$field][] = ['rule_id' => $rule->id, 'rule' => $rule->label, 'effect_id' => $effect->id, 'priority' => $rule->priority, 'value' => $effect->value, 'category' => $this->category($rule), 'conditions' => $this->conditionDescription($rule->conditions, $definition, $result), 'target' => ['type' => 'rule', 'id' => $rule->id], 'trace_indices' => [$traceIndex]];
                }
            }
        }
        $projection = [];
        foreach ($groups as $attributeId => $targets) {
            foreach ($targets as $target => $fields) {
                foreach ($fields as $field => $candidates) {
                    $outcome = $outcomes[$attributeId][$target][$field] ?? null;
                    $effective = $target === 'attribute' ? ($result?->attributes[$attributeId][$field] ?? null) : ($result?->attributes[$attributeId]['options'][$target][$field] ?? null);
                    $projection[$attributeId][$target][] = ['target' => (string) $target, 'field' => $field, 'candidates' => $candidates, 'winner_rule_ids' => $outcome['rule_ids'] ?? [], 'conflict' => $outcome['conflict'] ?? false, 'effective_value' => $effective, 'trace_indices' => $this->presentationTraceIndices($candidates, $outcome)];
                }
            }
        }

        return $projection;
    }

    /** @return list<int> */
    private function valueTraceIndices(array $indices, array $presentation): array
    {
        foreach ($presentation as $outcome) {
            $indices = [...$indices, ...$outcome['trace_indices']];
        }
        $indices = array_values(array_unique($indices));
        sort($indices);

        return $indices;
    }

    /** @return list<int> */
    private function presentationTraceIndices(array $candidates, ?array $outcome): array
    {
        $indices = $outcome !== null ? [$outcome['trace_index']] : [];
        foreach ($candidates as $candidate) {
            $indices = [...$indices, ...$candidate['trace_indices']];
        }
        $indices = array_values(array_unique($indices));
        sort($indices);

        return $indices;
    }

    /** @return list<Cause> */
    private function initialCauses(string $attributeId, ConfiguratorOptionDTO $option): array
    {
        $causes = [];
        $target = ['type' => 'value', 'attribute_id' => $attributeId, 'id' => $option->id];
        if ($option->hidden) {
            $causes[] = $this->initialCause(__('configurator.preview.initially_hidden_reason'), $target);
        }
        if ($option->disabled) {
            $causes[] = $this->initialCause(__('configurator.preview.initially_disabled_reason'), $target);
        }
        if (! $option->active || $option->lifecycleHidden) {
            $causes[] = $this->initialCause(__('configurator.preview.canonical_unavailable_reason'), null);
        }

        return $causes;
    }

    /** @return Cause */
    private function initialCause(string $message, ?array $target): array
    {
        return ['category' => 'initial', 'rule_id' => null, 'rule' => null, 'set_id' => null, 'set_label' => null, 'priority' => null, 'conditions' => '', 'effect' => $message, 'effect_kind' => null, 'message' => $message, 'options' => [], 'additional_options' => [], 'overlapping_options' => [], 'changed_applicability' => false, 'target' => $target, 'trace_index' => null];
    }

    /** @return array<string, mixed> */
    private function conflict(array $row, ConfiguratorAttributeDTO $attribute): array
    {
        $causes = [];
        foreach ($row['causes'] as $cause) {
            if ($cause['rule_id'] !== null) {
                $causes[$cause['rule_id']] = ['rule' => $cause['rule'], 'conditions' => $cause['conditions'], 'target' => $cause['target']];
            }
        }
        $values = [];
        foreach ($attribute->options as $option) {
            $valueCauses = array_map(fn (array $cause): array => ['message' => $cause['message'], 'target' => $cause['target']], $row['options'][$option->id]['causes']);
            $values[] = ['value' => $this->identity($option), 'reasons' => array_values(array_unique(array_column($valueCauses, 'message'))), 'causes' => $valueCauses];
        }

        return ['attribute_id' => $row['id'], 'attribute' => $row['label'], 'rules' => array_column($causes, 'rule'), 'causes' => array_values($causes), 'values' => $values];
    }

    private function category(ConfiguratorRuleDTO $rule): string
    {
        $sources = $this->conditionCategories($rule->conditions);
        if ($rule->driverId !== null) {
            $sources[] = 'selection';
        }
        $sources = array_unique($sources);

        return count($sources) > 1 ? 'mixed' : ($sources[0] ?? 'system');
    }

    /** @return list<string> */
    private function conditionCategories(ConfiguratorConditionDTO|ConfiguratorConditionGroupDTO $condition): array
    {
        if ($condition instanceof ConfiguratorConditionDTO) {
            return [match ($condition->source) {
                ConditionSource::SelectionOption, ConditionSource::SelectionCode => 'selection', ConditionSource::ProductProperty => 'product', default => 'context',
            }];
        }
        $sources = [];
        foreach ($condition->conditions as $child) {
            $sources = [...$sources, ...$this->conditionCategories($child)];
        }

        return $sources;
    }

    private function conditionDescription(ConfiguratorConditionDTO|ConfiguratorConditionGroupDTO $condition, ConfiguratorDefinition $definition, ?ConfiguratorEvaluationResult $result): string
    {
        if ($condition instanceof ConfiguratorConditionGroupDTO) {
            if ($condition->conditions === []) {
                return __('configurator.preview.unconditional');
            }

            return $condition->operator->value.' ('.implode('; ', array_map(fn ($child): string => $this->conditionDescription($child, $definition, $result), $condition->conditions)).')';
        }
        if ($condition->attributeId !== null) {
            $attribute = $definition->attributes[$condition->attributeId];
            $field = $result?->attributes[$attribute->id]['label'] ?? $attribute->label;
            $value = implode(', ', array_map(fn (string $id): string => $this->identity($attribute->options[$id]), $condition->optionIds));
        } else {
            $field = $condition->source->getLabel().': '.($condition->propertyKey ?? $condition->contextDimension ?? '');
            $value = implode(', ', (array) $condition->operand);
        }

        return $field.' '.$condition->operator->getLabel().' '.$value;
    }

    /** @return array{source_attribute_ids: list<string>, source_option_ids: list<string>, affected_attribute_ids: list<string>} */
    private function ruleReferences(array $row): array
    {
        $attributes = [];
        $options = [];
        $visit = function (array $conditions) use (&$visit, &$attributes, &$options): void {
            foreach ($conditions as $condition) {
                if (isset($condition['conditions'])) {
                    $visit($condition['conditions']);
                } elseif (($condition['source_configurator_attribute_id'] ?? null) !== null) {
                    $attributes[] = (string) $condition['source_configurator_attribute_id'];
                    $options = [...$options, ...array_map('strval', $condition['option_ids'] ?? [])];
                }
            }
        };
        $visit($row['conditions']);
        if (($row['driver_configurator_attribute_id'] ?? null) !== null) {
            $attributes[] = (string) $row['driver_configurator_attribute_id'];
            foreach ($row['sets'] as $set) {
                $options = [...$options, ...array_map('strval', $set['source_option_ids'])];
            }
        }
        $affected = array_map('strval', array_column($row['effects'], 'target_configurator_attribute_id'));
        if (($row['target_configurator_attribute_id'] ?? null) !== null) {
            $affected[] = (string) $row['target_configurator_attribute_id'];
        }

        return ['source_attribute_ids' => array_values(array_unique($attributes)), 'source_option_ids' => array_values(array_unique($options)), 'affected_attribute_ids' => array_values(array_unique($affected))];
    }

    private function rawCategory(array $row): string
    {
        $categories = [];
        $visit = function (array $conditions) use (&$visit, &$categories): void {
            foreach ($conditions as $condition) {
                if (isset($condition['conditions'])) {
                    $visit($condition['conditions']);
                } else {
                    $categories[] = match ($condition['source_kind']) {
                        'SelectionOption', 'SelectionCode' => 'selection', 'ProductProperty' => 'product', default => 'context',
                    };
                }
            }
        };
        $visit($row['conditions']);
        if (($row['driver_configurator_attribute_id'] ?? null) !== null) {
            $categories[] = 'selection';
        }
        $categories = array_values(array_unique($categories));

        return count($categories) > 1 ? 'mixed' : ($categories[0] ?? 'system');
    }

    private function rawConditionDescription(array $conditions, ConfiguratorDefinition $definition, ?ConfiguratorEvaluationResult $result): string
    {
        if ($conditions === []) {
            return __('configurator.preview.unconditional');
        }
        $descriptions = [];
        foreach ($conditions as $condition) {
            if (isset($condition['conditions'])) {
                $descriptions[] = $condition['operator'].' ('.$this->rawConditionDescription($condition['conditions'], $definition, $result).')';

                continue;
            }
            $attributeId = $condition['source_configurator_attribute_id'] ?? null;
            $attribute = $definition->attributes[$attributeId] ?? null;
            $source = ConditionSource::from($condition['source_kind']);
            $field = $attribute !== null ? ($result?->attributes[$attributeId]['label'] ?? $attribute->label) : $source->getLabel().': '.($condition['property_key'] ?? $condition['context_dimension'] ?? '');
            $value = $attribute !== null ? implode(', ', array_map(fn (string $id): string => $this->identity($attribute->options[$id] ?? null), $condition['option_ids'])) : implode(', ', (array) ($condition['operand'] ?? []));
            $descriptions[] = $field.' '.ConditionOperator::from($condition['operator'])->getLabel().' '.$value;
        }

        return implode('; ', $descriptions);
    }

    private function identity(?ConfiguratorOptionDTO $option): string
    {
        if ($option === null) {
            return __('configurator.preview.unavailable_value');
        }

        return filled($option->semanticId) ? $option->semanticId.' ('.$option->code.')' : $option->code;
    }
}
