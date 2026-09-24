<?php

namespace App\Actions;

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
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($configurator, $data): Configurator {
            $record = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            $current = $this->loader->draft($record);
            [$attributes, $options] = $this->loader->canonical($data, true);
            $definition = $this->compiler->compile($data, $attributes, $options, authoring: true);
            $data = $definition->data;
            $this->assertOwnership($data, $current);
            $attributeMap = [];
            $optionMap = [];
            foreach ($data['attributes'] as $row) {
                $attribute = $this->persist(ConfiguratorAttribute::class, $row['id'], ['configurator_id' => $record->id, ...$this->only($row, ['attribute_id', 'display_order', 'code_order', 'label_override', 'input_type', 'help_text'])]);
                $attributeMap[(string) $row['id']] = $attribute->id;
                foreach ($row['options'] as $optionRow) {
                    $option = $this->persist(ConfiguratorOption::class, $optionRow['id'], ['configurator_attribute_id' => $attribute->id, ...$this->only($optionRow, ['option_id', 'display_order', 'label_override', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default'])]);
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
            $settings = $this->only($data, ['name', 'description', 'context_schema', 'policy_overrides']);
            foreach (['context_schema', 'policy_overrides'] as $key) {
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
                    throw ValidationException::withMessages(['order' => 'This Attribute does not belong to the Configurator.']);
                }
                $rows = &$data['attributes'][$found]['options'];
                $column = 'display_order';
            } else {
                throw ValidationException::withMessages(['order' => 'Unknown order axis.']);
            }
            if (array_filter($ids, fn (mixed $id): bool => ! is_int($id) && ! is_string($id)) !== []) {
                throw ValidationException::withMessages(['order' => 'Submit the complete unique row order.']);
            }
            $submitted = array_map('strval', $ids);
            $expected = array_map('strval', array_column($rows, 'id'));
            $sorted = $submitted;
            sort($sorted, SORT_STRING);
            sort($expected, SORT_STRING);
            if (! array_is_list($ids) || $sorted !== $expected) {
                throw ValidationException::withMessages(['order' => 'Submit the complete unique row order, including rows hidden by filters.']);
            }
            foreach ($rows as &$row) {
                $position = array_search((string) $row['id'], $submitted, true);
                $row[$column] = $axis === 'rules' ? count($submitted) - 1 - $position : $position;
            }
            unset($row, $rows);

            return $data;
        });
    }

    /** @param \Closure(array<string, mixed>): array<string, mixed> $change */
    public function change(User $actor, Configurator $configurator, \Closure $change): Configurator
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($actor, $configurator, $change): Configurator {
            $record = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();

            return $this->handle($actor, $record, $change($this->loader->draft($record)));
        });
    }

    public function duplicate(User $actor, Configurator $configurator, string $name): Configurator
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($actor, $configurator, $name): Configurator {
            $source = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            $data = $this->loader->draft($source);
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
            $copy = Configurator::create(['name' => $name]);

            return $this->handle($actor, $copy, $data);
        });
    }

    /** @param array<string, mixed> $row @param array<string, int> $attributes @param array<string, int> $options */
    private function persistRule(ConfiguratorRule $rule, array $row, array $attributes, array $options): void
    {
        $conditionIds = [];
        $groupIds = [];
        foreach ($row['conditions'] as $index => $condition) {
            if (array_key_exists('conditions', $condition)) {
                $group = $this->persist(RuleConditionGroup::class, $condition['id'], ['rule_id' => $rule->id, 'operator' => $condition['operator'], 'sort_order' => $index]);
                $groupIds[] = $group->id;
                foreach ($condition['conditions'] as $order => $predicate) {
                    $conditionIds[] = $this->persistCondition($rule, $predicate, $attributes, $options, $group->id, $order);
                }
            } else {
                $conditionIds[] = $this->persistCondition($rule, $condition, $attributes, $options, null, $index);
            }
        }
        $rule->conditions()->whereNotIn('id', $conditionIds)->delete();
        $rule->conditionGroups()->whereNotIn('id', $groupIds)->delete();
        $effectIds = [];
        foreach ($row['effects'] as $effect) {
            $saved = $this->persist(RuleEffect::class, $effect['id'], ['rule_id' => $rule->id, ...$this->only($effect, ['kind', 'target_scope', 'display_value']), 'target_configurator_attribute_id' => $attributes[(string) $effect['target_configurator_attribute_id']]]);
            $effectIds[] = $saved->id;
            $this->references($saved->optionReferences(), array_map(fn (string|int $id): int => $options[(string) $id], $effect['option_ids']));
        }
        $rule->effects()->whereNotIn('id', $effectIds)->delete();
        $setIds = [];
        $sources = [];
        foreach ($row['sets'] as $set) {
            $saved = $this->persist(MappingSet::class, $set['id'], ['rule_id' => $rule->id, ...$this->only($set, ['label', 'sort_order'])]);
            $setIds[] = $saved->id;
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
                    throw ValidationException::withMessages([$type.'.'.$id => 'This row belongs to another owner or its canonical identity changed. Reload and repair the draft.']);
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
