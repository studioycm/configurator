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
use App\MappingTargetBehavior;
use App\RuleEffectKind;
use App\RuleKind;
use App\RuleTargetScope;
use Illuminate\Validation\ValidationException;

class ConfiguratorDefinitionCompiler
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{key: string, label: string}>  $canonicalAttributes
     * @param  array<int, array{attribute_id: int, code: string, label: string, semantic_id?: string, is_active?: bool, is_hidden?: bool}>  $canonicalOptions
     * @param  array<string, list<array{value: string, label: string}>>  $globalContext
     */
    public function compile(array $data, array $canonicalAttributes, array $canonicalOptions, bool $authoring = false, array $globalContext = []): ConfiguratorDefinition
    {
        $data += ['hidden_context_options' => ['territory' => [], 'application' => []]];
        $this->keys($data, ['name', 'description', 'context_schema', 'hidden_context_options', 'policy_overrides', 'attributes', 'rules'], 'definition');
        $this->text($data['name'] ?? null, 'name', false);
        $this->text($data['description'] ?? null, 'description', true, 5000);
        $this->check(is_array($data['policy_overrides'] ?? null), 'policy_overrides', __('configurator.validation.provide_a_policy_override_object'));
        $policy = ConfiguratorPolicy::resolve($data['policy_overrides']);
        $data['context_schema'] = $this->context($data['context_schema'] ?? []);
        $data['hidden_context_options'] = $this->hiddenContext($data['hidden_context_options'] ?? []);
        $context = $this->resolveContext($data['context_schema'], $data['hidden_context_options'], $globalContext);
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
            $row += ['is_active' => true];
            $this->keys($row, ['id', 'attribute_id', 'display_order', 'code_order', 'label_override', 'input_type', 'help_text', 'default_configurator_option_id', 'options', 'is_active'], $path);
            $row['is_active'] = $this->boolean($row['is_active'] ?? true, $path.'.is_active');
            $id = $this->claim($row, 'attribute', $seen, $path);
            $canonicalId = $this->integer($row['attribute_id'] ?? null, $path.'.attribute_id', 1);
            $this->check(isset($canonicalAttributes[$canonicalId]), $path.'.attribute_id', __('configurator.validation.select_an_existing_canonical_attribute'));
            $this->unique($canonicalId, $canonicalIds, $path.'.attribute_id', __('configurator.validation.each_canonical_attribute_can_be_included_once'));
            $display = $this->integer($row['display_order'] ?? null, $path.'.display_order');
            $codeOrder = $this->integer($row['code_order'] ?? null, $path.'.code_order');
            $this->unique($display, $displayOrders, $path.'.display_order', __('configurator.validation.display_order_must_be_unique'));
            $this->unique($codeOrder, $codeOrders, $path.'.code_order', __('configurator.validation.code_order_must_be_unique'));
            $input = is_string($row['input_type'] ?? null) ? ConfigInputType::tryFrom($row['input_type']) : null;
            $this->check($input !== null, $path.'.input_type', __('configurator.validation.choose_toggle_or_select'));
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
                $this->check($canonical !== null && $canonical['attribute_id'] === $canonicalId, $optionPath.'.option_id', __('configurator.validation.choose_an_attribute_value_belonging_to_this'));
                $this->unique($canonicalOptionId, $optionIds, $optionPath.'.option_id', __('configurator.validation.an_attribute_value_can_only_be_included'));
                $this->check(is_string($canonical['code']) && preg_match('/\A[A-Za-z0-9]{2}\z/D', $canonical['code']) === 1, $optionPath.'.option_id', __('configurator.validation.repair_the_shared_attribute_value_code_exactly'));
                $this->unique($canonical['code'], $codes, $optionPath.'.option_id', __('configurator.validation.shared_attribute_value_codes_must_be_globally'));
                $order = $this->integer($option['display_order'] ?? null, $optionPath.'.display_order');
                $this->unique($order, $optionOrders, $optionPath.'.display_order', __('configurator.validation.included_value_display_order_must_be_unique'));
                $options[$optionId] = new ConfiguratorOptionDTO($optionId, $canonicalOptionId, $canonical['code'], $order,
                    $this->text($option['label_override'] ?? null, $optionPath.'.label_override') ?? $canonical['label'],
                    $this->text($option['display_value_override'] ?? null, $optionPath.'.display_value_override') ?? $canonical['label'],
                    $this->text($option['hint'] ?? null, $optionPath.'.hint', true, 5000),
                    $this->boolean($option['hidden_by_default'] ?? false, $optionPath.'.hidden_by_default'),
                    $this->boolean($option['disabled_by_default'] ?? false, $optionPath.'.disabled_by_default'),
                    $canonical['is_active'] ?? true, $canonical['is_hidden'] ?? false, $canonical['semantic_id'] ?? null);
            }
            uasort($options, fn (ConfiguratorOptionDTO $a, ConfiguratorOptionDTO $b): int => [$a->displayOrder, $a->id] <=> [$b->displayOrder, $b->id]);
            $default = $row['default_configurator_option_id'] ?? null;
            if ($default === null && str_starts_with($id, 'new:')) {
                $default = array_key_first($options);
            }
            $this->check((is_int($default) || is_string($default)) && isset($options[(string) $default]), $path.'.default_configurator_option_id', __('configurator.validation.choose_a_default_from_this_inclusion_repair'));
            $row['default_configurator_option_id'] = (string) $default;
            $attributes[$id] = new ConfiguratorAttributeDTO($id, $canonicalId, $canonicalAttributes[$canonicalId]['key'], $label ?? $canonicalAttributes[$canonicalId]['label'], $input->value, $help, $display, $codeOrder, (string) $default, $options, $row['is_active'] && ($canonicalAttributes[$canonicalId]['is_active'] ?? true));
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
            $this->check($kind !== null, $path.'.kind', __('configurator.validation.choose_mapping_or_advanced'));
            $priority = $this->integer($row['priority'] ?? null, $path.'.priority');
            if ($authoring) {
                $this->unique($priority, $priorities, $path.'.priority', __('configurator.validation.rule_priority_must_be_unique_reorder_the'));
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
                $this->check(($row['effects'] ?? []) === [], $path.'.effects', __('configurator.validation.mapping_rules_use_mapping_sets'));
                $sources[] = $driver;
                $usedSources = [];
                $setOrders = [];
                foreach ($this->rows($row['sets'] ?? null, $path.'.sets', 1) as $setIndex => $set) {
                    $setPath = $path.'.sets.'.$setIndex;
                    if (! array_key_exists('disallowed_target_behavior', $set)) {
                        $set['disallowed_target_behavior'] = MappingTargetBehavior::Disable->value;
                    }
                    $this->keys($set, ['id', 'label', 'sort_order', 'source_option_ids', 'target_option_ids', 'disallowed_target_behavior'], $setPath);
                    $behavior = is_string($set['disallowed_target_behavior']) ? MappingTargetBehavior::tryFrom($set['disallowed_target_behavior']) : null;
                    $this->check($behavior !== null, $setPath.'.disallowed_target_behavior', __('configurator.validation.choose_disable_or_hide_for_disallowed_targets'));
                    $data['rules'][$index]['sets'][$setIndex]['disallowed_target_behavior'] = $behavior->value;
                    $setId = $this->claim($set, 'set', $seen, $setPath);
                    $this->text($set['label'] ?? null, $setPath.'.label');
                    $this->unique($this->integer($set['sort_order'] ?? null, $setPath.'.sort_order'), $setOrders, $setPath.'.sort_order', __('configurator.validation.set_order_must_be_unique'));
                    $sourceIds = $this->optionReferences($set['source_option_ids'] ?? null, $attributes[$driver], $setPath.'.source_option_ids');
                    foreach ($sourceIds as $sourceId) {
                        $this->unique($sourceId, $usedSources, $setPath.'.source_option_ids', __('configurator.validation.a_source_value_can_belong_to_at'));
                    }
                    $sets[] = new ConfiguratorMappingSetDTO($setId, $sourceIds, $this->optionReferences($set['target_option_ids'] ?? null, $attributes[$target], $setPath.'.target_option_ids'), $behavior);
                }
                foreach (array_unique($sources) as $source) {
                    $edges[$source][$target][] = $label;
                }
            } else {
                $this->check(($row['sets'] ?? []) === [] && ($row['driver_configurator_attribute_id'] ?? null) === null && ($row['target_configurator_attribute_id'] ?? null) === null, $path, __('configurator.validation.advanced_rules_use_typed_effects_not_mapping'));
                foreach ($this->rows($row['effects'] ?? null, $path.'.effects', 1) as $effectIndex => $effect) {
                    $effectPath = $path.'.effects.'.$effectIndex;
                    $this->keys($effect, ['id', 'target_configurator_attribute_id', 'kind', 'target_scope', 'option_ids', 'display_value'], $effectPath);
                    $effectId = $this->claim($effect, 'effect', $seen, $effectPath);
                    $effectTarget = $this->attributeReference($effect['target_configurator_attribute_id'] ?? null, $attributes, $effectPath.'.target_configurator_attribute_id');
                    $effectKind = is_string($effect['kind'] ?? null) ? RuleEffectKind::tryFrom($effect['kind']) : null;
                    $scope = is_string($effect['target_scope'] ?? null) ? RuleTargetScope::tryFrom($effect['target_scope']) : null;
                    $this->check($effectKind !== null && $scope !== null, $effectPath, __('configurator.validation.choose_a_supported_effect_and_target_scope'));
                    $presentation = in_array($effectKind, [RuleEffectKind::SetLabel, RuleEffectKind::SetDisplayValue, RuleEffectKind::SetHint], true);
                    $this->check($presentation || ($effectKind === RuleEffectKind::HideAttribute ? $scope === RuleTargetScope::Attribute : $scope === RuleTargetScope::Options), $effectPath.'.target_scope', __('configurator.validation.this_effect_has_the_wrong_target_scope'));
                    $optionIds = $scope === RuleTargetScope::Options ? $this->optionReferences($effect['option_ids'] ?? null, $attributes[$effectTarget], $effectPath.'.option_ids') : [];
                    $this->check($scope !== RuleTargetScope::Attribute || ($effect['option_ids'] ?? []) === [], $effectPath.'.option_ids', __('configurator.validation.attribute_effects_cannot_carry_included_value_references'));
                    $value = $this->text($effect['display_value'] ?? null, $effectPath.'.display_value', ! $presentation);
                    $this->check($presentation || $value === null, $effectPath.'.display_value', __('configurator.validation.only_presentation_effects_accept_text'));
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

    /** @param array<string, mixed> $local @param array<string, mixed> $hidden @param array<string, mixed> $global @return array<string, list<array{value: string, label: string}>> */
    public function resolveContext(array $local, array $hidden, array $global): array
    {
        $local = $this->context($local + ['territory' => [], 'application' => []]);
        $global = $this->context($global + ['territory' => [], 'application' => []]);
        $hidden = $this->hiddenContext($hidden);
        $result = [];
        foreach (['territory', 'application'] as $dimension) {
            $choices = [];
            foreach ($global[$dimension] as $choice) {
                $choices[$choice['value']] = $choice;
            }
            foreach ($local[$dimension] as $choice) {
                $choices[$choice['value']] = $choice;
            }
            $result[$dimension] = array_values(array_filter($choices, fn (array $choice): bool => ! in_array($choice['value'], $hidden[$dimension], true)));
        }

        return $result;
    }

    /** @return array{territory: list<string>, application: list<string>} */
    private function hiddenContext(mixed $hidden): array
    {
        $this->check(is_array($hidden), 'hidden_context_options', __('configurator.validation.choose_which_global_options_to_hide'));
        $hidden += ['territory' => [], 'application' => []];
        $this->keys($hidden, ['territory', 'application'], 'hidden_context_options');
        $result = [];
        foreach (['territory', 'application'] as $dimension) {
            $path = 'hidden_context_options.'.$dimension;
            $values = $hidden[$dimension] ?? [];
            $this->check(is_array($values) && array_is_list($values) && count($values) <= 500, $path, __('configurator.validation.provide_a_list_of_global_option_values'));
            $seen = [];
            foreach ($values as $index => $value) {
                $value = $this->text($value, $path.'.'.$index, false);
                $this->check($value !== ConfiguratorPolicy::UNRESTRICTED_CONTEXT, $path, __('configurator.validation.all_is_always_available'));
                $this->unique($value, $seen, $path, __('configurator.validation.choose_each_hidden_option_only_once'));
            }
            $result[$dimension] = $values;
        }

        return $result;
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    public function context(mixed $schema): array
    {
        $this->check(is_array($schema), 'context_schema', __('configurator.validation.provide_territory_and_application_choices'));
        $this->keys($schema, ['territory', 'application'], 'context_schema');
        $result = [];
        foreach (['territory', 'application'] as $dimension) {
            $values = [];
            $result[$dimension] = [];
            foreach ($this->rows($schema[$dimension] ?? [], 'context_schema.'.$dimension) as $index => $choice) {
                $path = 'context_schema.'.$dimension.'.'.$index;
                $this->keys($choice, ['value', 'label'], $path);
                $value = $this->text($choice['value'] ?? null, $path.'.value', false);
                $this->check($value !== 'All', $path.'.value', __('configurator.validation.all_is_reserved_for_unrestricted_context'));
                $this->unique($value, $values, $path.'.value', __('configurator.validation.context_choices_must_be_distinct'));
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
                $this->check(! $nested, $field, __('configurator.validation.only_one_level_of_allany_groups_is'));
                $this->keys($row, ['id', 'operator', 'conditions'], $field);
                $id = $this->claim($row, 'condition_group', $seen, $field);
                $junction = is_string($row['operator'] ?? null) ? ConditionJunction::tryFrom($row['operator']) : null;
                $this->check($junction !== null, $field.'.operator', __('configurator.validation.choose_all_or_any'));
                $result[] = new ConfiguratorConditionGroupDTO($id, $junction, $this->conditions($row['conditions'], $attributes, $context, $seen, $field.'.conditions', true));

                continue;
            }
            $this->keys($row, ['id', 'source_kind', 'source_configurator_attribute_id', 'property_key', 'context_dimension', 'operator', 'operand', 'option_ids'], $field);
            $id = $this->claim($row, 'condition', $seen, $field);
            $source = is_string($row['source_kind'] ?? null) ? ConditionSource::tryFrom($row['source_kind']) : null;
            $operator = is_string($row['operator'] ?? null) ? ConditionOperator::tryFrom($row['operator']) : null;
            $this->check($source !== null && $operator !== null, $field, __('configurator.validation.choose_a_supported_condition_source_and_operator'));
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
                $this->check($operator !== ConditionOperator::Contains && ($list || count($optionIds) === 1) && $operand === null, $field.'.option_ids', __('configurator.validation.select_one_included_value_for_equality_or'));
            } else {
                $this->check(($row['option_ids'] ?? []) === [], $field.'.option_ids', __('configurator.validation.only_selection_conditions_accept_included_value_references'));
                if ($source === ConditionSource::ProductProperty) {
                    $property = $row['property_key'] ?? null;
                    $this->check(is_string($property) && in_array($property, CatalogImportParser::propertyKeys(), true), $field.'.property_key', __('configurator.validation.choose_a_registered_product_property'));
                } else {
                    $dimension = $source === ConditionSource::Territory ? 'territory' : 'application';
                    $this->check(($row['context_dimension'] ?? null) === $dimension && $operator !== ConditionOperator::Contains, $field.'.context_dimension', __('configurator.validation.choose_the_matching_context_dimension'));
                }
                if ($list) {
                    $this->check(is_array($operand) && array_is_list($operand) && count($operand) > 0 && count($operand) <= 500, $field.'.operand', __('configurator.validation.provide_a_nonempty_list_of_strings'));
                    foreach ($operand as $value) {
                        $this->text($value, $field.'.operand', false, 5000, true);
                    }
                    $this->check(count(array_unique($operand, SORT_STRING)) === count($operand), $field.'.operand', __('configurator.validation.use_distinct_operand_values'));
                } else {
                    $this->text($operand, $field.'.operand', false, 5000, true);
                }
                if ($dimension !== null) {
                    foreach ($list ? $operand : [$operand] as $value) {
                        $this->check(in_array($value, array_column($context[$dimension], 'value'), true), $field.'.operand', __('configurator.validation.select_a_registered_context_choice_all_is'));
                    }
                }
            }
            $this->check(($row['source_configurator_attribute_id'] ?? null) === null || $selection, $field.'.source_configurator_attribute_id', __('configurator.validation.this_source_does_not_accept_an_attribute'));
            $this->check(($row['property_key'] ?? null) === null || $source === ConditionSource::ProductProperty, $field.'.property_key', __('configurator.validation.this_source_does_not_accept_a_product'));
            $this->check(($row['context_dimension'] ?? null) === null || $dimension !== null, $field.'.context_dimension', __('configurator.validation.this_source_does_not_accept_a_context'));
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
                $this->check(false, 'rules', __('configurator.validation.choice_dependency_cycle_involving_repair_rules', ['value1' => implode(' → ', $labels), 'value2' => implode(', ', array_unique($rules))]));
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
        $this->check((is_int($id) || is_string($id)) && isset($attributes[(string) $id]), $path, __('configurator.validation.this_attribute_is_not_included_repair_referencing'));

        return (string) $id;
    }

    /** @return list<string> */
    private function optionReferences(mixed $ids, ConfiguratorAttributeDTO $attribute, string $path): array
    {
        $this->check(is_array($ids) && array_is_list($ids) && count($ids) > 0 && count($ids) <= 500, $path, __('configurator.validation.select_at_least_one_included_value'));
        $result = [];
        foreach ($ids as $id) {
            $this->check((is_int($id) || is_string($id)) && isset($attribute->options[(string) $id]), $path, __('configurator.validation.an_included_value_belongs_to_a_different'));
            $this->check(! in_array((string) $id, $result, true), $path, __('configurator.validation.select_distinct_included_values'));
            $result[] = (string) $id;
        }

        return $result;
    }

    /** @param array<string, mixed> $row @param array<string, array<string, bool>> $seen */
    private function claim(array $row, string $type, array &$seen, string $path): string
    {
        $value = $row['id'] ?? null;
        $this->check((is_int($value) || is_string($value)) && preg_match('/\A(?:[1-9][0-9]*|new:[A-Za-z0-9_-]{1,80})\z/D', (string) $value) === 1, $path.'.id', __('configurator.validation.provide_a_stable_row_id_or_a'));
        $id = (string) $value;
        $this->check(! isset($seen[$type][$id]), $path.'.id', __('configurator.validation.this_row_id_is_repeated'));
        $seen[$type][$id] = true;

        return $id;
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $rows, string $path, int $minimum = 0): array
    {
        $this->check(is_array($rows) && array_is_list($rows) && count($rows) >= $minimum && count($rows) <= 500, $path, __('configurator.validation.provide_a_complete_list_with_at_least', ['value1' => $minimum]));
        foreach ($rows as $row) {
            $this->check(is_array($row), $path, __('configurator.validation.each_row_must_be_an_object'));
        }

        return $rows;
    }

    /** @param array<string, mixed> $row @param list<string> $allowed */
    private function keys(array $row, array $allowed, string $path): void
    {
        $this->check(array_diff(array_keys($row), $allowed) === [] && array_diff($allowed, array_keys($row)) === [], $path, __('configurator.validation.provide_the_complete_row_with_only_the'));
    }

    private function text(mixed $value, string $path, bool $nullable = true, int $maximum = 255, bool $allowEmpty = false): ?string
    {
        if ($nullable && $value === null) {
            return null;
        }
        $this->check(is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen($value) <= $maximum && ($allowEmpty || $value !== ''), $path, __('configurator.validation.provide_valid_text_up_to_characters', ['value1' => $maximum]));

        return $value;
    }

    private function integer(mixed $value, string $path, int $minimum = 0): int
    {
        $this->check((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= $minimum && (int) $value <= 2147483647, $path, __('configurator.validation.provide_a_whole_number_from_to_2147483647', ['value1' => $minimum]));

        return (int) $value;
    }

    private function boolean(mixed $value, string $path): bool
    {
        $this->check(is_bool($value), $path, __('configurator.validation.provide_true_or_false'));

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
