<?php

namespace App\Services;

use App\ConditionSource;
use App\DTO\ConfiguratorAttributeDTO;
use App\DTO\ConfiguratorConditionDTO;
use App\DTO\ConfiguratorConditionGroupDTO;
use App\DTO\ConfiguratorEvaluationResult;
use App\DTO\ConfiguratorOptionDTO;
use App\RuleEffectKind;

/**
 * @phpstan-type EffectNote array{attribute: string, message: string, text: string, values: list<string>, sources: list<string>, rule: string}
 * @phpstan-type PreviewReport array{effects: list<EffectNote>, warnings: list<array<string, mixed>>, diagnostics: list<array<string, mixed>>, selected_values: list<array{attribute: string, value: string}>, conflicts: list<array<string, mixed>>}
 */
class ConfiguratorPreviewNotes
{
    /** @return PreviewReport */
    public function build(?ConfiguratorEvaluationResult $result): array
    {
        $report = ['effects' => [], 'warnings' => [], 'diagnostics' => [], 'selected_values' => [], 'conflicts' => []];
        if ($result === null) {
            return $report;
        }
        if ($result->definition === null) {
            $report['diagnostics'] = $result->diagnostics;

            return $report;
        }
        $selectionRules = [];
        foreach ($result->definition->rules as $rule) {
            $sources = $this->conditionSources($rule->conditions);
            if ($sources === null) {
                continue;
            }
            if ($rule->driverId !== null) {
                $sources[] = $rule->driverId;
            }
            if ($sources !== []) {
                $selectionRules[$rule->id] = ['rule' => $rule, 'sources' => array_values(array_unique($sources))];
            }
        }
        $systemRestrictions = [];
        foreach ($result->trace as $entry) {
            if (! isset($entry['effect_kind'], $entry['rule_id']) || isset($selectionRules[$entry['rule_id']])) {
                continue;
            }
            $kind = RuleEffectKind::from($entry['effect_kind']);
            $attributeId = $entry['attribute_id'];
            if ($kind === RuleEffectKind::HideAttribute) {
                $systemRestrictions[$attributeId]['attribute_hidden'] = true;

                continue;
            }
            $options = $entry['excluded'] ?? ($kind === RuleEffectKind::AllowOptions ? array_diff(array_keys($result->definition->attributes[$attributeId]->options), $entry['options']) : $entry['options']);
            $field = $kind === RuleEffectKind::HideOptions ? 'hidden' : 'unavailable';
            $systemRestrictions[$attributeId][$field] = [...($systemRestrictions[$attributeId][$field] ?? []), ...$options];
        }
        $restrictionCauses = [];
        foreach ($result->trace as $entry) {
            if (isset($entry['effect_kind'], $entry['rule_id']) && $entry['restricted_options'] !== []) {
                $restrictionCauses[$entry['attribute_id']][] = ['rule_id' => $entry['rule_id'], 'options' => $entry['restricted_options']];
            }
            $ruleIds = $entry['rule_ids'] ?? (isset($entry['rule_id']) ? [$entry['rule_id']] : []);
            if ($ruleIds === [] || array_diff($ruleIds, array_keys($selectionRules)) !== []) {
                continue;
            }
            $attributeId = $entry['attribute_id'];
            $attribute = $result->definition->attributes[$attributeId];
            $values = [];
            $text = '';
            if (isset($entry['field'])) {
                if ($entry['conflict'] || ! $result->attributes[$attributeId]['applicable']
                    || ($entry['target'] !== 'attribute' && ($result->selections[$attributeId] ?? null) !== $entry['target'])) {
                    continue;
                }
                $message = __('configurator.preview.selection_presentation_'.$entry['field']);
                $text = (string) $entry['value'];
                if ($entry['target'] !== 'attribute') {
                    $values[] = $this->identity($attribute->options[$entry['target']]);
                }
            } elseif (isset($entry['effect_kind'])) {
                $kind = RuleEffectKind::from($entry['effect_kind']);
                if ($kind === RuleEffectKind::HideAttribute) {
                    if (! ($entry['changed_applicability'] ?? false) || ($systemRestrictions[$attributeId]['attribute_hidden'] ?? false)) {
                        continue;
                    }
                    $message = __('configurator.preview.selection_attribute_hidden');
                } else {
                    $system = $systemRestrictions[$attributeId] ?? [];
                    $overlapping = $kind === RuleEffectKind::HideOptions ? ($system['hidden'] ?? []) : [...($system['hidden'] ?? []), ...($system['unavailable'] ?? [])];
                    $affected = array_values(array_diff($entry['affected_options'], $overlapping));
                    if ($affected === [] || ! $result->attributes[$attributeId]['applicable']) {
                        continue;
                    }
                    $message = __($kind === RuleEffectKind::HideOptions ? 'configurator.preview.selection_values_hidden' : 'configurator.preview.selection_values_unavailable');
                    $values = array_map(fn (string $id): string => $this->identity($attribute->options[$id]), $affected);
                }
            } else {
                continue;
            }
            $sources = [];
            $labels = [];
            foreach ($ruleIds as $ruleId) {
                $labels[] = $selectionRules[$ruleId]['rule']->label;
                foreach ($selectionRules[$ruleId]['sources'] as $sourceId) {
                    if (isset($result->selections[$sourceId])) {
                        $sources[$sourceId] = $result->attributes[$sourceId]['label'].': '.$this->identity($result->definition->attributes[$sourceId]->options[$result->selections[$sourceId]]);
                    }
                }
            }
            $report['effects'][] = ['attribute' => $result->attributes[$attributeId]['label'], 'message' => $message, 'text' => $text, 'values' => $values, 'sources' => array_values($sources), 'rule' => implode(' · ', $labels)];
        }
        foreach ($result->diagnostics as $diagnostic) {
            $causes = $restrictionCauses[$diagnostic['attribute_id'] ?? ''] ?? [];
            if ($diagnostic['code'] === 'selection_adjusted') {
                $causes = array_filter($causes, fn (array $cause): bool => in_array($diagnostic['previous_choice_id'], $cause['options'], true));
            }
            $ruleIds = array_column($causes, 'rule_id');
            $attribute = $result->definition->attributes[$diagnostic['attribute_id'] ?? ''] ?? null;
            $baseLegal = $attribute === null ? [] : array_keys(array_filter($attribute->options, fn (ConfiguratorOptionDTO $option): bool => ! $option->hidden && ! $option->lifecycleHidden && ! $option->disabled && $option->active));
            $relevantOptions = $diagnostic['code'] === 'selection_adjusted' ? [$diagnostic['previous_choice_id']] : $baseLegal;
            $system = $systemRestrictions[$diagnostic['attribute_id'] ?? ''] ?? [];
            $hasSystemCause = array_intersect($relevantOptions, [...($system['hidden'] ?? []), ...($system['unavailable'] ?? [])]) !== [];
            $selectionDriven = in_array($diagnostic['code'], ['selection_rejected', 'interaction_rejected', 'selection_adjusted'], true)
                || (in_array($diagnostic['code'], ['selection_adjusted', 'no_legal_options'], true) && $ruleIds !== [] && ! $hasSystemCause && array_diff($ruleIds, array_keys($selectionRules)) === []);
            if (! $selectionDriven) {
                $report['diagnostics'][] = $diagnostic;

                continue;
            }
            $report['warnings'][] = $diagnostic;
        }
        $attributes = $result->definition->attributes;
        uasort($attributes, fn (ConfiguratorAttributeDTO $first, ConfiguratorAttributeDTO $second): int => [$first->displayOrder, $first->id] <=> [$second->displayOrder, $second->id]);
        foreach ($attributes as $id => $attribute) {
            if (isset($result->selections[$id])) {
                $report['selected_values'][] = ['attribute' => $result->attributes[$id]['label'], 'value' => $this->identity($attribute->options[$result->selections[$id]])];
            }
            if ($result->attributes[$id]['applicable'] && $result->attributes[$id]['legal'] === []) {
                $report['conflicts'][] = $this->conflict($result, $id);
            }
        }

        return $report;
    }

