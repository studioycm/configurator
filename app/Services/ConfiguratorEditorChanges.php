<?php

namespace App\Services;

use App\ConditionOperator;
use App\ConditionSource;
use App\ConfigInputType;
use App\DTO\ConfiguratorEvaluationResult;
use App\MappingTargetBehavior;
use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\RuleEffectKind;
use App\RuleTargetScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfiguratorEditorChanges
{
    public const array OVERVIEW_FIELDS = ['name', 'description', 'context_schema', 'hidden_context_options'];

    public const array ATTRIBUTE_FIELDS = ['label_override', 'input_type', 'help_text', 'default_configurator_option_id'];

    public const array VALUE_FIELDS = ['label_override', 'subtitle_mode', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default'];

    public const array RULE_FIELDS = ['label', 'is_active', 'driver_configurator_attribute_id', 'target_configurator_attribute_id', 'conditions', 'effects', 'sets'];

    public function __construct(private ConfiguratorDefinitionLoader $loader, private ConfiguratorDefinitionCompiler $compiler, private ConfiguratorRuleDraft $rules) {}

    /** @param list<array<string, mixed>> $rows @return array<string, mixed> */
    public function capture(User $actor, int $configuratorId, array $rows): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        foreach ($rows as &$row) {
            if (($row['type'] ?? null) === 'value') {
                foreach (['before', 'after'] as $side) {
                    if (is_array($row[$side] ?? null)) {
                        $row[$side] = Arr::only($row[$side], self::VALUE_FIELDS);
                    }
                }
            }
        }
        unset($row);
        $payload = ['configurator_id' => $configuratorId, 'rows' => $rows];

        return [...$payload, 'signature' => $this->signature($actor, $payload)];
    }

    /** @param array<string, mixed> $capture @return list<array<string, mixed>> */
    public function verifiedRows(User $actor, int $configuratorId, array $capture): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $payload = Arr::only($capture, ['configurator_id', 'rows']);
        if (($payload['configurator_id'] ?? null) !== $configuratorId || ! is_array($payload['rows'] ?? null)
            || ! array_is_list($payload['rows']) || count($payload['rows']) > 500 || ! is_string($capture['signature'] ?? null)
            || ! hash_equals($this->signature($actor, $payload), $capture['signature'])) {
            throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]);
        }

        foreach ($payload['rows'] as $row) {
            if (! is_array($row) || ! in_array($row['type'] ?? null, ['overview', 'attribute', 'value', 'rule'], true)
                || (! is_string($row['id'] ?? null) && ! is_int($row['id'] ?? null))
                || ! is_array($row['before'] ?? null) || ! is_array($row['after'] ?? null)
                || ($row['before'] !== [] && array_is_list($row['before'])) || ($row['after'] !== [] && array_is_list($row['after']))
                || ! $this->boundedState($row['before']) || ! $this->boundedState($row['after'])) {
                throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]);
            }
            $id = (string) $row['id'];
            if (strlen($id) > 128 || ($row['type'] === 'overview' ? $id !== 'overview' : (! ctype_digit($id) || (int) $id < 1) && ! ($row['type'] === 'rule' && str_starts_with($id, 'new:') && strlen($id) > 4))) {
                throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]);
            }
        }

        return $payload['rows'];
    }

    /** @param list<array<string, mixed>> $captures @return array{changes: array, errors: list<string>, unavailable: list<array{type:string,id:string}>, new_rules: bool, saved_rules: array, matches: bool} */
    public function compareSaved(User $actor, int $configuratorId, array $captures): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($actor, $configuratorId, $captures): array {
            $rows = $this->savedRows($actor, $configuratorId, $captures);
            $configurator = Configurator::findOrFail($configuratorId);
            $current = $this->loader->draft($configurator);
            $comparison = $unavailable = [];
            $newRules = false;
            foreach ($rows as $row) {
                $id = (string) $row['id'];
                if ($row['type'] === 'rule' && str_starts_with($id, 'new:')) {
                    $newRules = true;
                } else {
                    try {
                        $path = $this->rowPath($current, $row['type'], $id);
                    } catch (ValidationException) {
                        $unavailable[] = ['type' => $row['type'], 'id' => $id];

                        continue;
                    }
                    $row['before'] = $path === '' ? $current : Arr::get($current, $path);
                }
                $comparison[] = $row;
            }
            $currentCaptures = collect($comparison)->chunk(500)->map(fn ($chunk): array => $this->capture($actor, $configuratorId, $chunk->values()->all()))->values()->all();
            $inspection = $this->inspect($actor, $configurator, $currentCaptures);

            return [...$inspection, 'unavailable' => $unavailable, 'new_rules' => $newRules,
                'saved_rules' => $newRules ? array_map(fn (array $rule): array => Arr::only($rule, ['id', 'label', 'kind']), $current['rules']) : [],
                'matches' => $inspection['changes'] === [] && $unavailable === [] && ! $newRules];
        });
    }

    /** @param list<array<string, mixed>> $captures @return array{changes: list<array<string, mixed>>, errors: list<string>} */
    public function inspect(User $actor, Configurator $configurator, array $captures): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $rows = $this->savedRows($actor, $configurator->id, $captures);
        $data = $this->loader->draft($configurator);
        $changes = [];
        $errors = [];
        foreach ($rows as $row) {
            $type = $row['type'];
            $id = (string) $row['id'];
            $new = $type === 'rule' && str_starts_with($id, 'new:');
            $path = $new ? null : $this->rowPath($data, $type, $id);
            $current = $path === '' ? $data : ($path === null ? [] : Arr::get($data, $path));
            $invalid = false;
            $rowErrors = [];
            try {
                $before = $this->normalize($type, $row['before']);
                $after = $this->normalize($type, $row['after']);
            } catch (ValidationException $exception) {
                if ($type !== 'rule') {
                    throw $exception;
                }
                $invalid = true;
                $before = $this->rawRuleState($row['before']);
                $after = $this->rawRuleState($row['after']);
                if (! $new) {
                    $current = $this->rules->fromRule($current);
                }
                foreach ($exception->errors() as $errorPath => $messages) {
                    foreach ($messages as $message) {
                        $rowErrors[] = $this->errorLocation('rules.0.'.$errorPath, ['rules' => [$after]], [], []).': '.$message;
                    }
                }
            }
            $fields = $type === 'rule' && $new ? $this->newRuleFields($after) : $this->fields($type);
            if ($invalid && array_key_exists('condition_blocks', $after)) {
                $fields = [...array_values(array_diff($fields, ['conditions'])), 'condition_blocks'];
            }
            $fields = $this->differences($type, $new ? [] : $before, $after, $fields);
            $stale = ! $new && $type === 'rule' && ($after['kind'] ?? null) !== $current['kind'];
            if (! $new) {
                foreach ($fields as $field => $difference) {
                    if ($this->sameMeaning($type, Arr::only($current, [$field]), Arr::only($after, [$field]))) {
                        unset($fields[$field]);
                    } elseif (! $this->sameMeaning($type, Arr::only($current, [$field]), Arr::only($before, [$field]))) {
                        $stale = true;
                    }
                }
            }
            if ($stale) {
                $rowErrors[] = $this->label($type, $id, $current).': '.__('configurator.preview.editor_conflict');
            }
            if ($fields === [] && ! $invalid && ! $stale) {
                continue;
            }
            $display = array_replace($current, $after);
            if (! $new && ! $invalid) {
                if ($path === '') {
                    $data = array_replace($data, Arr::only($after, array_keys($fields)));
                } else {
                    Arr::set($data, $path, array_replace($current, Arr::only($after, array_keys($fields))));
                }
            }
            $changes[] = ['type' => $type, 'id' => $id, 'label' => $new ? (is_string($after['label'] ?? null) && filled($after['label']) ? $after['label'] : __('configurator.preview.new_rule')) : $this->label($type, $id, $display), 'new' => $new, 'fields' => $fields, 'invalid' => $invalid, 'stale' => $stale, 'errors' => $rowErrors];
            $errors = [...$errors, ...$rowErrors];
        }
        [$attributes, $options] = $this->loader->canonical($data);

        return ['changes' => $this->describeChanges($changes, $data, $attributes, $options), 'errors' => array_values(array_unique($errors))];
    }

    /** @param list<array<string, mixed>> $captures */
    public function captureHash(User $actor, int $configuratorId, array $captures): string
    {
        $rows = $this->savedRows($actor, $configuratorId, $captures);
        usort($rows, fn (array $a, array $b): int => [$a['type'], (string) $a['id']] <=> [$b['type'], (string) $b['id']]);

        return hash('sha256', json_encode([$configuratorId, $rows], JSON_THROW_ON_ERROR));
    }

    /** @param list<array<string, mixed>> $captures @return array{data: array<string, mixed>, changes: list<array<string, mixed>>} */
    public function prepare(User $actor, Configurator $configurator, array $captures): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $rows = $this->savedRows($actor, $configurator->id, $captures);
        $data = $this->loader->draft($configurator);
        $changes = [];
        foreach ($rows as $row) {
            $type = $row['type'];
            $id = (string) $row['id'];
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
                $fields = $this->newRuleFields($after);
                $changes[] = ['type' => $type, 'id' => $id, 'label' => ($after['label'] !== '' ? $after['label'] : __('configurator.preview.new_rule')), 'new' => true, 'fields' => $this->differences($type, [], $after, $fields)];

                continue;
            }
            $path = $this->rowPath($data, $type, $id);
            $current = $path === '' ? $data : Arr::get($data, $path);
            if (! is_array($current) || ($type === 'rule' && ($after['kind'] ?? null) !== $current['kind'])) {
                throw ValidationException::withMessages(['preview' => __('configurator.preview.editor_conflict')]);
            }
            $fields = $this->differences($type, $before, $after, $this->fields($type));
            foreach ($fields as $field => $difference) {
                if ($this->sameMeaning($type, Arr::only($current, [$field]), Arr::only($after, [$field]))) {
                    unset($fields[$field]);

                    continue;
                }
                if (! $this->sameMeaning($type, Arr::only($current, [$field]), Arr::only($before, [$field]))) {
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

        return ['data' => $compiled->data, 'changes' => $this->describeChanges($changes, $compiled->data, $attributes, $options)];
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
        if ($type === 'value' && (array_key_exists('subtitle_mode', $state) || array_key_exists('display_value_override', $state))) {
            $state = [...$state, ...$this->compiler->normalizeSubtitle($state, 'value')];
        }
        foreach (['label_override', 'help_text', 'hint', 'description'] as $field) {
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

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    public function sameMeaning(string $type, array $left, array $right): bool
    {
        return $this->comparisonState($type, $left) === $this->comparisonState($type, $right);
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function comparisonState(string $type, array $state): array
    {
        if ($type === 'rule') {
            if (isset($state['conditions']) && is_array($state['conditions']) && ! array_key_exists('condition_blocks', $state)) {
                $hadSets = array_key_exists('sets', $state);
                $state = $this->rules->fromRule([...$state, 'sets' => $state['sets'] ?? []]);
                if (! $hadSets) {
                    unset($state['sets']);
                }
            }
            $state = $this->rawRuleState($state);
            foreach ($state['sets'] ?? [] as $key => $set) {
                if (is_array($set)) {
                    unset($set['sort_order']);
                    if (! array_key_exists('disallowed_target_behavior', $set)) {
                        $set['disallowed_target_behavior'] = MappingTargetBehavior::Disable->value;
                    }
                    if (($set['label'] ?? null) === '') {
                        $set['label'] = null;
                    }
                    $state['sets'][$key] = $set;
                }
            }
            foreach ($state['effects'] ?? [] as $key => $effect) {
                if (is_array($effect) && ($effect['display_value'] ?? null) === '') {
                    $state['effects'][$key]['display_value'] = null;
                }
            }
        } else {
            try {
                $state = $this->normalize($type, $state);
            } catch (ValidationException) {
                $state = Arr::only($state, $this->fields($type));
            }
        }

        return $this->comparisonValue($state);
    }

    /** @param array<string, mixed> $state */
    public function comparisonHash(string $type, array $state): string
    {
        return hash('sha256', json_encode($this->comparisonState($type, $state), JSON_THROW_ON_ERROR));
    }

    private function comparisonValue(mixed $value, string $field = '', bool $literal = false): mixed
    {
        $literal = $literal || in_array($field, ['operand', 'operand_text', 'operand_list', 'context_schema', 'hidden_context_options'], true);
        if (is_array($value)) {
            $ids = ! $literal && in_array($field, ['option_ids', 'source_option_ids', 'target_option_ids'], true);
            $result = [];
            foreach ($value as $key => $child) {
                $result[$key] = $this->comparisonValue($child, $ids ? 'id' : (string) $key, $literal);
            }
            if (! array_is_list($result)) {
                ksort($result, SORT_STRING);
            }

            return $result;
        }
        if (! $literal && ($field === 'id' || str_ends_with($field, '_id') || in_array($field, ['priority', 'sort_order'], true))
            && (is_int($value) || (is_string($value) && ctype_digit($value)))) {
            return ltrim((string) $value, '0') ?: '0';
        }
        if (! $literal && in_array($field, ['is_active', 'hidden_by_default', 'disabled_by_default'], true)
            && in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            return (bool) $value;
        }

        return $value;
    }

    public function fingerprint(Configurator $configurator, int $productId, bool $lock = false): string
    {
        $context = CatalogContextSettings::current(lock: $lock)->choices;
        $data = $this->loader->draft($configurator);
        [$attributes, $options] = $this->loader->canonical($data, $lock);
        if ($lock) {
            $groupId = Product::whereKey($productId)->value('group_id');
            $group = Group::whereKey($groupId)->lockForUpdate()->firstOrFail();
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();
            if ($product->group_id !== $group->id) {
                throw ValidationException::withMessages(['preview' => __('configurator.preview.saved_conflict')]);
            }
        } else {
            $product = Product::whereKey($productId)->firstOrFail();
            $group = $product->group()->firstOrFail();
        }

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
    private function differences(string $type, array $before, array $after, array $fields): array
    {
        $changes = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $after) && ! $this->sameMeaning($type, Arr::only($before, [$field]), Arr::only($after, [$field]))) {
                $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $after[$field]];
            }
        }

        return $changes;
    }

    /** @param list<array<string, mixed>> $captures @return list<array<string, mixed>> */
    public function savedRows(User $actor, int $configuratorId, array $captures): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (! array_is_list($captures) || count($captures) > 3) {
            throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]);
        }
        $rows = [];
        $seen = [];
        foreach ($captures as $capture) {
            if (! is_array($capture)) {
                throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]);
            }
            foreach ($this->verifiedRows($actor, $configuratorId, $capture) as $row) {
                $identity = $row['type'].':'.$row['id'];
                if (isset($seen[$identity])) {
                    throw ValidationException::withMessages(['preview' => __('configurator.preview.capture_expired')]);
                }
                $seen[$identity] = true;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $state */
    private function boundedState(array $state, int $depth = 0): bool
    {
        if ($depth > 12 || count($state) > 500) {
            return false;
        }
        foreach ($state as $value) {
            if (is_array($value) ? ! $this->boundedState($value, $depth + 1) : (! is_scalar($value) && $value !== null && ! $value instanceof ConfigInputType)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function rawRuleState(array $state): array
    {
        $state = Arr::only($state, ['id', 'kind', 'priority', ...self::RULE_FIELDS, 'condition_blocks']);
        foreach (['condition_blocks', 'conditions', 'effects', 'sets'] as $field) {
            if (is_array($state[$field] ?? null)) {
                $state[$field] = $this->rawRuleChildren(array_values($state[$field]));
            }
        }

        return $state;
    }

    /** @param array<array-key, mixed> $rows @return array<array-key, mixed> */
    private function rawRuleChildren(array $rows): array
    {
        $allowed = ['id', 'type', 'data', 'operator', 'conditions', 'source_kind', 'source_configurator_attribute_id', 'property_key', 'context_dimension', 'operand', 'operand_text', 'operand_list', 'option_ids', 'kind', 'target_configurator_attribute_id', 'target_scope', 'display_value', 'label', 'source_option_ids', 'target_option_ids', 'disallowed_target_behavior', 'sort_order'];
        $filtered = [];
        foreach ($rows as $key => $value) {
            if (is_int($key) || in_array($key, $allowed, true)) {
                $filtered[$key] = is_array($value) ? $this->rawRuleChildren($key === 'conditions' ? array_values($value) : $value) : $value;
            }
        }

        return $filtered;
    }

    /** @param array<string, mixed> $rule @return list<string> */
    private function newRuleFields(array $rule): array
    {
        return array_values(array_diff(['kind', ...self::RULE_FIELDS], ($rule['kind'] ?? null) === 'Mapping' ? ['effects'] : ['driver_configurator_attribute_id', 'target_configurator_attribute_id', 'sets']));
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $tested @param array<string, mixed> $saved @return array<string, mixed> */
    public function rebase(string $type, array $current, array $tested, array $saved): array
    {
        $fields = $type === 'rule' ? array_diff([...self::RULE_FIELDS, 'condition_blocks'], ['conditions']) : $this->fields($type);
        foreach ($fields as $field) {
            if (array_key_exists($field, $current) && ! $this->sameMeaning($type, Arr::only($current, [$field]), Arr::only($tested, [$field]))) {
                $saved[$field] = $current[$field];
            }
        }

        return $saved;
    }

    /** @param list<array<string, mixed>> $changes @param array<string, mixed> $data @param array<int, array<string, mixed>> $canonicalAttributes @param array<int, array<string, mixed>> $canonicalOptions @return list<array<string, mixed>> */
    private function describeChanges(array $changes, array $data, array $canonicalAttributes, array $canonicalOptions): array
    {
        $attributes = [];
        $options = [];
        foreach ($data['attributes'] as $attribute) {
            $attributes[$attribute['id']] = ($attribute['label_override'] !== null && $attribute['label_override'] !== '' ? $attribute['label_override'] : ($canonicalAttributes[$attribute['attribute_id']]['label'] ?? '—'));
            foreach ($attribute['options'] as $option) {
                $canonical = $canonicalOptions[$option['option_id']] ?? [];
                $options[$option['id']] = (($option['label_override'] !== null && $option['label_override'] !== '' ? $option['label_override'] : ($canonical['label'] ?? '—'))).' · '.($canonical['code'] ?? '—').(filled($canonical['semantic_id'] ?? null) ? ' · '.$canonical['semantic_id'] : '');
            }
        }
        foreach ($changes as &$change) {
            foreach ($change['fields'] as $field => &$difference) {
                $difference['before_text'] = $this->describe($field, $difference['before'], $attributes, $options);
                $difference['after_text'] = $this->describe($field, $difference['after'], $attributes, $options);
            }
            unset($difference);
        }
        unset($change);

        return $changes;
    }

    /** @param array<string, string> $attributes @param array<string, string> $options */
    private function describe(string $field, mixed $value, array $attributes, array $options): string
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
            return $attributes[(string) $value] ?? '—';
        }
        if (in_array($field, ['default_configurator_option_id', 'option_ids', 'source_option_ids', 'target_option_ids'], true)) {
            $labels = [];
            foreach ((array) $value as $id) {
                if (is_scalar($id) && isset($options[(string) $id])) {
                    $labels[] = $options[(string) $id];
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
                $description = $this->describe(is_int($key) ? $field : $key, $child, $attributes, $options);
                $translation = 'configurator.preview.fields.'.$key;
                $label = __($translation) === $translation ? Str::headline($key) : __($translation);
                $lines[] = is_int($key) ? $description : $label.': '.$description;
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
                $labels[] = __('configurator.preview.types.rule').' “'.(is_string($row['label'] ?? null) && filled($row['label']) ? $row['label'] : __('configurator.preview.new_rule')).'”';
            } else {
                $labels[] = __('configurator.preview.types.attribute').' “'.(($row['label_override'] !== null && $row['label_override'] !== '' ? $row['label_override'] : ($attributes[$row['attribute_id']]['label'] ?? '—'))).'”';
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
            'attribute' => ($row['label_override'] !== null && $row['label_override'] !== '' ? $row['label_override'] : Attribute::findOrFail($row['attribute_id'])->label),
            'value' => Option::with('value')->findOrFail($row['option_id'])->value->labelWithSemanticId($row['label_override']),
        };
    }

    /** @param array<string, mixed> $payload */
    private function signature(User $actor, array $payload): string
    {
        return hash_hmac('sha256', json_encode([$actor->getAuthIdentifier(), $payload], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
