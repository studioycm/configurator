<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ConfiguratorRuleImportBuilder
{
    public function __construct(private ConfiguratorRuleExchange $exchange) {}

    /** @param list<array<string, string>> $rows @param array<string, mixed> $draft @param array<int, array<string, mixed>> $attributes @param array<int, array<string, mixed>> $options @param array<string, mixed> $settings @return array{rule: array<string, mixed>, change: array<string, mixed>} */
    public function build(array $rows, array $draft, array $attributes, array $options, array $settings): array
    {
        $base = $rows[0];
        $create = $base['operation'] === 'Create';
        $this->check(in_array($base['operation'], ['Create', 'Update'], true), 'Choose Create or Update.');
        $this->check($settings[$create ? 'allow_create' : 'allow_update'], $base['operation'].' is not enabled in import options.');
        $this->check($base['format_version'] === 'rules-rows-v1', 'Unsupported format_version.');
        $this->check(in_array($base['enabled'], ['0', '1'], true), 'enabled must be 0 or 1.');
        $this->check(in_array($base['rule_kind'], ['Mapping', 'Advanced'], true), 'Choose Mapping or Advanced.');
        $existing = null;
        if ($create) {
            $this->check($base['rule_id'] === '' && $base['expected_revision'] === '', 'Create requires blank rule_id and expected_revision.');
        } else {
            $this->integer($base['rule_id'], 'rule_id', 1);
            $existing = collect($draft['rules'])->firstWhere('id', $base['rule_id']);
            $this->check($existing !== null, 'Update rule_id does not belong to this Configurator.');
            $revision = hash('sha256', json_encode($existing, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->check(hash_equals($revision, $base['expected_revision']), 'The rule changed since export; export again for its expected_revision.');
        }
        $owned = $this->children($existing ?? []);
        $prefix = hash('sha256', $base['configurator_id']."\0".$base['rule_key']);
        $identity = fn (string $token): string => $owned[$token] ?? 'new:'.hash('sha256', $prefix."\0".$token);
        $rule = ['id' => $existing['id'] ?? 'new:'.$prefix, 'label' => $base['rule_label'], 'kind' => $base['rule_kind'], 'is_active' => $base['enabled'] === '1' && (! $create || ! $settings['keep_new_disabled']), 'priority' => $this->integer($base['priority'], 'priority'), 'driver_configurator_attribute_id' => null, 'target_configurator_attribute_id' => null, 'conditions' => [], 'effects' => [], 'sets' => []];
        $attributeMap = [];
        $optionMap = [];
        foreach ($draft['attributes'] as $attribute) {
            $key = $attributes[$attribute['attribute_id']]['key'] ?? null;
            $this->check(is_string($key) && ! isset($attributeMap[$key]), 'Missing or ambiguous included Attribute key.');
            $attributeMap[$key] = $attribute['id'];
            foreach ($attribute['options'] as $option) {
                $semantic = $options[$option['option_id']]['semantic_id'] ?? null;
                $this->check(is_string($semantic) && $semantic !== '' && ! isset($optionMap[$key][$semantic]), 'Missing or ambiguous included Value ID Name.');
                $optionMap[$key][$semantic] = $option['id'];
            }
        }
        $this->validateReferences($rows, $attributeMap, $optionMap);
        $attributeId = function (string $key) use ($attributeMap): string {
            $this->check(isset($attributeMap[$key]), 'Missing included Attribute: '.$key);

            return $attributeMap[$key];
        };
        $optionIds = function (string $key, string $cell) use ($optionMap): array {
            $values = $this->exchange->decodeList($cell);
            $ids = [];
            foreach ($values as $value) {
                $this->check($value !== '' && isset($optionMap[$key][$value]), 'Missing included Value: '.$key.'.'.$value);
                $ids[] = $optionMap[$key][$value];
            }

            return $ids;
        };
        $rowKeys = [];
        $orders = [];
        foreach ($rows as $row) {
            foreach (array_slice(ConfiguratorRuleExchange::COLUMNS, 0, 11) as $field) {
                $this->check($row[$field] === $base[$field], 'Inconsistent '.$field.' within rule group.');
            }
            $this->check($row['row_key'] !== '' && ! isset($rowKeys[$row['row_key']]), 'Duplicate or empty row_key.');
            $rowKeys[$row['row_key']] = true;
            $order = $this->integer($row['row_order'], 'row_order');
            $this->check(! isset($orders[$order]), 'Duplicate row_order within rule group.');
            $orders[$order] = true;
        }
        usort($rows, fn (array $a, array $b): int => (int) $a['row_order'] <=> (int) $b['row_order']);
        $groups = [];
        $usedTokens = [];
        foreach ($rows as $row) {
            $fields = match ($row['row_type']) {
                'Condition' => ['condition_group', 'group_operator', 'condition_source', 'condition_field', 'condition_operator', 'condition_values'],
                'Effect' => ['target_attribute_key', 'effect_kind', 'target_scope', 'target_values', 'display_value'],
                'MappingSet' => ['condition_source', 'condition_field', 'condition_values', 'target_attribute_key', 'target_values', 'mapping_label', 'mapping_order', 'other_values'],
                default => throw ValidationException::withMessages(['rules' => 'Unknown row_type.']),
            };
            foreach (array_slice(ConfiguratorRuleExchange::COLUMNS, 14) as $field) {
                $this->check(in_array($field, $fields, true) || $row[$field] === '', 'Irrelevant nonblank field '.$field.' on '.$row['row_type'].'.');
            }
            $type = ['Condition' => 'c_', 'Effect' => 'e_', 'MappingSet' => 's_'][$row['row_type']];
            $token = $row['row_key'];
            $id = isset($owned[$token]) && str_starts_with($token, $type) ? $owned[$token] : 'new:'.hash('sha256', $prefix."\0".$type.$token);
            $usedTokens[] = $type.$id;
            if ($row['row_type'] === 'Condition') {
                $selection = in_array($row['condition_source'], ['SelectionOption', 'SelectionCode'], true);
                $product = $row['condition_source'] === 'ProductProperty';
                $list = in_array($row['condition_operator'], ['In', 'NotIn'], true);
                $condition = ['id' => $id, 'source_kind' => $row['condition_source'], 'source_configurator_attribute_id' => $selection ? $attributeId($row['condition_field']) : null, 'property_key' => $product ? $row['condition_field'] : null, 'context_dimension' => ! $selection && ! $product ? $row['condition_field'] : null, 'operator' => $row['condition_operator'], 'operand' => $selection ? null : ($list ? $this->exchange->decodeList($row['condition_values']) : $row['condition_values']), 'option_ids' => $selection ? $optionIds($row['condition_field'], $row['condition_values']) : []];
                if ($row['condition_group'] === '') {
                    $this->check($row['group_operator'] === '', 'Root conditions cannot carry group_operator.');
                    $rule['conditions'][] = $condition;
                } else {
                    $groupToken = $row['condition_group'];
                    $this->check(! isset($rowKeys[$groupToken]), 'Condition group and row keys must be distinct.');
                    $this->check(in_array($row['group_operator'], ['All', 'Any'], true), 'Condition groups require All or Any.');
                    if (isset($groups[$groupToken])) {
                        $this->check($rule['conditions'][$groups[$groupToken]]['operator'] === $row['group_operator'], 'Inconsistent condition group operator.');
                    } else {
                        $groups[$groupToken] = count($rule['conditions']);
                        $groupId = isset($owned[$groupToken]) && str_starts_with($groupToken, 'g_') ? $identity($groupToken) : 'new:'.hash('sha256', $prefix."\0g_".$groupToken);
                        $rule['conditions'][] = ['id' => $groupId, 'operator' => $row['group_operator'], 'conditions' => []];
                        $usedTokens[] = 'g_'.$groupId;
                    }
                    $rule['conditions'][$groups[$groupToken]]['conditions'][] = $condition;
                }
            } elseif ($row['row_type'] === 'Effect') {
                $this->check($rule['kind'] === 'Advanced', 'Mapping rules cannot carry Effect rows.');
                $this->check($row['target_scope'] !== 'Attribute' || $row['target_values'] === '', 'Attribute effects cannot carry target_values.');
                $presentation = in_array($row['effect_kind'], ['SetLabel', 'SetDisplayValue', 'SetHint'], true);
                $this->check($presentation ? $row['display_value'] !== '' : $row['display_value'] === '', 'Only presentation effects require nonempty display_value.');
                $rule['effects'][] = ['id' => $id, 'kind' => $row['effect_kind'], 'target_scope' => $row['target_scope'], 'display_value' => $presentation ? $row['display_value'] : null, 'target_configurator_attribute_id' => $attributeId($row['target_attribute_key']), 'option_ids' => $row['target_scope'] === 'Options' ? $optionIds($row['target_attribute_key'], $row['target_values']) : []];
            } else {
                $this->check($rule['kind'] === 'Mapping' && $row['condition_source'] === 'SelectionOption', 'MappingSet requires Mapping and SelectionOption.');
                $driver = $attributeId($row['condition_field']);
                $target = $attributeId($row['target_attribute_key']);
                $this->check($rule['driver_configurator_attribute_id'] === null || ($rule['driver_configurator_attribute_id'] === $driver && $rule['target_configurator_attribute_id'] === $target), 'Mapping driver and target must agree throughout the group.');
                $rule['driver_configurator_attribute_id'] = $driver;
                $rule['target_configurator_attribute_id'] = $target;
                $rule['sets'][] = ['id' => $id, 'label' => $row['mapping_label'] === '' ? null : $row['mapping_label'], 'sort_order' => $this->integer($row['mapping_order'], 'mapping_order'), 'disallowed_target_behavior' => $row['other_values'], 'source_option_ids' => $optionIds($row['condition_field'], $row['condition_values']), 'target_option_ids' => $optionIds($row['target_attribute_key'], $row['target_values'])];
            }
        }
        if ($existing !== null) {
            $effectIds = array_column($rule['effects'], 'id');
            $saved = array_values(array_intersect(array_column($existing['effects'], 'id'), $effectIds));
            $new = array_values(array_filter($effectIds, fn (string $id): bool => str_starts_with($id, 'new:')));
            $this->check($effectIds === [...$saved, ...$new], 'Keep exported effect order and append new effects after saved effects; this Update cannot preserve child identities in the requested order.');
        }
        $changes = [];
        foreach (['label', 'kind', 'is_active', 'priority', 'conditions', 'effects', 'sets'] as $field) {
            if ($existing === null || $this->ordered($existing[$field]) !== $this->ordered($rule[$field])) {
                $changes[] = ucfirst($field).' before: '.($existing === null ? '(new rule)' : $this->describe($existing[$field], $attributeMap, $optionMap));
                $changes[] = ucfirst($field).' after: '.$this->describe($rule[$field], $attributeMap, $optionMap);
            }
        }

        return ['rule' => $rule, 'change' => ['rule_key' => $base['rule_key'], 'operation' => $base['operation'], 'rule_id' => $existing === null ? null : (int) $existing['id'], 'label' => $rule['label'], 'kind' => $rule['kind'], 'enabled' => $rule['is_active'], 'priority' => $rule['priority'], 'conditions' => count(array_filter($rows, fn (array $row): bool => $row['row_type'] === 'Condition')), 'effects' => count($rule['effects']), 'sets' => count($rule['sets']), 'deleted_children' => array_values(array_diff(array_keys($owned), $usedTokens)), 'changes' => $changes]];
    }

    /** @param list<array<string, string>> $rows @param array<string, string> $attributes @param array<string, array<string, string>> $options */
    private function validateReferences(array $rows, array $attributes, array $options): void
    {
        $errors = [];
        foreach ($rows as $row) {
            $references = [];
            if ($row['row_type'] === 'MappingSet' || ($row['row_type'] === 'Condition' && in_array($row['condition_source'], ['SelectionOption', 'SelectionCode'], true))) {
                $references[] = [$row['condition_field'], $row['condition_values']];
            }
            if (in_array($row['row_type'], ['Effect', 'MappingSet'], true)) {
                $references[] = [$row['target_attribute_key'], $row['row_type'] === 'MappingSet' || $row['target_scope'] === 'Options' ? $row['target_values'] : null];
            }
            foreach ($references as [$key, $cell]) {
                if (! isset($attributes[$key])) {
                    $errors[] = 'Missing included Attribute: '.$key;

                    continue;
                }
                if ($cell === null) {
                    continue;
                }
                try {
                    foreach ($this->exchange->decodeList($cell) as $value) {
                        if ($value === '' || ! isset($options[$key][$value])) {
                            $errors[] = 'Missing included Value: '.$key.'.'.$value;
                        }
                    }
                } catch (\InvalidArgumentException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['rules' => array_values(array_unique($errors))]);
        }
    }

    private function ordered(mixed $value, ?string $field = null): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->ordered($item, (string) $key);
        }
        if (in_array($field, ['option_ids', 'source_option_ids', 'target_option_ids'], true)) {
            sort($value, SORT_STRING);
        }

        return $value;
    }

    /** @param array<string, string> $attributes @param array<string, array<string, string>> $options */
    private function describe(mixed $value, array $attributes, array $options): string
    {
        $attributeKeys = array_flip($attributes);
        $optionKeys = [];
        foreach ($options as $key => $values) {
            foreach ($values as $semantic => $id) {
                $optionKeys[$id] = $key.'.'.$semantic;
            }
        }
        $references = function (mixed $data) use (&$references, $attributeKeys, $optionKeys): mixed {
            if (! is_array($data)) {
                return $data;
            }
            foreach ($data as $field => $item) {
                if (is_string($field) && str_ends_with($field, 'configurator_attribute_id')) {
                    $data[$field] = $item === null ? null : ($attributeKeys[$item] ?? $item);
                } elseif (in_array($field, ['option_ids', 'source_option_ids', 'target_option_ids'], true)) {
                    $data[$field] = array_map(fn (string $id): string => $optionKeys[$id] ?? $id, $item);
                } else {
                    $data[$field] = $references($item);
                }
            }

            return $data;
        };

        return json_encode($references($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $rule @return array<string, string> */
    private function children(array $rule): array
    {
        $children = [];
        foreach ($rule['conditions'] ?? [] as $condition) {
            if (isset($condition['conditions'])) {
                $children['g_'.$condition['id']] = $condition['id'];
                foreach ($condition['conditions'] as $predicate) {
                    $children['c_'.$predicate['id']] = $predicate['id'];
                }
            } else {
                $children['c_'.$condition['id']] = $condition['id'];
            }
        }
        foreach (['effects' => 'e_', 'sets' => 's_'] as $field => $prefix) {
            foreach ($rule[$field] ?? [] as $child) {
                $children[$prefix.$child['id']] = $child['id'];
            }
        }

        return $children;
    }

    public function integer(string $value, string $field, int $minimum = 0): int
    {
        $maximum = in_array($field, ['configurator_id', 'rule_id', 'row_order'], true) ? PHP_INT_MAX : 2147483647;
        $withinMaximum = strlen($value) < strlen((string) $maximum) || (strlen($value) === strlen((string) $maximum) && strcmp($value, (string) $maximum) <= 0);
        $this->check(preg_match('/^(0|[1-9][0-9]*)$/D', $value) === 1 && $withinMaximum && (int) $value >= $minimum, $field.' must be an integer from '.$minimum.' to '.$maximum.'.');

        return (int) $value;
    }

    private function check(bool $valid, string $message): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['rules' => $message]);
        }
    }
}
