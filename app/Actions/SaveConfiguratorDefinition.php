<?php

namespace App\Actions;

use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\MappingSet;
use App\Models\MappingSetSource;
use App\Models\RuleCondition;
use App\Models\RuleConditionGroup;
use App\Models\RuleEffect;
use App\Models\User;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorRuleRecords;
use App\Services\ConfiguratorRuleWritingCodec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveConfiguratorDefinition
{
    public function __construct(private ConfiguratorDefinitionCompiler $compiler, private ConfiguratorDefinitionLoader $loader) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Configurator $configurator, array $data): Configurator
    {
        return $this->save($actor, $configurator, $data);
    }

    /** @param array<string, mixed> $data */
    private function save(User $actor, Configurator $configurator, array $data, bool $preserveInactiveReferences = false): Configurator
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($configurator, $data, $preserveInactiveReferences): Configurator {
            $record = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            $globalContext = CatalogContextSettings::current(lock: true)->choices;
            $current = $this->loader->draft($record);
            [$attributes, $options] = $this->loader->canonical($data, true, $this->loader->canonicalReferences($current));
            $definition = $this->compiler->compile($data, $attributes, $options, authoring: true, globalContext: $globalContext);
            $data = $definition->data;
            $this->assertOwnership($data, $current);
            foreach ($data['attributes'] as $index => $row) {
                if (! $preserveInactiveReferences && str_starts_with((string) $row['id'], 'new:') && ! $attributes[$row['attribute_id']]['is_active']) {
                    throw ValidationException::withMessages(['attributes.'.$index.'.attribute_id' => __('configurator.validation.disabled_attributes_cannot_be_newly_included_re_enable')]);
                }
                foreach ($row['options'] as $optionIndex => $option) {
                    $canonical = $options[$option['option_id']];
                    if (! $preserveInactiveReferences && str_starts_with((string) $option['id'], 'new:') && (! $canonical['is_active'] || $canonical['is_hidden'])) {
                        throw ValidationException::withMessages(['attributes.'.$index.'.options.'.$optionIndex.'.option_id' => __('configurator.validation.disabled_or_hidden_attribute_values_cannot_be')]);
                    }
                }
            }
            $attributeMap = [];
            $optionMap = [];
            foreach ($data['attributes'] as $row) {
                $attribute = $this->persist(ConfiguratorAttribute::class, $row['id'], ['configurator_id' => $record->id, ...$this->only($row, ['attribute_id', 'display_order', 'code_order', 'label_override', 'input_type', 'help_text', 'is_active'])]);
                $attributeMap[(string) $row['id']] = $attribute->id;
                foreach ($row['options'] as $optionRow) {
                    $option = $this->persist(ConfiguratorOption::class, $optionRow['id'], ['configurator_attribute_id' => $attribute->id, ...$this->only($optionRow, ['option_id', 'display_order', 'label_override', 'subtitle_mode', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default'])]);
                    $optionMap[(string) $optionRow['id']] = $option->id;
                }
            }
            $ruleIds = [];
            foreach ($data['rules'] as $row) {
                $rule = $this->persist(ConfiguratorRule::class, $row['id'], ['configurator_id' => $record->id, ...$this->only($row, ['label', 'kind', 'is_active', 'priority']),
                    'driver_configurator_attribute_id' => isset($row['driver_configurator_attribute_id']) ? $attributeMap[(string) $row['driver_configurator_attribute_id']] : null,
                    'target_configurator_attribute_id' => isset($row['target_configurator_attribute_id']) ? $attributeMap[(string) $row['target_configurator_attribute_id']] : null]);
                $ruleIds[] = $rule->id;
                $this->persistRule($rule, $row, $attributeMap, $optionMap);
            }
            $record->rules()->whereNotIn('id', $ruleIds)->delete();
            foreach ($data['attributes'] as $row) {
                $attribute = ConfiguratorAttribute::findOrFail($attributeMap[(string) $row['id']]);
                $attribute->fill(['default_configurator_option_id' => $optionMap[(string) $row['default_configurator_option_id']]]);
                if ($attribute->isDirty()) {
                    $attribute->save();
                }
            }
            $removed = $record->attributes()->whereNotIn('id', array_values($attributeMap))->get();
            foreach ($removed as $attribute) {
                $attribute->update(['default_configurator_option_id' => null]);
            }
            ConfiguratorOption::whereHas('configuratorAttribute', fn ($query) => $query->where('configurator_id', $record->id))->whereNotIn('id', array_values($optionMap))->delete();
            $record->attributes()->whereNotIn('id', array_values($attributeMap))->delete();
            $settings = $this->only($data, ['name', 'description', 'context_schema', 'hidden_context_options', 'policy_overrides']);
            foreach (['context_schema', 'hidden_context_options', 'policy_overrides'] as $key) {
                if ($record->$key !== null && $settings[$key] == $record->$key) {
                    $settings[$key] = $record->$key;
                }
            }
            $record->fill($settings);
            if ($record->isDirty()) {
                $record->save();
            }

            return $record->fresh();
        });
    }

    /** @param list<int|string> $ids */
    public function reorder(User $actor, Configurator $configurator, string $axis, array $ids): Configurator
    {
        return $this->change($actor, $configurator, function (array $data) use ($axis, $ids): array {
            if ($axis === 'attributes' || $axis === 'code') {
                $rows = &$data['attributes'];
                $column = $axis === 'attributes' ? 'display_order' : 'code_order';
            } elseif ($axis === 'rules') {
                $rows = &$data['rules'];
                $column = 'priority';
            } elseif (str_starts_with($axis, 'options:')) {
                $owner = substr($axis, 8);
                $found = null;
                foreach ($data['attributes'] as $index => $attribute) {
                    if ((string) $attribute['id'] === $owner) {
                        $found = $index;
                    }
                }
                if ($found === null) {
                    throw ValidationException::withMessages(['order' => __('configurator.validation.this_attribute_does_not_belong_to_the')]);
                }
                $rows = &$data['attributes'][$found]['options'];
                $column = 'display_order';
            } else {
                throw ValidationException::withMessages(['order' => __('configurator.validation.unknown_order_axis')]);
            }
            if (array_filter($ids, fn (mixed $id): bool => ! is_int($id) && ! is_string($id)) !== []) {
                throw ValidationException::withMessages(['order' => __('configurator.validation.submit_the_complete_unique_row_order')]);
            }
            $submitted = array_map('strval', $ids);
            $expected = array_map('strval', array_column($rows, 'id'));
            $sorted = $submitted;
            sort($sorted, SORT_STRING);
            sort($expected, SORT_STRING);
            if (! array_is_list($ids) || $sorted !== $expected) {
                throw ValidationException::withMessages(['order' => __('configurator.validation.submit_the_complete_unique_row_order_including')]);
            }
            foreach ($rows as &$row) {
                $position = array_search((string) $row['id'], $submitted, true);
                $row[$column] = $axis === 'rules' ? count($submitted) - 1 - $position : $position;
            }
            unset($row, $rows);

            return $data;
        });
    }

    /**
     * @param  \Closure(array<string, mixed>): array<string, mixed>  $change
     * @param  array{attribute_ids?: list<int|string>, value_ids?: list<int|string>, option_ids?: list<int|string>}  $additionalParents
     */
    public function change(User $actor, Configurator $configurator, \Closure $change, array $additionalParents = []): Configurator
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($actor, $configurator, $change, $additionalParents): Configurator {
            $record = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            CatalogContextSettings::current(lock: true);

            $current = $this->loader->draft($record);
            $this->loader->canonical($current, true, $additionalParents);

            return $this->handle($actor, $record, $change($current));
        });
    }

    /**
     * Server-only single-Rule mutation. The opening baseline must come from trusted server state.
     * A returned snapshot remains provisional while an enclosing transaction is open.
     *
     * @param  array<string, mixed>|null  $expectedRule
     * @param  array<string, mixed>  $proposedRule
     * @param  array<string, mixed>  $referenceBindings  Required accepted-source payload; explicit [] means an ID-only operation with no source-fidelity assertion.
     * @return array{configurator_id:int, rule_id:int, operation:string, identities:array<string,array<string,int>>, saved_rule:array<string,mixed>, records:array<string,mixed>, comparison_state:array<string,mixed>}
     */
    public function changeRuleWithIdentities(User $actor, Configurator $configurator, ?array $expectedRule, array $proposedRule, array $referenceBindings): array
    {
        return $this->changeRulesWithIdentities($actor, $configurator, fn (): array => [
            ['expected_rule' => $expectedRule, 'proposed_rule' => $proposedRule, 'reference_bindings' => $referenceBindings],
        ])['rules'][0];
    }

    /**
     * Prepare trusted selected mutations after canonical parent locks; nested results are provisional.
     *
     * @param  \Closure(array, array, array):array  $prepare
     * @return array{configurator_id:int,rules:list<array<string,mixed>>}
     */
    public function changeRulesWithIdentities(User $actor, Configurator $configurator, \Closure $prepare): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($configurator, $prepare): array {
            $record = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            $context = CatalogContextSettings::current(lock: true)->choices;
            $current = $this->loader->draft($record);
            [$attributes, $options] = $this->loader->canonical($current, true);
            $codec = app(ConfiguratorRuleWritingCodec::class);
            $dictionary = $codec->dictionary($current, $attributes, $options);
            $labels = [];
            foreach ($current['attributes'] as $attribute) {
                $label = $attribute['label_override'];
                $labels[$attribute['id']] = $label === null || trim($label) === '' ? $attributes[$attribute['attribute_id']]['label'] : $label;
            }
            $mutations = $prepare($current, $dictionary, $labels);
            if (! is_array($mutations) || ! array_is_list($mutations) || $mutations === []) {
                throw ValidationException::withMessages(['rules' => 'Select a nonempty list of Rule changes.']);
            }
            $changes = app(ConfiguratorEditorChanges::class);
            $owned = static function (array $rule): array {
                unset($rule['is_active'], $rule['priority']);

                return $rule;
            };
            $data = $current;
            $plans = [];
            $seen = [];
            $nextPriority = $current['rules'] === [] ? 0 : max(array_column($current['rules'], 'priority')) + 1;
            foreach ($mutations as $mutation) {
                if (! is_array($mutation) || array_diff(array_keys($mutation), ['expected_rule', 'proposed_rule', 'reference_bindings']) !== [] || ! array_key_exists('expected_rule', $mutation) || ($mutation['expected_rule'] !== null && ! is_array($mutation['expected_rule'])) || ! is_array($mutation['proposed_rule'] ?? null) || ! is_array($mutation['reference_bindings'] ?? null)) {
                    throw ValidationException::withMessages(['rules' => 'Malformed trusted Rule mutation.']);
                }
                ['expected_rule' => $expectedRule, 'proposed_rule' => $proposedRule, 'reference_bindings' => $referenceBindings] = $mutation;
                if ($referenceBindings !== []) {
                    try {
                        $codec->validateReferenceBindings($referenceBindings, $dictionary);
                    } catch (\InvalidArgumentException $exception) {
                        throw ValidationException::withMessages(['references' => $exception->getMessage()]);
                    }
                }
                $id = (string) ($proposedRule['id'] ?? '');
                if (isset($seen[$id])) {
                    throw ValidationException::withMessages(['rules' => 'A Rule may be changed only once in a selected batch.']);
                }
                $seen[$id] = true;
                $index = array_search($id, array_column($current['rules'], 'id'), true);
                $creating = $expectedRule === null;
                $original = $index === false ? null : $current['rules'][$index];
                if ($creating) {
                    if (! str_starts_with($id, 'new:') || $id === 'new:') {
                        throw ValidationException::withMessages(['rule' => 'Create requires an explicit new staging identity.']);
                    }
                    $proposedRule['is_active'] = true;
                    $proposedRule['priority'] = $nextPriority++;
                } else {
                    if ($original === null || (string) ($expectedRule['id'] ?? '') !== $id
                        || ! $changes->sameMeaning('rule', $owned($expectedRule), $owned($original))
                        || array_column($expectedRule['sets'] ?? [], 'sort_order', 'id') !== array_column($original['sets'], 'sort_order', 'id')) {
                        throw ValidationException::withMessages(['rule' => 'The saved Rule changed or was removed. Keep the writing and refresh explicitly.']);
                    }
                    if (($proposedRule['kind'] ?? null) !== $original['kind']) {
                        throw ValidationException::withMessages(['rule' => 'The Rule kind cannot change.']);
                    }
                    $proposedRule['is_active'] = $original['is_active'];
                    $proposedRule['priority'] = $original['priority'];
                    $surviving = array_values(array_intersect(array_column($original['effects'], 'id'), array_column($proposedRule['effects'] ?? [], 'id')));
                    $submitted = array_values(array_filter(array_column($proposedRule['effects'] ?? [], 'id'), fn ($effectId) => ! str_starts_with((string) $effectId, 'new:')));
                    $sawNew = false;
                    foreach ($proposedRule['effects'] ?? [] as $effect) {
                        if (str_starts_with((string) $effect['id'], 'new:')) {
                            $sawNew = true;
                        } elseif ($sawNew) {
                            throw ValidationException::withMessages(['effects' => 'New effects must follow surviving effects.']);
                        }
                    }
                    if ($surviving !== $submitted) {
                        throw ValidationException::withMessages(['effects' => 'Surviving effect order must be preserved.']);
                    }
                }
                if (! in_array($proposedRule['kind'] ?? null, ['Mapping', 'Advanced'], true)) {
                    throw ValidationException::withMessages(['rule' => 'Only Mapping and Advanced are supported.']);
                }
                if ($creating) {
                    $index = count($data['rules']);
                    $data['rules'][] = $proposedRule;
                } else {
                    $data['rules'][$index] = $proposedRule;
                }
                $plans[] = ['index' => $index, 'original' => $original, 'creating' => $creating];
            }
            $data = $this->compiler->compile($data, $attributes, $options, authoring: true, globalContext: $context)->data;
            $this->assertOwnership($data, $current);
            $attributeMap = array_combine(array_column($current['attributes'], 'id'), array_map('intval', array_column($current['attributes'], 'id')));
            $optionMap = [];
            foreach ($current['attributes'] as $attribute) {
                foreach ($attribute['options'] as $option) {
                    $optionMap[(string) $option['id']] = (int) $option['id'];
                }
            }
            $results = [];
            foreach ($plans as $plan) {
                ['index' => $index, 'original' => $original, 'creating' => $creating] = $plan;
                $row = $data['rules'][$index];
                $identities = ['rules' => [], 'condition_groups' => [], 'conditions' => [], 'effects' => [], 'mapping_sets' => []];
                $unchanged = ! $creating && $changes->sameMeaning('rule', $original, $row)
                    && array_column($original['sets'], 'sort_order', 'id') === array_column($row['sets'], 'sort_order', 'id');
                if ($unchanged) {
                    $rule = ConfiguratorRule::findOrFail($row['id']);
                    $this->existingRuleIdentities($original, $identities);
                } else {
                    $rule = $this->persist(ConfiguratorRule::class, $row['id'], ['configurator_id' => $record->id, ...$this->only($row, ['label', 'kind', 'is_active', 'priority']),
                        'driver_configurator_attribute_id' => $row['driver_configurator_attribute_id'], 'target_configurator_attribute_id' => $row['target_configurator_attribute_id']]);
                    $identities['rules'][(string) $row['id']] = $rule->id;
                    $this->persistRule($rule, $row, $attributeMap, $optionMap, $identities);
                }
                $results[] = ['configurator_id' => $record->id, 'rule_id' => $rule->id, 'operation' => $unchanged ? 'unchanged' : ($creating ? 'create' : 'update'), 'identities' => $identities];
            }
            $readback = $this->loader->draft($record->fresh());
            foreach ($results as &$result) {
                $saved = $readback['rules'][array_search((string) $result['rule_id'], array_column($readback['rules'], 'id'), true)];
                $result += ['saved_rule' => $saved, 'records' => $this->ruleRecords(ConfiguratorRule::findOrFail($result['rule_id'])), 'comparison_state' => $changes->comparisonState('rule', $saved)];
            }
            unset($result);

            return ['configurator_id' => $record->id, 'rules' => $results];
        });
    }

    /** @param array<string,mixed> $row @param array<string,array<string,int>> $identities */
    private function existingRuleIdentities(array $row, array &$identities): void
    {
        $identities['rules'][(string) $row['id']] = (int) $row['id'];
        foreach ($row['conditions'] as $condition) {
            if (isset($condition['conditions'])) {
                $identities['condition_groups'][(string) $condition['id']] = (int) $condition['id'];
                foreach ($condition['conditions'] as $child) {
                    $identities['conditions'][(string) $child['id']] = (int) $child['id'];
                }
            } else {
                $identities['conditions'][(string) $condition['id']] = (int) $condition['id'];
            }
        }
        foreach (['effects' => 'effects', 'sets' => 'mapping_sets'] as $bucket => $kind) {
            foreach ($row[$bucket] as $child) {
                $identities[$kind][(string) $child['id']] = (int) $child['id'];
            }
        }
    }

    /** @return array<string,mixed> */
    private function ruleRecords(ConfiguratorRule $rule): array
    {
        return app(ConfiguratorRuleRecords::class)->read($rule);
    }

    public function duplicate(User $actor, Configurator $configurator, string $name): Configurator
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($actor, $configurator, $name): Configurator {
            $source = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            CatalogContextSettings::current(lock: true);
            $data = $this->loader->draft($source);
            $this->loader->canonical($data, true);
            $data['name'] = $name;
            foreach ($data['attributes'] as &$attribute) {
                $attribute['id'] = 'new:attribute'.$attribute['id'];
                if ($attribute['default_configurator_option_id'] !== null) {
                    $attribute['default_configurator_option_id'] = 'new:option'.$attribute['default_configurator_option_id'];
                }
                foreach ($attribute['options'] as &$option) {
                    $option['id'] = 'new:option'.$option['id'];
                }
                unset($option);
            }
            unset($attribute);
            $conditions = function (array $rows) use (&$conditions): array {
                foreach ($rows as &$row) {
                    $row['id'] = 'new:'.(isset($row['conditions']) ? 'group' : 'condition').$row['id'];
                    if (isset($row['conditions'])) {
                        $row['conditions'] = $conditions($row['conditions']);
                    } else {
                        if ($row['source_configurator_attribute_id'] !== null) {
                            $row['source_configurator_attribute_id'] = 'new:attribute'.$row['source_configurator_attribute_id'];
                        }
                        $row['option_ids'] = array_map(fn (string $id): string => 'new:option'.$id, $row['option_ids']);
                    }
                }

                return $rows;
            };
            foreach ($data['rules'] as &$rule) {
                $rule['id'] = 'new:rule'.$rule['id'];
                foreach (['driver_configurator_attribute_id', 'target_configurator_attribute_id'] as $key) {
                    if ($rule[$key] !== null) {
                        $rule[$key] = 'new:attribute'.$rule[$key];
                    }
                }
                $rule['conditions'] = $conditions($rule['conditions']);
                foreach ($rule['effects'] as &$effect) {
                    $effect['id'] = 'new:effect'.$effect['id'];
                    $effect['target_configurator_attribute_id'] = 'new:attribute'.$effect['target_configurator_attribute_id'];
                    $effect['option_ids'] = array_map(fn (string $id): string => 'new:option'.$id, $effect['option_ids']);
                }
                unset($effect);
                foreach ($rule['sets'] as &$set) {
                    $set['id'] = 'new:set'.$set['id'];
                    foreach (['source_option_ids', 'target_option_ids'] as $key) {
                        $set[$key] = array_map(fn (string $id): string => 'new:option'.$id, $set[$key]);
                    }
                }
                unset($set);
            }
            unset($rule);
            $copy = Configurator::create(['name' => $name, 'is_active' => $source->is_active, 'disabled_group_behavior' => $source->disabled_group_behavior]);

            return $this->save($actor, $copy, $data, preserveInactiveReferences: true);
        });
    }

    /** @param array<string, mixed> $row @param array<string, int> $attributes @param array<string, int> $options */
    private function persistRule(ConfiguratorRule $rule, array $row, array $attributes, array $options, ?array &$identities = null): void
    {
        $conditionIds = [];
        $groupIds = [];
        foreach ($row['conditions'] as $index => $condition) {
            if (array_key_exists('conditions', $condition)) {
                $group = $this->persist(RuleConditionGroup::class, $condition['id'], ['rule_id' => $rule->id, 'operator' => $condition['operator'], 'sort_order' => $index]);
                $groupIds[] = $group->id;
                if ($identities !== null) {
                    $identities['condition_groups'][(string) $condition['id']] = $group->id;
                }
                foreach ($condition['conditions'] as $order => $predicate) {
                    $conditionIds[] = $savedCondition = $this->persistCondition($rule, $predicate, $attributes, $options, $group->id, $order);
                    if ($identities !== null) {
                        $identities['conditions'][(string) $predicate['id']] = $savedCondition;
                    }
                }
            } else {
                $conditionIds[] = $savedCondition = $this->persistCondition($rule, $condition, $attributes, $options, null, $index);
                if ($identities !== null) {
                    $identities['conditions'][(string) $condition['id']] = $savedCondition;
                }
            }
        }
        $rule->conditions()->whereNotIn('id', $conditionIds)->delete();
        $rule->conditionGroups()->whereNotIn('id', $groupIds)->delete();
        $effectIds = [];
        foreach ($row['effects'] as $effect) {
            $saved = $this->persist(RuleEffect::class, $effect['id'], ['rule_id' => $rule->id, ...$this->only($effect, ['kind', 'target_scope', 'display_value']), 'target_configurator_attribute_id' => $attributes[(string) $effect['target_configurator_attribute_id']]]);
            $effectIds[] = $saved->id;
            if ($identities !== null) {
                $identities['effects'][(string) $effect['id']] = $saved->id;
            }
            $this->references($saved->optionReferences(), array_map(fn (string|int $id): int => $options[(string) $id], $effect['option_ids']));
        }
        $rule->effects()->whereNotIn('id', $effectIds)->delete();
        $setIds = [];
        $sources = [];
        foreach ($row['sets'] as $set) {
            $saved = $this->persist(MappingSet::class, $set['id'], ['rule_id' => $rule->id, ...$this->only($set, ['label', 'sort_order', 'disallowed_target_behavior'])]);
            $setIds[] = $saved->id;
            if ($identities !== null) {
                $identities['mapping_sets'][(string) $set['id']] = $saved->id;
            }
            foreach ($set['source_option_ids'] as $id) {
                $sources[$options[(string) $id]] = $saved->id;
            }
            $this->references($saved->targets(), array_map(fn (string|int $id): int => $options[(string) $id], $set['target_option_ids']));
        }
        MappingSetSource::where('rule_id', $rule->id)->whereNotIn('configurator_option_id', array_keys($sources))->delete();
        foreach ($sources as $optionId => $setId) {
            $source = MappingSetSource::firstOrNew(['rule_id' => $rule->id, 'configurator_option_id' => $optionId]);
            $source->fill(['mapping_set_id' => $setId]);
            if (! $source->exists || $source->isDirty()) {
                $source->save();
            }
        }
        $rule->mappingSets()->whereNotIn('id', $setIds)->delete();
    }

    /** @param array<string, mixed> $row @param array<string, int> $attributes @param array<string, int> $options */
    private function persistCondition(ConfiguratorRule $rule, array $row, array $attributes, array $options, ?int $groupId, int $order): int
    {
        $condition = $this->persist(RuleCondition::class, $row['id'], ['rule_id' => $rule->id, 'condition_group_id' => $groupId, 'sort_order' => $order, ...$this->only($row, ['source_kind', 'property_key', 'context_dimension', 'operator', 'operand']), 'source_configurator_attribute_id' => isset($row['source_configurator_attribute_id']) ? $attributes[(string) $row['source_configurator_attribute_id']] : null]);
        $this->references($condition->optionReferences(), array_map(fn (string|int $id): int => $options[(string) $id], $row['option_ids']));

        return $condition->id;
    }

    /** @param list<int> $ids */
    private function references(HasMany $relation, array $ids): void
    {
        (clone $relation)->whereNotIn('configurator_option_id', $ids)->delete();
        foreach ($ids as $id) {
            $relation->firstOrCreate(['configurator_option_id' => $id]);
        }
    }

    /** @param class-string<Model> $class @param array<string, mixed> $values */
    private function persist(string $class, string|int $id, array $values): Model
    {
        $record = str_starts_with((string) $id, 'new:') ? new $class : $class::findOrFail($id);
        $record->fill($values);
        if (! $record->exists || $record->isDirty()) {
            $record->save();
        }

        return $record;
    }

    /** @param array<string, mixed> $proposed @param array<string, mixed> $current */
    private function assertOwnership(array $proposed, array $current): void
    {
        $existing = $this->inventory($current);
        foreach ($this->inventory($proposed) as $type => $rows) {
            foreach ($rows as $id => $owner) {
                if (str_starts_with((string) $id, 'new:')) {
                    continue;
                }
                if (($existing[$type][$id] ?? null) !== $owner) {
                    throw ValidationException::withMessages([$type.'.'.$id => __('configurator.validation.this_row_belongs_to_another_owner_or')]);
                }
            }
        }
    }

    /** @param array<string, mixed> $data @return array<string, array<string, string>> */
    private function inventory(array $data): array
    {
        $inventory = [];
        foreach ($data['attributes'] as $row) {
            $inventory['attributes'][(string) $row['id']] = (string) $row['attribute_id'];
            foreach ($row['options'] as $option) {
                $inventory['options'][(string) $option['id']] = $row['id'].':'.$option['option_id'];
            }
        }
        foreach ($data['rules'] as $rule) {
            $inventory['rules'][(string) $rule['id']] = 'root';
            foreach ($rule['conditions'] as $condition) {
                if (isset($condition['conditions'])) {
                    $inventory['condition_groups'][(string) $condition['id']] = (string) $rule['id'];
                    foreach ($condition['conditions'] as $predicate) {
                        $inventory['conditions'][(string) $predicate['id']] = (string) $rule['id'];
                    }
                } else {
                    $inventory['conditions'][(string) $condition['id']] = (string) $rule['id'];
                }
            }
            foreach (['effects', 'sets'] as $bucket) {
                foreach ($rule[$bucket] as $row) {
                    $inventory[$bucket][(string) $row['id']] = (string) $rule['id'];
                }
            }
        }

        return $inventory;
    }

    /** @param array<string, mixed> $data @param list<string> $keys @return array<string, mixed> */
    private function only(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }
}