    /** @return array<string, mixed> */
    private function conflict(ConfiguratorEvaluationResult $result, string $attributeId): array
    {
        $attribute = $result->definition->attributes[$attributeId];
        $causes = [];
        $values = [];
        $rules = [];
        foreach ($result->definition->rules as $rule) {
            $rules[$rule->id] = $rule;
        }
        foreach ($attribute->options as $id => $option) {
            $reasons = [];
            $valueCauses = [];
            $valueTarget = ['type' => 'value', 'attribute_id' => $attributeId, 'id' => (string) $id];
            if ($option->hidden) {
                $reasons[] = __('configurator.preview.initially_hidden_reason');
                $valueCauses[] = ['message' => __('configurator.preview.initially_hidden_reason'), 'target' => $valueTarget];
            }
            if ($option->disabled) {
                $reasons[] = __('configurator.preview.initially_disabled_reason');
                $valueCauses[] = ['message' => __('configurator.preview.initially_disabled_reason'), 'target' => $valueTarget];
            }
            if (! $option->active || $option->lifecycleHidden) {
                $reasons[] = __('configurator.preview.canonical_unavailable_reason');
                $valueCauses[] = ['message' => __('configurator.preview.canonical_unavailable_reason'), 'target' => null];
            }
            foreach ($result->trace as $entry) {
                if (($entry['attribute_id'] ?? null) !== $attributeId || ! isset($entry['effect_kind'], $entry['rule_id'])) {
                    continue;
                }
                $kind = RuleEffectKind::from($entry['effect_kind']);
                $restricted = $entry['excluded'] ?? ($kind === RuleEffectKind::AllowOptions ? array_diff(array_keys($attribute->options), $entry['options']) : $entry['options']);
                if (! in_array((string) $id, array_map('strval', $restricted), true)) {
                    continue;
                }
                $rule = $rules[$entry['rule_id']];
                $reasons[] = $rule->label.' · '.$kind->getLabel();
                $target = ['type' => 'rule', 'id' => $rule->id];
                $valueCauses[] = ['message' => $rule->label.' · '.$kind->getLabel(), 'target' => $target];
                if (isset($causes[$rule->id])) {
                    continue;
                }
                $conditions = $this->conditionDescription($rule->conditions, $result);
                if (isset($entry['set_id']) && $rule->driverId !== null) {
                    $driver = $result->definition->attributes[$rule->driverId];
                    $setLabel = '';
                    foreach ($result->definition->data['rules'] as $row) {
                        if ($row['id'] !== $rule->id) {
                            continue;
                        }
                        foreach ($row['sets'] as $index => $set) {
                            if ($set['id'] === $entry['set_id']) {
                                $setLabel = $set['label'] ?: __('configurator.preview.mapping_set', ['number' => $index + 1]);
                            }
                        }
                    }
                    $matched = $result->attributes[$rule->driverId]['label'].': '.$this->identity($driver->options[$result->selections[$rule->driverId]]).' · '.$setLabel;
                    $conditions = $matched.($conditions === __('configurator.preview.unconditional') ? '' : ' · '.$conditions);
                }
                $causes[$rule->id] = ['rule' => $rule->label, 'conditions' => $conditions, 'target' => $target];
            }
            $values[] = ['value' => $this->identity($option), 'reasons' => array_values(array_unique($reasons)), 'causes' => $valueCauses];
        }

        return ['attribute_id' => $attributeId, 'attribute' => $result->attributes[$attributeId]['label'], 'rules' => array_column($causes, 'rule'), 'causes' => array_values($causes), 'values' => $values];
    }

