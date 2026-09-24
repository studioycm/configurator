<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Translates the bounded editor vocabulary; the definition compiler remains authoritative. */
class ConfiguratorRuleDraft
{
    /** @param array<string, mixed> $rule @return array<string, mixed> */
    public function fromRule(array $rule): array
    {
        $blocks = [];
        foreach ($rule['conditions'] as $condition) {
            if (isset($condition['conditions'])) {
                $condition['conditions'] = array_map($this->predicateToDraft(...), $condition['conditions']);
                $blocks[] = ['type' => 'group', 'data' => $condition];
            } else {
                $blocks[] = ['type' => 'predicate', 'data' => $this->predicateToDraft($condition)];
            }
        }
        unset($rule['conditions']);
        foreach ($rule['sets'] as &$set) {
            unset($set['sort_order']);
        }
        unset($set);

        return [...$rule, 'condition_blocks' => $blocks];
    }

    /** @param array<string, mixed> $draft @return array<string, mixed> */
    public function toRule(array $draft): array
    {
        $this->keys($draft, ['id', 'label', 'kind', 'is_active', 'priority', 'driver_configurator_attribute_id', 'target_configurator_attribute_id', 'condition_blocks', 'effects', 'sets'], 'rule');
        $conditions = [];
        foreach ($this->rows($draft['condition_blocks'], 'condition_blocks') as $i => $block) {
            $path = 'condition_blocks.'.$i;
            $this->keys($block, ['type', 'data'], $path);
            if (! is_array($block['data'])) {
                $this->invalid($path, 'Provide a condition or a one-level group.');
            }
            if ($block['type'] === 'predicate') {
                $conditions[] = $this->predicateFromDraft($block['data'], $path.'.data');
            } elseif ($block['type'] === 'group') {
                $group = $block['data'];
                $this->keys($group, ['id', 'operator', 'conditions'], $path.'.data');
                $group['conditions'] = array_map(fn (array $condition): array => $this->predicateFromDraft($condition, $path.'.data.conditions'), $this->rows($group['conditions'], $path.'.data.conditions'));
                $conditions[] = $group;
            } else {
                $this->invalid($path, 'Only predicates and one level of All/Any groups are supported.');
            }
        }
        unset($draft['condition_blocks']);
        $draft['sets'] = $this->rows($draft['sets'], 'sets');
        foreach ($draft['sets'] as $i => &$set) {
            $this->keys($set, ['id', 'label', 'source_option_ids', 'target_option_ids'], 'sets.'.$i);
            $set['label'] = $set['label'] === '' ? null : $set['label'];
            $set['sort_order'] = $i;
        }
        unset($set);
        $draft['effects'] = $this->rows($draft['effects'], 'effects');
        foreach ($draft['effects'] as &$effect) {
            if (($effect['display_value'] ?? null) === '') {
                $effect['display_value'] = null;
            }
        }
        unset($effect);

        return [...$draft, 'conditions' => $conditions];
    }

    /** @param array<string, mixed> $condition @return array<string, mixed> */
    private function predicateToDraft(array $condition): array
    {
        $operand = $condition['operand'];
        unset($condition['operand']);

        return [...$condition, 'operand_text' => is_string($operand) ? $operand : null, 'operand_list' => is_array($operand) ? $operand : []];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function predicateFromDraft(array $row, string $path): array
    {
        $this->keys($row, ['id', 'source_kind', 'source_configurator_attribute_id', 'property_key', 'context_dimension', 'operator', 'operand_text', 'operand_list', 'option_ids'], $path);
        $list = in_array($row['operator'], ['In', 'NotIn'], true);
        $selection = in_array($row['source_kind'], ['SelectionOption', 'SelectionCode'], true);
        if (($list && $row['operand_text'] !== null) || (! $list && $row['operand_list'] !== []) || ($selection && ($row['operand_text'] !== null || $row['operand_list'] !== []))) {
            $this->invalid($path, 'Clear the previous operand explicitly before changing its source or scalar/list operator.');
        }
        $operand = $selection ? null : ($list ? $row['operand_list'] : $row['operand_text']);
        unset($row['operand_text'], $row['operand_list']);

        return [...$row, 'operand' => $operand];
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $rows, string $path): array
    {
        if (! is_array($rows) || count($rows) > 500 || array_filter($rows, fn (mixed $row): bool => ! is_array($row)) !== []) {
            $this->invalid($path, 'Provide a bounded list of complete rows.');
        }

        return array_values($rows);
    }

    /** @param array<string, mixed> $row @param list<string> $keys */
    private function keys(array $row, array $keys, string $path): void
    {
        if (array_diff(array_keys($row), $keys) !== [] || array_diff($keys, array_keys($row)) !== []) {
            $this->invalid($path, 'Provide a complete row with only supported fields. Nested or unknown rule payloads are not supported.');
        }
    }

    private function invalid(string $path, string $message): never
    {
        throw ValidationException::withMessages([$path => $message]);
    }
}
