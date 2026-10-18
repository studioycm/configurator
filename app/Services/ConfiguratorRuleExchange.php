<?php

namespace App\Services;

use App\ConditionOperator;
use App\ConditionSource;
use App\MappingTargetBehavior;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\RuleEffectKind;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ConfiguratorRuleExchange
{
    public const COLUMNS = ['format_version', 'import', 'operation', 'configurator_id', 'rule_key', 'rule_id', 'expected_revision', 'rule_label', 'rule_kind', 'enabled', 'priority', 'row_key', 'row_type', 'row_order', 'condition_group', 'group_operator', 'condition_source', 'condition_field', 'condition_operator', 'condition_values', 'target_attribute_key', 'effect_kind', 'target_scope', 'target_values', 'display_value', 'mapping_label', 'mapping_order', 'other_values'];

    public const HELPERS = ['labels' => ['helper_configurator_name', 'helper_source_labels_snapshot', 'helper_target_labels_snapshot'], 'codes' => ['helper_reference_codes_snapshot'], 'summaries' => ['helper_rule_summary_snapshot'], 'diagnostics' => ['helper_review_snapshot']];

    /** @var array<int, array<string, mixed>> */
    private array $snapshots = [];

    public function __construct(private ConfiguratorDefinitionLoader $loader) {}

    /** @return array{rows: list<array<string, string>>, helpers: list<array<string, string>>, lookups: list<array<string, string>>} */
    public function snapshot(ConfiguratorRule $record): array
    {
        $snapshot = $this->snapshots[$record->configurator_id] ??= DB::transaction(function () use ($record): array {
            if (DB::getDriverName() === 'mysql' && ! in_array(DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level, ['REPEATABLE-READ', 'SERIALIZABLE'], true)) {
                throw new RuntimeException('Rules export requires a consistent MySQL transaction snapshot.');
            }
            $configurator = Configurator::findOrFail($record->configurator_id);
            $draft = $this->loader->draft($configurator);
            [$canonicalAttributes, $canonicalOptions] = $this->loader->canonical($draft);
            $attributes = [];
            $options = [];
            $lookups = [];
            foreach ($draft['attributes'] as $attribute) {
                $canonical = $canonicalAttributes[$attribute['attribute_id']] ?? throw new RuntimeException('Missing Attribute reference.');
                $attributes[$attribute['id']] = ['key' => $canonical['key'], 'label' => $attribute['label_override'] ?? $canonical['label']];
                foreach ($attribute['options'] as $option) {
                    $value = $canonicalOptions[$option['option_id']] ?? throw new RuntimeException('Missing Attribute-Value reference.');
                    if (! is_string($value['semantic_id'] ?? null) || $value['semantic_id'] === '') {
                        throw new RuntimeException('An included Value is missing its semantic ID.');
                    }
                    $options[$option['id']] = ['attribute_id' => $attribute['id'], 'semantic_id' => $value['semantic_id'], 'label' => $option['label_override'] ?? $value['label'], 'code' => $value['code']];
                    $lookups[] = ['configurator_id' => (string) $configurator->id, 'configurator_name' => $configurator->name, 'reference_type' => 'AttributeValue', 'attribute_key' => $canonical['key'], 'attribute_label' => $attributes[$attribute['id']]['label'], 'value_semantic_id' => $value['semantic_id'], 'value_label' => $options[$option['id']]['label'], 'reference_code' => $value['code'], 'field' => '', 'literal' => ''];
                }
                if ($attribute['options'] === []) {
                    $lookups[] = ['configurator_id' => (string) $configurator->id, 'configurator_name' => $configurator->name, 'reference_type' => 'Attribute', 'attribute_key' => $canonical['key'], 'attribute_label' => $attributes[$attribute['id']]['label'], 'value_semantic_id' => '', 'value_label' => '', 'reference_code' => '', 'field' => '', 'literal' => ''];
                }
            }
            foreach (CatalogImportParser::propertyKeys() as $property) {
                $lookups[] = $this->fieldLookup($configurator, 'ProductProperty', $property, '');
            }
            $choices = app(ConfiguratorDefinitionCompiler::class)->resolveContext($draft['context_schema'], $draft['hidden_context_options'], CatalogContextSettings::current()->choices);
            foreach (['territory' => 'Territory', 'application' => 'Application'] as $dimension => $source) {
                foreach ($choices[$dimension] ?? [] as $choice) {
                    $lookups[] = $this->fieldLookup($configurator, $source, $dimension, $choice['value']);
                }
            }

            $tokens = ['format_version' => ['rules-rows-v1'], 'import' => ['0', '1'], 'operation' => ['Create', 'Update'], 'rule_kind' => ['Mapping', 'Advanced'], 'enabled' => ['0', '1'], 'row_type' => ['Condition', 'Effect', 'MappingSet'], 'group_operator' => ['All', 'Any'], 'condition_source' => array_column(ConditionSource::cases(), 'value'), 'condition_operator' => array_column(ConditionOperator::cases(), 'value'), 'effect_kind' => array_column(RuleEffectKind::cases(), 'value'), 'target_scope' => ['Options', 'Attribute'], 'other_values' => array_column(MappingTargetBehavior::cases(), 'value')];
            foreach ($tokens as $field => $values) {
                foreach ($values as $value) {
                    $lookups[] = $this->fieldLookup($configurator, 'Token', $field, $value);
                }
            }

            return compact('configurator', 'draft', 'attributes', 'options', 'lookups');
        });
        $rule = collect($snapshot['draft']['rules'])->firstWhere('id', (string) $record->id);
        if ($rule === null) {
            throw new RuntimeException('The rule no longer exists in this Configurator snapshot.');
        }
        $rows = $this->rows($snapshot['configurator']->id, $rule, $snapshot['attributes'], $snapshot['options']);
        $helpers = array_map(fn (array $row): array => $this->helpers($row, $snapshot), $rows);

        return ['rows' => $rows, 'helpers' => $helpers, 'lookups' => $snapshot['lookups']];
    }

    /** @param array<string, string> $row @param array<string, mixed> $snapshot @return array<string, string> */
    private function helpers(array $row, array $snapshot): array
    {
        $attributes = $snapshot['attributes'];
        $options = collect($snapshot['options']);
        $source = collect($attributes)->firstWhere('key', $row['condition_field']);
        $target = collect($attributes)->firstWhere('key', $row['target_attribute_key']);
        $sourceValues = in_array($row['condition_source'], ['SelectionOption', 'SelectionCode'], true) ? $this->decodeList($row['condition_values']) : [];
        $targetValues = $row['target_values'] === '' ? [] : $this->decodeList($row['target_values']);
        $sources = $options->filter(fn (array $option): bool => $attributes[$option['attribute_id']]['key'] === $row['condition_field'] && in_array($option['semantic_id'], $sourceValues, true));
        $targets = $options->filter(fn (array $option): bool => $attributes[$option['attribute_id']]['key'] === $row['target_attribute_key'] && in_array($option['semantic_id'], $targetValues, true));
        $label = fn (?array $attribute, Collection $values): string => $attribute === null ? '' : $attribute['label'].' ['.$attribute['key'].']'.($values->isEmpty() ? '' : ': '.$values->map(fn (array $option): string => $option['label'].' ['.$option['semantic_id'].']')->implode('; '));

        return ['helper_configurator_name' => $snapshot['configurator']->name, 'helper_source_labels_snapshot' => $label($source, $sources), 'helper_target_labels_snapshot' => $label($target, $targets), 'helper_reference_codes_snapshot' => $sources->merge($targets)->unique(fn (array $option): string => $option['attribute_id'].'.'.$option['semantic_id'])->map(fn (array $option): string => $attributes[$option['attribute_id']]['key'].'.'.$option['semantic_id'].' = '.$option['code'].' ('.$option['label'].')')->implode('; '), 'helper_rule_summary_snapshot' => $row['rule_kind'].' · '.$row['rule_label'].' · '.$row['row_type'].' · '.($row['effect_kind'] ?: $row['condition_operator']), 'helper_review_snapshot' => __('data_exchange.rules.snapshot_note')];
    }

    /** @return array<string, string> */
    private function fieldLookup(Configurator $configurator, string $source, string $field, string $literal): array
    {
        return ['configurator_id' => (string) $configurator->id, 'configurator_name' => $configurator->name, 'reference_type' => $source, 'attribute_key' => '', 'attribute_label' => '', 'value_semantic_id' => '', 'value_label' => '', 'reference_code' => '', 'field' => $field, 'literal' => $literal];
    }

    /** @param array<string, mixed> $rule @param array<string, array<string, string>> $attributes @param array<string, array<string, string>> $options @return list<array<string, string>> */
    private function rows(int $configuratorId, array $rule, array $attributes, array $options): array
    {
        $base = array_fill_keys(self::COLUMNS, '');
        $base = array_replace($base, ['format_version' => 'rules-rows-v1', 'import' => '0', 'operation' => 'Update', 'configurator_id' => (string) $configuratorId, 'rule_key' => 'rule_'.$rule['id'], 'rule_id' => $rule['id'], 'expected_revision' => hash('sha256', json_encode($rule, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'rule_label' => $rule['label'], 'rule_kind' => $rule['kind'], 'enabled' => $rule['is_active'] ? '1' : '0', 'priority' => (string) $rule['priority']]);
        $rows = [];
        foreach ($rule['conditions'] as $condition) {
            if (isset($condition['conditions'])) {
                if ($condition['conditions'] === []) {
                    throw new RuntimeException('An empty condition group cannot be represented by rules-rows-v1.');
                }
                foreach ($condition['conditions'] as $predicate) {
                    $rows[] = array_replace($base, $this->condition($predicate, $attributes, $options), ['condition_group' => 'g_'.$condition['id'], 'group_operator' => $condition['operator']]);
                }
            } else {
                $rows[] = array_replace($base, $this->condition($condition, $attributes, $options));
            }
        }
        foreach ($rule['effects'] as $effect) {
            $target = $this->attribute($effect['target_configurator_attribute_id'], $attributes);
            $rows[] = array_replace($base, ['row_key' => 'e_'.$effect['id'], 'row_type' => 'Effect', 'target_attribute_key' => $target['key'], 'effect_kind' => $effect['kind'], 'target_scope' => $effect['target_scope'], 'target_values' => $this->optionValues($effect['option_ids'], $effect['target_configurator_attribute_id'], $options), 'display_value' => $effect['display_value'] ?? '']);
        }
        foreach ($rule['sets'] as $set) {
            $driver = $this->attribute($rule['driver_configurator_attribute_id'], $attributes);
            $target = $this->attribute($rule['target_configurator_attribute_id'], $attributes);
            $rows[] = array_replace($base, ['row_key' => 's_'.$set['id'], 'row_type' => 'MappingSet', 'condition_source' => 'SelectionOption', 'condition_field' => $driver['key'], 'condition_values' => $this->optionValues($set['source_option_ids'], $rule['driver_configurator_attribute_id'], $options), 'target_attribute_key' => $target['key'], 'target_values' => $this->optionValues($set['target_option_ids'], $rule['target_configurator_attribute_id'], $options), 'mapping_label' => $set['label'] ?? '', 'mapping_order' => (string) $set['sort_order'], 'other_values' => $set['disallowed_target_behavior']]);
        }
        if ($rows === []) {
            throw new RuntimeException('An empty rule cannot be represented by rules-rows-v1.');
        }
        foreach ($rows as $index => &$row) {
            $row['row_order'] = (string) $index;
        }

        return $rows;
    }

    /** @param array<string, mixed> $condition @param array<string, array<string, string>> $attributes @param array<string, array<string, string>> $options @return array<string, string> */
    private function condition(array $condition, array $attributes, array $options): array
    {
        $selection = in_array($condition['source_kind'], ['SelectionOption', 'SelectionCode'], true);
        $field = $selection ? $this->attribute($condition['source_configurator_attribute_id'], $attributes)['key'] : ($condition['property_key'] ?? $condition['context_dimension']);
        $values = $selection ? $this->optionValues($condition['option_ids'], $condition['source_configurator_attribute_id'], $options) : (is_array($condition['operand']) ? $this->encodeList($condition['operand']) : $condition['operand']);

        return ['row_key' => 'c_'.$condition['id'], 'row_type' => 'Condition', 'condition_source' => $condition['source_kind'], 'condition_field' => $field, 'condition_operator' => $condition['operator'], 'condition_values' => $values];
    }

    /** @param array<string, array<string, string>> $attributes @return array<string, string> */
    private function attribute(?string $id, array $attributes): array
    {
        return $attributes[$id] ?? throw new RuntimeException('The rule references an Attribute outside this Configurator.');
    }

    /** @param list<string> $ids @param array<string, array<string, string>> $options */
    private function optionValues(array $ids, ?string $attributeId, array $options): string
    {
        $values = [];
        foreach ($ids as $id) {
            $option = $options[$id] ?? throw new RuntimeException('The rule references a Value outside this Configurator.');
            if ($option['attribute_id'] !== $attributeId || ! is_string($option['semantic_id']) || $option['semantic_id'] === '') {
                throw new RuntimeException('The rule has an invalid Attribute-Value reference.');
            }
            $values[] = $option['semantic_id'];
        }

        return $this->encodeList($values);
    }

    /** @param list<string> $values */
    public function encodeList(array $values): string
    {
        return implode('|', array_map(fn (string $value): string => str_replace(['\\', '|'], ['\\\\', '\\|'], $value), $values));
    }

    /** @return list<string> */
    public function decodeList(string $value): array
    {
        $parts = [''];
        $index = 0;
        for ($position = 0; $position < strlen($value); $position++) {
            $character = $value[$position];
            if ($character === '\\') {
                $character = $value[++$position] ?? '';
                if (! in_array($character, ['\\', '|'], true)) {
                    throw new InvalidArgumentException('Unknown or trailing list escape.');
                }
            } elseif ($character === '|') {
                $parts[++$index] = '';

                continue;
            }
            $parts[$index] .= $character;
        }

        return $parts;
    }
}
