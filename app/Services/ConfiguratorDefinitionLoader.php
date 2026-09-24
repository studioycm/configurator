<?php

namespace App\Services;

use App\DTO\ConfiguratorDefinition;
use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorInteraction;
use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\Product;
use App\Models\RuleCondition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConfiguratorDefinitionLoader
{
    public function __construct(private ConfiguratorDefinitionCompiler $compiler) {}

    /** @param array<string, mixed> $state @param array<string, mixed> $intent */
    public function forProduct(int $productId, array $state = [], array $intent = []): ConfiguratorEvaluationInput
    {
        return DB::transaction(function () use ($productId, $state, $intent): ConfiguratorEvaluationInput {
            $this->assertSnapshotIsolation();
            $product = Product::with('group')->findOrFail($productId);
            $configuratorId = $product->group->configurator_id;
            $interaction = ConfiguratorInteraction::fromUntrusted($intent === [] ? ['kind' => 'Initialize'] : $intent);
            if ($configuratorId === null) {
                return new ConfiguratorEvaluationInput(null, properties: $product->properties, intent: $interaction);
            }
            $diagnostics = [];
            $sameDefinition = ($state['version'] ?? null) === 1 && ($state['configurator_id'] ?? null) === $configuratorId;
            if ($state !== [] && ! $sameDefinition) {
                $diagnostics[] = ['code' => 'definition_changed', 'message' => 'The current Configurator has been loaded and its choices reset.'];
            }
            $safeState = [];
            foreach (['context', 'selections', 'remembered'] as $key) {
                $safeState[$key] = $sameDefinition && is_array($state[$key] ?? null) && count($state[$key]) <= 500 ? $state[$key] : [];
            }
            try {
                $data = $this->draft(Configurator::findOrFail($configuratorId));
                [$attributes, $options] = $this->canonical($data);
                $definition = $this->compiler->compile($data, $attributes, $options, globalContext: CatalogContextSettings::current()->choices);
            } catch (ValidationException) {
                $definition = null;
                $diagnostics[] = ['code' => 'invalid_definition', 'message' => 'The assigned Configurator needs repair before it can be used.'];
            }

            return new ConfiguratorEvaluationInput($definition, $product->properties, $safeState['context'], $safeState['selections'], $safeState['remembered'], $interaction, $configuratorId, $diagnostics);
        });
    }

    /** @param array<string, mixed> $state @param array<string, mixed> $intent */
    public function forPreview(User $actor, int $configuratorId, int $productId, array $state = [], array $intent = []): ConfiguratorEvaluationInput
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($configuratorId, $productId, $state, $intent): ConfiguratorEvaluationInput {
            $this->assertSnapshotIsolation();
            $available = Product::whereKey($productId)->whereHas('group', fn ($query) => $query->where('configurator_id', $configuratorId)->doesntHave('children'))->exists();
            if (! $available) {
                return new ConfiguratorEvaluationInput(null, diagnostics: [['code' => 'preview_product_unavailable', 'message' => 'This Product is no longer assigned to the previewed Configurator. Choose another Product.']]);
            }

            return $this->forProduct($productId, $state, $intent);
        });
    }

    private function assertSnapshotIsolation(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $isolation = DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level;
            if (! in_array($isolation, ['REPEATABLE-READ', 'SERIALIZABLE'], true)) {
                throw new \RuntimeException('Configurator reads require a consistent MySQL transaction snapshot. Configure REPEATABLE READ before serving definitions.');
            }
        }
    }

    public function load(int $configuratorId): ConfiguratorDefinition
    {
        return DB::transaction(function () use ($configuratorId): ConfiguratorDefinition {
            $this->assertSnapshotIsolation();
            $data = $this->draft(Configurator::findOrFail($configuratorId));
            [$attributes, $options] = $this->canonical($data);

            return $this->compiler->compile($data, $attributes, $options, globalContext: CatalogContextSettings::current()->choices);
        });
    }

    /** @return array<string, mixed> */
    public function draft(Configurator $configurator): array
    {
        $configurator->load(['attributes.options', 'rules.conditionGroups', 'rules.conditions.optionReferences', 'rules.effects.optionReferences', 'rules.mappingSets.sources', 'rules.mappingSets.targets']);
        $attributes = [];
        foreach ($configurator->attributes->sortBy(['display_order', 'id']) as $attribute) {
            $row = $attribute->only(['attribute_id', 'display_order', 'code_order', 'label_override', 'input_type', 'help_text']);
            $row['id'] = (string) $attribute->id;
            $row['default_configurator_option_id'] = $attribute->default_configurator_option_id === null ? null : (string) $attribute->default_configurator_option_id;
            $row['options'] = [];
            foreach ($attribute->options->sortBy(['display_order', 'id']) as $option) {
                $row['options'][] = ['id' => (string) $option->id, ...$option->only(['option_id', 'display_order', 'label_override', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default'])];
            }
            $attributes[] = $row;
        }
        $rules = [];
        foreach ($configurator->rules->sortBy(['priority', 'id']) as $rule) {
            $row = ['id' => (string) $rule->id, ...$rule->only(['label', 'kind', 'is_active', 'priority'])];
            foreach (['driver_configurator_attribute_id', 'target_configurator_attribute_id'] as $field) {
                $row[$field] = $rule->$field === null ? null : (string) $rule->$field;
            }
            $row['conditions'] = $rule->conditions->whereNull('condition_group_id')->sortBy(['sort_order', 'id'])->map(fn (RuleCondition $condition): array => $this->condition($condition))->values()->all();
            foreach ($rule->conditionGroups->sortBy(['sort_order', 'id']) as $group) {
                $row['conditions'][] = ['id' => (string) $group->id, 'operator' => $group->operator, 'conditions' => $rule->conditions->where('condition_group_id', $group->id)->sortBy(['sort_order', 'id'])->map(fn (RuleCondition $condition): array => $this->condition($condition))->values()->all()];
            }
            $row['effects'] = [];
            foreach ($rule->effects->sortBy('id') as $effect) {
                $row['effects'][] = ['id' => (string) $effect->id, ...$effect->only(['kind', 'target_scope', 'display_value']), 'target_configurator_attribute_id' => (string) $effect->target_configurator_attribute_id, 'option_ids' => $effect->optionReferences->sortBy('id')->pluck('configurator_option_id')->map(fn (int $id): string => (string) $id)->all()];
            }
            $row['sets'] = [];
            foreach ($rule->mappingSets->sortBy(['sort_order', 'id']) as $set) {
                $row['sets'][] = ['id' => (string) $set->id, ...$set->only(['label', 'sort_order']), 'source_option_ids' => $set->sources->sortBy('id')->pluck('configurator_option_id')->map(fn (int $id): string => (string) $id)->all(), 'target_option_ids' => $set->targets->sortBy('id')->pluck('configurator_option_id')->map(fn (int $id): string => (string) $id)->all()];
            }
            $rules[] = $row;
        }

        return ['name' => $configurator->name, 'description' => $configurator->description, 'context_schema' => $configurator->context_schema ?? ['territory' => [], 'application' => []], 'hidden_context_options' => $configurator->hidden_context_options ?? ['territory' => [], 'application' => []], 'policy_overrides' => $configurator->policy_overrides ?? [], 'attributes' => $attributes, 'rules' => $rules];
    }

    /** @param array<string, mixed> $data @return array{array<int, array{key: string, label: string}>, array<int, array{attribute_id: int, code: string, label: string}>} */
    public function canonical(array $data, bool $lock = false): array
    {
        $attributeIds = [];
        $optionIds = [];
        foreach (is_array($data['attributes'] ?? null) ? $data['attributes'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (is_int($row['attribute_id'] ?? null) || is_string($row['attribute_id'] ?? null)) {
                $attributeIds[] = $row['attribute_id'];
            }
            foreach (is_array($row['options'] ?? null) ? $row['options'] : [] as $option) {
                if (is_array($option) && (is_int($option['option_id'] ?? null) || is_string($option['option_id'] ?? null))) {
                    $optionIds[] = $option['option_id'];
                }
            }
        }
        $attributeQuery = Attribute::whereKey($attributeIds)->orderBy('id');
        $optionQuery = Option::with('value')->whereKey($optionIds)->orderBy('id');
        if ($lock) {
            $attributeQuery->lockForUpdate();
            $optionQuery->lockForUpdate();
        }
        $attributes = $attributeQuery->get()->mapWithKeys(fn (Attribute $attribute): array => [$attribute->id => $attribute->only(['key', 'label'])])->all();
        $options = $optionQuery->get()->mapWithKeys(fn (Option $option): array => [$option->id => ['attribute_id' => $option->attribute_id, 'code' => $option->code, 'label' => $option->value->label]])->all();

        return [$attributes, $options];
    }

    /** @return array<string, mixed> */
    private function condition(RuleCondition $condition): array
    {
        return ['id' => (string) $condition->id, ...$condition->only(['source_kind', 'property_key', 'context_dimension', 'operator', 'operand']), 'source_configurator_attribute_id' => $condition->source_configurator_attribute_id === null ? null : (string) $condition->source_configurator_attribute_id, 'option_ids' => $condition->optionReferences->sortBy('id')->pluck('configurator_option_id')->map(fn (int $id): string => (string) $id)->all()];
    }
}
