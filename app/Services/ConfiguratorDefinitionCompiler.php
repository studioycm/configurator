<?php

namespace App\Services;

use App\ConditionJunction;
use App\ConditionOperator;
use App\ConditionSource;
use App\ConfigInputType;
use App\DTO\ConfiguratorAttributeDTO;
use App\DTO\ConfiguratorConditionDTO;
use App\DTO\ConfiguratorConditionGroupDTO;
use App\DTO\ConfiguratorDefinition;
use App\DTO\ConfiguratorEffectDTO;
use App\DTO\ConfiguratorMappingSetDTO;
use App\DTO\ConfiguratorOptionDTO;
use App\DTO\ConfiguratorRuleDTO;
use App\RuleEffectKind;
use App\RuleKind;
use App\RuleTargetScope;
use Illuminate\Validation\ValidationException;

class ConfiguratorDefinitionCompiler
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{key: string, label: string}>  $canonicalAttributes
     * @param  array<int, array{attribute_id: int, code: string, label: string}>  $canonicalOptions
     */
    public function compile(array $data, array $canonicalAttributes, array $canonicalOptions, bool $authoring = false): ConfiguratorDefinition
    {
        $this->keys($data, ['name', 'description', 'context_schema', 'policy_overrides', 'attributes', 'rules'], 'definition');
        $this->text($data['name'] ?? null, 'name', false);
        $this->text($data['description'] ?? null, 'description', true, 5000);
        $this->check(is_array($data['policy_overrides'] ?? null), 'policy_overrides', 'Provide a policy override object.');
        $policy = ConfiguratorPolicy::resolve($data['policy_overrides']);
        $context = $this->context($data['context_schema'] ?? []);
        $data['context_schema'] = $context;
        $data['policy_overrides'] = [];
        $data['description'] ??= null;
        $attributeRows = $this->rows($data['attributes'] ?? null, 'attributes');
        $seen = [];
        $attributes = [];
        $canonicalIds = [];
        $displayOrders = [];
        $codeOrders = [];
        $codes = [];
        foreach ($attributeRows as $index => &$row) {
            $path = 'attributes.'.$index;
            $this->keys($row, ['id', 'attribute_id', 'display_order', 'code_order', 'label_override', 'input_type', 'help_text', 'default_configurator_option_id', 'options'], $path);
            $id = $this->claim($row, 'attribute', $seen, $path);
            $canonicalId = $this->integer($row['attribute_id'] ?? null, $path.'.attribute_id', 1);
            $this->check(isset($canonicalAttributes[$canonicalId]), $path.'.attribute_id', 'Select an existing canonical Attribute.');
            $this->unique($canonicalId, $canonicalIds, $path.'.attribute_id', 'Each canonical Attribute can be included once.');
            $display = $this->integer($row['display_order'] ?? null, $path.'.display_order');
            $codeOrder = $this->integer($row['code_order'] ?? null, $path.'.code_order');
            $this->unique($display, $displayOrders, $path.'.display_order', 'Display order must be unique.');
            $this->unique($codeOrder, $codeOrders, $path.'.code_order', 'Code order must be unique.');
            $input = is_string($row['input_type'] ?? null) ? ConfigInputType::tryFrom($row['input_type']) : null;
            $this->check($input !== null, $path.'.input_type', 'Choose Toggle or Select.');
            $label = $this->text($row['label_override'] ?? null, $path.'.label_override');
            $help = $this->text($row['help_text'] ?? null, $path.'.help_text', true, 5000);
            $options = [];
            $optionIds = [];
            $optionOrders = [];
            foreach ($this->rows($row['options'] ?? null, $path.'.options', 1) as $optionIndex => $option) {
                $optionPath = $path.'.options.'.$optionIndex;
                $this->keys($option, ['id', 'option_id', 'display_order', 'label_override', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default'], $optionPath);
                $optionId = $this->claim($option, 'option', $seen, $optionPath);
                $canonicalOptionId = $this->integer($option['option_id'] ?? null, $optionPath.'.option_id', 1);
                $canonical = $canonicalOptions[$canonicalOptionId] ?? null;
                $this->check($canonical !== null && $canonical['attribute_id'] === $canonicalId, $optionPath.'.option_id', 'Choose an Option belonging to this canonical Attribute.');
                $this->unique($canonicalOptionId, $optionIds, $optionPath.'.option_id', 'An Option can only be included once.');
                $this->check(is_string($canonical['code']) && preg_match('/\A[A-Za-z0-9]{2}\z/D', $canonical['code']) === 1, $optionPath.'.option_id', 'Repair the canonical Option code: exactly two ASCII alphanumerics are required.');
                $this->unique($canonical['code'], $codes, $optionPath.'.option_id', 'Canonical Option codes must be globally unique and case-sensitive.');
                $order = $this->integer($option['display_order'] ?? null, $optionPath.'.display_order');
                $this->unique($order, $optionOrders, $optionPath.'.display_order', 'Option display order must be unique.');
                $options[$optionId] = new ConfiguratorOptionDTO($optionId, $canonicalOptionId, $canonical['code'], $order,
                    $this->text($option['label_override'] ?? null, $optionPath.'.label_override') ?? $canonical['label'],
                    $this->text($option['display_value_override'] ?? null, $optionPath.'.display_value_override') ?? $canonical['label'],
                    $this->text($option['hint'] ?? null, $optionPath.'.hint', true, 5000),
                    $this->boolean($option['hidden_by_default'] ?? false, $optionPath.'.hidden_by_default'),
                    $this->boolean($option['disabled_by_default'] ?? false, $optionPath.'.disabled_by_default'));
            }
            uasort($options, fn (ConfiguratorOptionDTO $a, ConfiguratorOptionDTO $b): int => [$a->displayOrder, $a->id] <=> [$b->displayOrder, $b->id]);
            $default = $row['default_configurator_option_id'] ?? null;
            if ($default === null && str_starts_with($id, 'new:')) {
                $default = array_key_first($options);
            }
            $this->check((is_int($default) || is_string($default)) && isset($options[(string) $default]), $path.'.default_configurator_option_id', 'Choose a default from this inclusion. Repair the default before removing its Option.');
            $row['default_configurator_option_id'] = (string) $default;
            $attributes[$id] = new ConfiguratorAttributeDTO($id, $canonicalId, $canonicalAttributes[$canonicalId]['key'], $label ?? $canonicalAttributes[$canonicalId]['label'], $input->value, $help, $display, $codeOrder, (string) $default, $options);
        }
        unset($row);
        $data['attributes'] = $attributeRows;
        $rules = [];
        $edges = array_fill_keys(array_keys($attributes), []);
        $priorities = [];
        foreach ($this->rows($data['rules'] ?? null, 'rules') as $index => $row) {
            $path = 'rules.'.$index;
            $this->keys($row, ['id', 'label', 'kind', 'is_active', 'priority', 'driver_configurator_attribute_id', 'target_configurator_attribute_id', 'conditions', 'effects', 'sets'], $path);
            $id = $this->claim($row, 'rule', $seen, $path);
            $label = $this->text($row['label'] ?? null, $path.'.label', false);
            $kind = is_string($row['kind'] ?? null) ? RuleKind::tryFrom($row['kind']) : null;
            $this->check($kind !== null, $path.'.kind', 'Choose Mapping or Advanced.');
            $priority = $this->integer($row['priority'] ?? null, $path.'.priority');
            if ($authoring) {
                $this->unique($priority, $priorities, $path.'.priority', 'Rule priority must be unique. Reorder the complete rule list.');
            }
            $conditions = $this->conditions($row['conditions'] ?? null, $attributes, $context, $seen, $path.'.conditions');
            $sources = $this->selectionSources($conditions);
            $driver = null;
            $target = null;
            $sets = [];
            $effects = [];
            if ($kind === RuleKind::Mapping) {
                $driver = $this->attributeReference($row['driver_configurator_attribute_id'] ?? null, $attributes, $path.'.driver_configurator_attribute_id');
                $target = $this->attributeReference($row['target_configurator_attribute_id'] ?? null, $attributes, $path.'.target_configurator_attribute_id');
                $this->check(($row['effects'] ?? []) === [], $path.'.effects', 'Mapping rules use mapping sets.');
                $sources[] = $driver;
                $usedSources = [];
                $setOrders = [];
                foreach ($this->rows($row['sets'] ?? null, $path.'.sets', 1) as $setIndex => $set) {
                    $setPath = $path.'.sets.'.$setIndex;
                    $this->keys($set, ['id', 'label', 'sort_order', 'source_option_ids', 'target_option_ids'], $setPath);
                    $setId = $this->claim($set, 'set', $seen, $setPath);
                    $this->text($set['label'] ?? null, $setPath.'.label');
                    $this->unique($this->integer($set['sort_order'] ?? null, $setPath.'.sort_order'), $setOrders, $setPath.'.sort_order', 'Set order must be unique.');
                    $sourceIds = $this->optionReferences($set['source_option_ids'] ?? null, $attributes[$driver], $setPath.'.source_option_ids');
                    foreach ($sourceIds as $sourceId) {
                        $this->unique($sourceId, $usedSources, $setPath.'.source_option_ids', 'A source Option can belong to at most one set in this rule.');
                    }
                    $sets[] = new ConfiguratorMappingSetDTO($setId, $sourceIds, $this->optionReferences($set['target_option_ids'] ?? null, $attributes[$target], $setPath.'.target_option_ids'));
                }
                foreach (array_unique($sources) as $source) {
                    $edges[$source][$target][] = $label;
                }
            } else {
                $this->check(($row['sets'] ?? []) === [] && ($row['driver_configurator_attribute_id'] ?? null) === null && ($row['target_configurator_attribute_id'] ?? null) === null, $path, 'Advanced rules use typed effects, not mapping driver/target fields.');
                foreach ($this->rows($row['effects'] ?? null, $path.'.effects', 1) as $effectIndex => $effect) {
                    $effectPath = $path.'.effects.'.$effectIndex;
                    $this->keys($effect, ['id', 'target_configurator_attribute_id', 'kind', 'target_scope', 'option_ids', 'display_value'], $effectPath);
                    $effectId = $this->claim($effect, 'effect', $seen, $effectPath);
                    $effectTarget = $this->attributeReference($effect['target_configurator_attribute_id'] ?? null, $attributes, $effectPath.'.target_configurator_attribute_id');
                    $effectKind = is_string($effect['kind'] ?? null) ? RuleEffectKind::tryFrom($effect['kind']) : null;
                    $scope = is_string($effect['target_scope'] ?? null) ? RuleTargetScope::tryFrom($effect['target_scope']) : null;
                    $this->check($effectKind !== null && $scope !== null, $effectPath, 'Choose a supported effect and target scope.');
                    $presentation = in_array($effectKind, [RuleEffectKind::SetLabel, RuleEffectKind::SetDisplayValue, RuleEffectKind::SetHint], true);
                    $this->check($presentation || ($effectKind === RuleEffectKind::HideAttribute ? $scope === RuleTargetScope::Attribute : $scope === RuleTargetScope::Options), $effectPath.'.target_scope', 'This effect has the wrong target scope.');
                    $optionIds = $scope === RuleTargetScope::Options ? $this->optionReferences($effect['option_ids'] ?? null, $attributes[$effectTarget], $effectPath.'.option_ids') : [];
                    $this->check($scope !== RuleTargetScope::Attribute || ($effect['option_ids'] ?? []) === [], $effectPath.'.option_ids', 'Attribute effects cannot carry Option references.');
                    $value = $this->text($effect['display_value'] ?? null, $effectPath.'.display_value', ! $presentation);
                    $this->check($presentation || $value === null, $effectPath.'.display_value', 'Only presentation effects accept text.');
                    $effects[] = new ConfiguratorEffectDTO($effectId, $effectTarget, $effectKind, $scope, $optionIds, $value);
                    if (! $presentation) {
                        foreach (array_unique($sources) as $source) {
                            $edges[$source][$effectTarget][] = $label;
                        }
                    }
                }
            }
            $rules[] = new ConfiguratorRuleDTO($id, $label, $kind, $this->boolean($row['is_active'] ?? null, $path.'.is_active'), $priority, new ConfiguratorConditionGroupDTO('root', ConditionJunction::All, $conditions), $driver, $target, $sets, $effects);
        }

        return new ConfiguratorDefinition($attributes, $rules, $this->topologicalOrder($attributes, $edges), $context, $data, $policy);
    }

    /** @param array<string, mixed> $schema @return array<string, list<array{value: string, label: string}>> */
    private function context(mixed $schema): array
    {
        $this->check(is_array($schema), 'context_schema', 'Provide Territory and Application choices.');
        $this->keys($schema, ['territory', 'application'], 'context_schema');
        $result = [];
        foreach (['territory', 'application'] as $dimension) {
            $values = [];
            $result[$dimension] = [];
            foreach ($this->rows($schema[$dimension] ?? [], 'context_schema.'.$dimension) as $index => $choice) {
                $path = 'context_schema.'.$dimension.'.'.$index;
                $this->keys($choice, ['value', 'label'], $path);
                $value = $this->text($choice['value'] ?? null, $path.'.value', false);
                $this->check($value !== 'All', $path.'.value', 'All is reserved for unrestricted context.');
                $this->unique($value, $values, $path.'.value', 'Context choices must be distinct.');
                $result[$dimension][] = ['value' => $value, 'label' => $this->text($choice['label'] ?? null, $path.'.label', false)];
            }
        }

        return $result;
    }

    /** @param array<string, ConfiguratorAttributeDTO> $attributes @param array<string, mixed> $context @param array<string, array<string, bool>> $seen @return list<ConfiguratorConditionDTO|ConfiguratorConditionGroupDTO> */
    private function conditions(mixed $rows, array $attributes, array $context, array &$seen, string $path, bool $nested = false): array
    {
        $result = [];
        foreach ($this->rows($rows, $path, $nested ? 1 : 0) as $index => $row) {
            $field = $path.'.'.$index;
            if (array_key_exists('conditions', $row)) {
                $this->check(! $nested, $field, 'Only one level of All/Any groups is supported.');
                $this->keys($row, ['id', 'operator', 'conditions'], $field);
                $id = $this->claim($row, 'condition_group', $seen, $field);
                $junction = is_string($row['operator'] ?? null) ? ConditionJunction::tryFrom($row['operator']) : null;
                $this->check($junction !== null, $field.'.operator', 'Choose All or Any.');
                $result[] = new ConfiguratorConditionGroupDTO($id, $junction, $this->conditions($row['conditions'], $attributes, $context, $seen, $field.'.conditions', true));

                continue;
            }
            $this->keys($row, ['id', 'source_kind', 'source_configurator_attribute_id', 'property_key', 'context_dimension', 'operator', 'operand', 'option_ids'], $field);
            $id = $this->claim($row, 'condition', $seen, $field);
            $source = is_string($row['source_kind'] ?? null) ? ConditionSource::tryFrom($row['source_kind']) : null;
            $operator = is_string($row['operator'] ?? null) ? ConditionOperator::tryFrom($row['operator']) : null;
            $this->check($source !== null && $operator !== null, $field, 'Choose a supported condition source and operator.');
            $attribute = null;
            $property = null;
            $dimension = null;
            $optionIds = [];
            $operand = $row['operand'] ?? null;
            $selection = in_array($source, [ConditionSource::SelectionOption, ConditionSource::SelectionCode], true);
            $list = in_array($operator, [ConditionOperator::In, ConditionOperator::NotIn], true);
            if ($selection) {
                $attribute = $this->attributeReference($row['source_configurator_attribute_id'] ?? null, $attributes, $field.'.source_configurator_attribute_id');
                $optionIds = $this->optionReferences($row['option_ids'] ?? null, $attributes[$attribute], $field.'.option_ids');
                $this->check($operator !== ConditionOperator::Contains && ($list || count($optionIds) === 1) && $operand === null, $field.'.option_ids', 'Select one included Option for equality or a nonempty set for In/NotIn.');
            } else {
                $this->check(($row['option_ids'] ?? []) === [], $field.'.option_ids', 'Only selection conditions accept Option references.');
                if ($source === ConditionSource::ProductProperty) {
                    $property = $row['property_key'] ?? null;
                    $this->check(is_string($property) && in_array($property, CatalogImportParser::propertyKeys(), true), $field.'.property_key', 'Choose a registered Product property.');
                } else {
                    $dimension = $source === ConditionSource::Territory ? 'territory' : 'application';
                    $this->check(($row['context_dimension'] ?? null) === $dimension && $operator !== ConditionOperator::Contains, $field.'.context_dimension', 'Choose the matching context dimension.');
                }
                if ($list) {
                    $this->check(is_array($operand) && array_is_list($operand) && count($operand) > 0 && count($operand) <= 500, $field.'.operand', 'Provide a nonempty list of strings.');
                    foreach ($operand as $value) {
                        $this->text($value, $field.'.operand', false, 5000, true);
                    }
                    $this->check(count(array_unique($operand, SORT_STRING)) === count($operand), $field.'.operand', 'Use distinct operand values.');
                } else {
                    $this->text($operand, $field.'.operand', false, 5000, true);
                }
                if ($dimension !== null) {
                    foreach ($list ? $operand : [$operand] as $value) {
                        $this->check(in_array($value, array_column($context[$dimension], 'value'), true), $field.'.operand', 'Select a registered context choice. All is unrestricted and cannot be a predicate operand.');
                    }
                }
            }
            $this->check(($row['source_configurator_attribute_id'] ?? null) === null || $selection, $field.'.source_configurator_attribute_id', 'This source does not accept an Attribute selector.');
            $this->check(($row['property_key'] ?? null) === null || $source === ConditionSource::ProductProperty, $field.'.property_key', 'This source does not accept a Product property selector.');
            $this->check(($row['context_dimension'] ?? null) === null || $dimension !== null, $field.'.context_dimension', 'This source does not accept a context selector.');
            $result[] = new ConfiguratorConditionDTO($id, $source, $attribute, $property, $dimension, $operator, $operand, $optionIds);
        }

        return $result;
    }

    /** @param list<ConfiguratorConditionDTO|ConfiguratorConditionGroupDTO> $conditions @return list<string> */
    private function selectionSources(array $conditions): array
    {
        $sources = [];
        foreach ($conditions as $condition) {
            if ($condition instanceof ConfiguratorConditionGroupDTO) {
                $sources = [...$sources, ...$this->selectionSources($condition->conditions)];
            } elseif ($condition->attributeId !== null) {
                $sources[] = $condition->attributeId;
            }
        }

        return array_values(array_unique($sources));
    }

    /** @param array<string, ConfiguratorAttributeDTO> $attributes @param array<string, array<string, list<string>>> $edges @return list<string> */
    private function topologicalOrder(array $attributes, array $edges): array
    {
        $inDegree = array_fill_keys(array_keys($attributes), 0);
        foreach ($edges as $targets) {
            foreach ($targets as $target => $_) {
                $inDegree[$target]++;
            }
        }
        $order = [];
        while (count($order) < count($attributes)) {
            $ready = array_keys(array_filter($inDegree, fn (int $degree): bool => $degree === 0));
            sort($ready, SORT_NATURAL);
            if ($ready === []) {
                $pending = array_keys($inDegree);
                $labels = [];
                foreach ($pending as $id) {
                    $labels[] = $attributes[$id]->key;
                }
                $rules = [];
                foreach ($pending as $id) {
                    foreach ($edges[$id] as $target => $edgeRules) {
                        if (isset($inDegree[$target])) {
                            $rules = [...$rules, ...$edgeRules];
                        }
                    }
                }
                $this->check(false, 'rules', 'Choice dependency cycle involving '.implode(' → ', $labels).'. Repair rules: '.implode(', ', array_unique($rules)).'.');
            }
            $id = (string) $ready[0];
            $order[] = $id;
            unset($inDegree[$id]);
            foreach ($edges[$id] as $target => $_) {
                $inDegree[$target]--;
            }
        }

        return $order;
    }

    /** @param array<string, ConfiguratorAttributeDTO> $attributes */
    private function attributeReference(mixed $id, array $attributes, string $path): string
    {
        $this->check((is_int($id) || is_string($id)) && isset($attributes[(string) $id]), $path, 'This Attribute is not included. Repair referencing rules before removing it.');

        return (string) $id;
    }

    /** @return list<string> */
    private function optionReferences(mixed $ids, ConfiguratorAttributeDTO $attribute, string $path): array
    {
        $this->check(is_array($ids) && array_is_list($ids) && count($ids) > 0 && count($ids) <= 500, $path, 'Select at least one included Option.');
        $result = [];
        foreach ($ids as $id) {
            $this->check((is_int($id) || is_string($id)) && isset($attribute->options[(string) $id]), $path, 'An Option belongs to a different Attribute or was removed. Repair this reference first.');
            $this->check(! in_array((string) $id, $result, true), $path, 'Select distinct Options.');
            $result[] = (string) $id;
        }

        return $result;
    }

    /** @param array<string, mixed> $row @param array<string, array<string, bool>> $seen */
    private function claim(array $row, string $type, array &$seen, string $path): string
    {
        $value = $row['id'] ?? null;
        $this->check((is_int($value) || is_string($value)) && preg_match('/\A(?:[1-9][0-9]*|new:[A-Za-z0-9_-]{1,80})\z/D', (string) $value) === 1, $path.'.id', 'Provide a stable row ID or a new staging key.');
        $id = (string) $value;
        $this->check(! isset($seen[$type][$id]), $path.'.id', 'This row ID is repeated.');
        $seen[$type][$id] = true;

        return $id;
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $rows, string $path, int $minimum = 0): array
    {
        $this->check(is_array($rows) && array_is_list($rows) && count($rows) >= $minimum && count($rows) <= 500, $path, 'Provide a complete list with at least '.$minimum.' rows (maximum 500).');
        foreach ($rows as $row) {
            $this->check(is_array($row), $path, 'Each row must be an object.');
        }

        return $rows;
    }

    /** @param array<string, mixed> $row @param list<string> $allowed */
    private function keys(array $row, array $allowed, string $path): void
    {
        $this->check(array_diff(array_keys($row), $allowed) === [] && array_diff($allowed, array_keys($row)) === [], $path, 'Provide the complete row with only the supported fields.');
    }

    private function text(mixed $value, string $path, bool $nullable = true, int $maximum = 255, bool $allowEmpty = false): ?string
    {
        if ($nullable && $value === null) {
            return null;
        }
        $this->check(is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen($value) <= $maximum && ($allowEmpty || $value !== ''), $path, 'Provide valid text up to '.$maximum.' characters.');

        return $value;
    }

    private function integer(mixed $value, string $path, int $minimum = 0): int
    {
        $this->check((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= $minimum && (int) $value <= 2147483647, $path, 'Provide a whole number from '.$minimum.' to 2147483647.');

        return (int) $value;
    }

    private function boolean(mixed $value, string $path): bool
    {
        $this->check(is_bool($value), $path, 'Provide true or false.');

        return $value;
    }

    /** @param list<string|int> $seen */
    private function unique(string|int $value, array &$seen, string $path, string $message): void
    {
        $this->check(! in_array($value, $seen, true), $path, $message);
        $seen[] = $value;
    }

    private function check(bool $condition, string $path, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$path => $message]);
        }
    }
}
