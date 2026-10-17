<?php

namespace App\Services;

use App\ConditionOperator;
use App\ConditionSource;
use App\ConfigInputType;
use App\DTO\ConfiguratorDefinition;
use App\DTO\ConfiguratorEvaluationResult;
use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\Product;
use App\RuleEffectKind;
use App\RuleTargetScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfiguratorEditorChanges
{
    public const array OVERVIEW_FIELDS = ['name', 'description', 'context_schema', 'hidden_context_options'];

    public const array ATTRIBUTE_FIELDS = ['label_override', 'input_type', 'help_text', 'default_configurator_option_id'];

    public const array VALUE_FIELDS = ['label_override', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default'];

    public const array RULE_FIELDS = ['label', 'is_active', 'driver_configurator_attribute_id', 'target_configurator_attribute_id', 'conditions', 'effects', 'sets'];

    public function __construct(private ConfiguratorDefinitionLoader $loader, private ConfiguratorDefinitionCompiler $compiler, private ConfiguratorRuleDraft $rules) {}

    /** @param list<array<string, mixed>> $rows @return array<string, mixed> */
    public function capture(int $configuratorId, array $rows): array
    {
        Gate::authorize('manage-catalog');
        $payload = ['configurator_id' => $configuratorId, 'rows' => $rows];

        return [...$payload, 'signature' => $this->signature($payload)];
    }

    /** @param array<string, mixed> $capture @return list<array<string, mixed>> */
    public function verifiedRows(int $configuratorId, array $capture): array
    {
        Gate::authorize('manage-catalog');
        $payload = Arr::only($capture, ['configurator_id', 'rows']);
        if (($payload['configurator_id'] ?? null) !== $configuratorId || ! is_array($payload['rows'] ?? null)
            || count($payload['rows']) > 500 || ! is_string($capture['signature'] ?? null)
            || ! hash_equals($this->signature($payload), $capture['signature'])) {
            throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]);
        }

        return $payload['rows'];
    }

    /** @param list<array<string, mixed>> $captures @return array{data: array<string, mixed>, changes: list<array<string, mixed>>} */
    public function prepare(Configurator $configurator, array $captures): array
    {
        Gate::authorize('manage-catalog');
        if (count($captures) > 3) {
            throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]);
        }
        $data = $this->loader->draft($configurator);
        $changes = [];
        $seen = [];
        foreach ($captures as $capture) {
            foreach ($this->verifiedRows($configurator->id, $capture) as $row) {
                $type = $row['type'];
                $id = (string) $row['id'];
                $key = $type.':'.$id;
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]);
                }
                $seen[$key] = true;
                try {
                    $before = $this->normalize($type, $row['before']);
                    $after = $this->normalize($type, $row['after']);
                } catch (ValidationException $exception) {
                    if ($type !== 'rule') {
                        throw $exception;
                    }
                    $errors = [];
                    foreach ($exception->errors() as $path => $messages) {
                        $errors[$path] = array_map(fn (string $message): string => $this->errorLocation('rules.0.'.$path, ['rules' => [$row['after']]], [], []).': '.$message, $messages);
                    }
                    throw ValidationException::withMessages($errors);
                }
                $new = $type === 'rule' && str_starts_with($id, 'new:');
                if ($new) {
                    $after['id'] = $id;
                    $after['priority'] = $data['rules'] === [] ? 0 : max(array_column($data['rules'], 'priority')) + 1;
                    $data['rules'][] = $after;
                    $fields = array_values(array_diff(['kind', ...self::RULE_FIELDS], $after['kind'] === 'Mapping' ? ['effects'] : ['driver_configurator_attribute_id', 'target_configurator_attribute_id', 'sets']));
                    $changes[] = ['type' => $type, 'id' => $id, 'label' => $after['label'] ?: __('configurator.preview.new_rule'), 'new' => true, 'fields' => $this->differences([], $after, $fields)];

                    continue;
                }
                $path = $this->rowPath($data, $type, $id);
                $current = $path === '' ? $data : Arr::get($data, $path);
                if (! is_array($current) || ($type === 'rule' && ($after['kind'] ?? null) !== $current['kind'])) {
                    throw ValidationException::withMessages(['preview' => __('configurator.preview.editor_conflict')]);
                }
                $fields = $this->differences($before, $after, $this->fields($type));
                foreach ($fields as $field => $difference) {
                    if (($current[$field] ?? null) == $difference['after']) {
                        unset($fields[$field]);

                        continue;
                    }
                    if (($current[$field] ?? null) != $difference['before']) {
                        throw ValidationException::withMessages(['preview' => __('configurator.preview.editor_conflict')]);
                    }
                    $current[$field] = $difference['after'];
                }
                if ($fields === []) {
                    continue;
                }
                if ($path === '') {
                    $data = $current;
                } else {
                    Arr::set($data, $path, $current);
                }
                $changes[] = ['type' => $type, 'id' => $id, 'label' => $this->label($type, $id, $current), 'new' => false, 'fields' => $fields];
            }
        }
        [$attributes, $options] = $this->loader->canonical($data);
        try {
            $compiled = $this->compiler->compile($data, $attributes, $options, authoring: true, globalContext: CatalogContextSettings::current()->choices);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $path => $messages) {
                $errors[$path] = array_map(fn (string $message): string => $this->errorLocation($path, $data, $attributes, $options).': '.$message, $messages);
            }
            throw ValidationException::withMessages($errors);
        }
        foreach ($changes as &$change) {
            foreach ($change['fields'] as $field => &$difference) {
                $difference['before_text'] = $this->describe($field, $difference['before'], $compiled);
                $difference['after_text'] = $this->describe($field, $difference['after'], $compiled);
            }
            unset($difference);
        }
        unset($change);

        return ['data' => $compiled->data, 'changes' => $changes];
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function normalize(string $type, array $state): array
    {
        if ($state === []) {
            return [];
        }
        if ($type === 'rule') {
            $state = isset($state['condition_blocks']) ? $this->rules->toRule($state) : $state;

            return Arr::only($state, ['id', 'kind', 'priority', ...self::RULE_FIELDS]);
        }
        $state = Arr::only($state, $this->fields($type));
        foreach (['label_override', 'display_value_override', 'help_text', 'hint', 'description'] as $field) {
            if (array_key_exists($field, $state) && blank($state[$field])) {
                $state[$field] = null;
            }
        }
        if (($state['input_type'] ?? null) instanceof ConfigInputType) {
            $state['input_type'] = $state['input_type']->value;
        }
        foreach (['territory', 'application'] as $dimension) {
            if (isset($state['context_schema'][$dimension])) {
                $state['context_schema'][$dimension] = array_values($state['context_schema'][$dimension]);
            }
        }

        return $state;
    }

    public function fingerprint(Configurator $configurator, int $productId, bool $lock = false): string
    {
        $data = $this->loader->draft($configurator);
        [$attributes, $options] = $this->loader->canonical($data, $lock);
        $product = Product::whereKey($productId)->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
        $group = $product->group()->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
        $context = CatalogContextSettings::current(lock: $lock)->choices;

        return hash('sha256', json_encode([$data, $attributes, $options, $configurator->is_active, $product->getAttributes(), $group->getAttributes(), $context], JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    public function blockingIssues(ConfiguratorEvaluationResult $result): array
    {
        $issues = [];
        foreach ($result->diagnostics as $diagnostic) {
            if (in_array($diagnostic['code'], ['selection_adjusted', 'context_repaired', 'definition_changed'], true)) {
                continue;
            }
            if (! $result->isComplete || in_array($diagnostic['code'], ['presentation_priority_tie', 'interaction_rejected', 'selection_rejected', 'context_rejected'], true)) {
                $issues[] = $diagnostic['message'];
            }
        }
        if (! $result->isComplete && $issues === []) {
            $issues[] = __('configurator.preview.test_errors');
        }

        return array_values(array_unique($issues));
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @param list<string> $fields @return array<string, array{before: mixed, after: mixed}> */
    private function differences(array $before, array $after, array $fields): array
    {
        $changes = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $after) && (! array_key_exists($field, $before) || $before[$field] != $after[$field])) {
                $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $after[$field]];
            }
        }

        return $changes;
    }

    /** @param list<array<string, mixed>> $captures @return list<array<string, mixed>> */
    public function savedRows(int $configuratorId, array $captures): array
    {
        $rows = [];
        foreach ($captures as $capture) {
            $rows = [...$rows, ...$this->verifiedRows($configuratorId, $capture)];
        }

        return $rows;
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $tested @param array<string, mixed> $saved @return array<string, mixed> */
    public function rebase(string $type, array $current, array $tested, array $saved): array
    {
        $fields = $type === 'rule' ? array_diff([...self::RULE_FIELDS, 'condition_blocks'], ['conditions']) : $this->fields($type);
        foreach ($fields as $field) {
            if (array_key_exists($field, $current) && ($current[$field] ?? null) != ($tested[$field] ?? null)) {
                $saved[$field] = $current[$field];
            }
        }

        return $saved;
    }

    private function describe(string $field, mixed $value, ConfiguratorDefinition $definition): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? __('configurator.preview.yes') : __('configurator.preview.no');
        }
        if (is_string($value)) {
            $label = match ($field) {
                'source_kind' => ConditionSource::tryFrom($value)?->getLabel(),
                'operator' => ConditionOperator::tryFrom($value)?->getLabel(),
                'kind' => RuleEffectKind::tryFrom($value)?->getLabel(),
                'target_scope' => RuleTargetScope::tryFrom($value)?->getLabel(),
                'input_type' => ConfigInputType::tryFrom($value)?->getLabel(),
                default => null,
            };
            if ($label !== null) {
                return (string) $label;
            }
        }
        if (str_ends_with($field, 'configurator_attribute_id') && is_scalar($value)) {
            return $definition->attributes[(string) $value]->label ?? '—';
        }
        if (in_array($field, ['default_configurator_option_id', 'option_ids', 'source_option_ids', 'target_option_ids'], true)) {
            $labels = [];
            foreach ((array) $value as $id) {
                foreach ($definition->attributes as $attribute) {
                    if (isset($attribute->options[(string) $id])) {
                        $option = $attribute->options[(string) $id];
                        $labels[] = $option->label.' · '.$option->code.(filled($option->semanticId) ? ' · '.$option->semanticId : '');
                    }
                }
            }

            return implode(', ', $labels) ?: '—';
        }
        if (is_array($value)) {
            if ($value === []) {
                return $field === 'conditions' ? __('configurator.preview.unconditional') : '—';
            }
            $lines = [];
            foreach ($value as $key => $child) {
                if (in_array($key, ['id', 'sort_order', 'priority'], true) || $child === null || $child === '' || $child === []) {
                    continue;
                }
                $description = $this->describe(is_int($key) ? $field : $key, $child, $definition);
                $lines[] = is_int($key) ? $description : __('configurator.preview.fields.'.$key).': '.$description;
            }

            return implode("\n", $lines) ?: '—';
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $data @param array<int, array<string, mixed>> $attributes @param array<int, array<string, mixed>> $options */
    private function errorLocation(string $path, array $data, array $attributes, array $options): string
    {
        $parts = explode('.', $path);
        $labels = [];
        if (in_array($parts[0], ['rules', 'attributes'], true) && isset($parts[1]) && ctype_digit($parts[1])) {
            $type = array_shift($parts);
            $row = $data[$type][(int) array_shift($parts)] ?? [];
            if ($type === 'rules') {
                $labels[] = __('configurator.preview.types.rule').' “'.($row['label'] ?? __('configurator.preview.new_rule')).'”';
            } else {
                $labels[] = __('configurator.preview.types.attribute').' “'.($row['label_override'] ?: ($attributes[$row['attribute_id']]['label'] ?? '—')).'”';
                if (($parts[0] ?? null) === 'options' && isset($parts[1])) {
                    array_shift($parts);
                    $option = $row['options'][(int) array_shift($parts)] ?? [];
                    $canonical = $options[$option['option_id'] ?? ''] ?? [];
                    $labels[] = __('configurator.preview.types.value').' “'.($canonical['label'] ?? '—').'”';
                }
            }
        } else {
            $labels[] = __('configurator.workspace.tabs.overview');
        }
        foreach ($parts as $part) {
            if (in_array($part, ['data', 'rule'], true)) {
                continue;
            }
            if (ctype_digit($part)) {
                $last = array_key_last($labels);
                $labels[$last] .= ' '.((int) $part + 1);
            } else {
                $key = 'configurator.preview.fields.'.$part;
                $labels[] = __($key) === $key ? Str::headline($part) : __($key);
            }
        }

        return implode(' · ', $labels);
    }

    /** @return list<string> */
    private function fields(string $type): array
    {
        return match ($type) {
            'overview' => self::OVERVIEW_FIELDS, 'attribute' => self::ATTRIBUTE_FIELDS, 'value' => self::VALUE_FIELDS, 'rule' => self::RULE_FIELDS,
            default => throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]),
        };
    }

    /** @param array<string, mixed> $data */
    private function rowPath(array $data, string $type, string $id): string
    {
        if ($type === 'overview' && $id === 'overview') {
            return '';
        }
        foreach ($data['rules'] as $index => $rule) {
            if ($type === 'rule' && $rule['id'] === $id) {
                return 'rules.'.$index;
            }
        }
        foreach ($data['attributes'] as $index => $attribute) {
            if ($type === 'attribute' && $attribute['id'] === $id) {
                return 'attributes.'.$index;
            }
            foreach ($attribute['options'] as $valueIndex => $value) {
                if ($type === 'value' && $value['id'] === $id) {
                    return 'attributes.'.$index.'.options.'.$valueIndex;
                }
            }
        }

        throw ValidationException::withMessages(['preview' => __('configurator.preview.editor_conflict')]);
    }

    /** @param array<string, mixed> $row */
    private function label(string $type, string $id, array $row): string
    {
        return match ($type) {
            'overview' => __('configurator.workspace.tabs.overview'),
            'rule' => $row['label'],
            'attribute' => $row['label_override'] ?: Attribute::findOrFail($row['attribute_id'])->label,
            'value' => Option::with('value')->findOrFail($row['option_id'])->value->labelWithSemanticId($row['label_override']),
        };
    }

    /** @param array<string, mixed> $payload */
    private function signature(array $payload): string
    {
        return hash_hmac('sha256', json_encode([auth()->id(), $payload], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