    private function conditionDescription(ConfiguratorConditionDTO|ConfiguratorConditionGroupDTO $condition, ConfiguratorEvaluationResult $result): string
    {
        if ($condition instanceof ConfiguratorConditionGroupDTO) {
            if ($condition->conditions === []) {
                return __('configurator.preview.unconditional');
            }

            return $condition->operator->value.' ('.implode('; ', array_map(fn ($child): string => $this->conditionDescription($child, $result), $condition->conditions)).')';
        }
        if ($condition->attributeId !== null) {
            $attribute = $result->definition->attributes[$condition->attributeId];
            $field = $result->attributes[$attribute->id]['label'];
            $value = implode(', ', array_map(fn (string $id): string => $this->identity($attribute->options[$id]), $condition->optionIds));
        } else {
            $field = $condition->source->getLabel().': '.str_replace('_', ' ', $condition->propertyKey ?? $condition->contextDimension ?? '');
            $value = implode(', ', (array) $condition->operand);
        }

        return $field.' '.$condition->operator->getLabel().' '.$value;
    }

    /** @return list<string>|null */
    private function conditionSources(ConfiguratorConditionDTO|ConfiguratorConditionGroupDTO $condition): ?array
    {
        if ($condition instanceof ConfiguratorConditionDTO) {
            return $condition->attributeId !== null && in_array($condition->source, [ConditionSource::SelectionOption, ConditionSource::SelectionCode], true) ? [$condition->attributeId] : null;
        }
        $sources = [];
        foreach ($condition->conditions as $child) {
            $childSources = $this->conditionSources($child);
            if ($childSources === null) {
                return null;
            }
            $sources = [...$sources, ...$childSources];
        }

        return $sources;
    }

    private function identity(?ConfiguratorOptionDTO $option): string
    {
        if ($option === null) {
            return __('configurator.preview.unavailable_value');
        }

        return filled($option->semanticId) ? $option->semanticId.' ('.$option->code.')' : $option->code;
    }
}
