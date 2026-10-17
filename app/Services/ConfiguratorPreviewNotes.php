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
 * @phpstan-type PreviewReport array{effects: list<EffectNote>, warnings: list<array<string, mixed>>, diagnostics: list<array<string, mixed>>, selected_values: list<array{attribute: string, value: string}>}
 */
class ConfiguratorPreviewNotes
{
    /** @return PreviewReport */
    public function build(?ConfiguratorEvaluationResult $result): array
    {
        $report = ['effects' => [], 'warnings' => [], 'diagnostics' => [], 'selected_values' => []];
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
            $selectionDriven = in_array($diagnostic['code'], ['selection_rejected', 'interaction_rejected'], true)
                || (in_array($diagnostic['code'], ['selection_adjusted', 'no_legal_options'], true) && $ruleIds !== [] && ! $hasSystemCause && array_diff($ruleIds, array_keys($selectionRules)) === []);
            if (! $selectionDriven) {
                $report['diagnostics'][] = $diagnostic;

                continue;
            }
            if ($diagnostic['code'] === 'selection_adjusted') {
                $attribute = $result->definition->attributes[$diagnostic['attribute_id']];
                $diagnostic['message'] = __('configurator.preview.selection_replaced', ['attribute' => $result->attributes[$attribute->id]['label'], 'previous' => $this->identity($attribute->options[$diagnostic['previous_choice_id']] ?? null), 'current' => $this->identity($attribute->options[$diagnostic['choice_id']])]);
            }
            $report['warnings'][] = $diagnostic;
        }
        $attributes = $result->definition->attributes;
        uasort($attributes, fn (ConfiguratorAttributeDTO $first, ConfiguratorAttributeDTO $second): int => [$first->displayOrder, $first->id] <=> [$second->displayOrder, $second->id]);
        foreach ($attributes as $id => $attribute) {
            if (isset($result->selections[$id])) {
                $report['selected_values'][] = ['attribute' => $result->attributes[$id]['label'], 'value' => $this->identity($attribute->options[$result->selections[$id]])];
            }
        }

        return $report;
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
