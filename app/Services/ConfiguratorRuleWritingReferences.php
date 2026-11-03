<?php

namespace App\Services;

use App\ConditionOperator;
use App\ConditionSource;
use App\MappingTargetBehavior;
use App\Models\Configurator;
use App\Models\User;
use App\RuleEffectKind;
use App\RuleTargetScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

class ConfiguratorRuleWritingReferences
{
    public function __construct(private ConfiguratorDefinitionLoader $loader, private ConfiguratorRuleWritingCodec $codec) {}

    /** @return array<string, mixed> Advisory identities, never a compiled or saveable Rule. */
    public function get(User $actor, Configurator $owner): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Writing references must own their read transaction.');
        }

        return DB::transaction(function () use ($owner): array {
            if (DB::getDriverName() === 'mysql') {
                $isolation = DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level;
                if (! in_array($isolation, ['REPEATABLE-READ', 'SERIALIZABLE'], true)) {
                    throw new LogicException('Writing references require a consistent MySQL snapshot.');
                }
            }
            $owner = Configurator::whereKey($owner->id)->sharedLock()->firstOrFail();
            $graph = $this->loader->draft($owner);
            [$canonicalAttributes, $canonicalOptions] = $this->loader->canonical($graph, lock: false);
            $dictionary = $this->codec->dictionary($graph, $canonicalAttributes, $canonicalOptions);
            $attributes = [];
            foreach ($graph['attributes'] as $row) {
                $canonical = $canonicalAttributes[$row['attribute_id']];
                $key = $canonical['key'];
                $entry = array_column($dictionary[$key], null, 'id')[$row['id']];
                $values = [];
                foreach ($row['options'] as $option) {
                    $value = $canonicalOptions[$option['option_id']];
                    $semantic = $value['semantic_id'];
                    $values[] = [
                        'configurator_option_id' => (string) $option['id'], 'option_id' => (string) $option['option_id'],
                        'semantic_id' => $semantic, 'label' => $option['label_override'] ?? $value['label'],
                        'active' => (bool) $value['is_active'], 'hidden' => (bool) ($option['hidden_by_default'] || $value['is_hidden']),
                        'disabled' => (bool) $option['disabled_by_default'], 'ambiguous' => $semantic !== null && count($entry['values'][$semantic] ?? []) > 1,
                    ];
                }
                $attributes[] = [
                    'configurator_attribute_id' => (string) $row['id'], 'attribute_id' => (string) $row['attribute_id'],
                    'key' => $key, 'label' => $row['label_override'] ?? $canonical['label'],
                    'active' => (bool) ($row['is_active'] && $canonical['is_active']),
                    'ambiguous' => count($dictionary[$key]) > 1, 'values' => $values,
                ];
            }
            $keys = [];
            foreach ($graph['rules'] as $rule) {
                foreach ($rule['conditions'] as $condition) {
                    foreach (isset($condition['conditions']) ? $condition['conditions'] : [$condition] as $item) {
                        if ($item['source_kind'] === ConditionSource::ProductProperty->value && is_string($item['property_key'])) {
                            $keys[] = $item['property_key'];
                        }
                    }
                }
            }
            $data = [
                'schema' => 1, 'configurator_id' => (string) $owner->id, 'attributes' => $attributes,
                'product_fields' => array_map(fn (string $key): array => ['key' => $key, 'label' => str_replace('_', ' ', $key)], ProductFields::keys(configuratorId: $owner->id, savedRuleKeys: $keys)),
                'presentation' => $this->presentation(),
            ];

            return [...$data, 'fingerprint' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'read_at' => now()->utc()->toIso8601String()];
        }, attempts: 1);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function presentation(): array
    {
        $operators = ['Equals' => '=', 'NotEquals' => '!=', 'In' => 'in', 'NotIn' => 'not-in', 'Contains' => 'contains'];
        $effects = ['AllowOptions' => ['allow', 'allowed'], 'ExcludeOptions' => ['exclude', 'excluded'], 'HideOptions' => ['hide', 'hidden'], 'DisableOptions' => ['disable', 'excluded'], 'HideAttribute' => ['hide', 'hidden'], 'SetLabel' => ['set-label', 'text'], 'SetDisplayValue' => ['set-display-value', 'text'], 'SetHint' => ['set-hint', 'text']];

        return [
            'sources' => array_map(fn (ConditionSource $source): array => ['value' => $source->value, 'label' => $source->getLabel(), 'token' => $source === ConditionSource::ProductProperty ? '$' : '@', 'tone' => 'source', 'icon' => $source === ConditionSource::ProductProperty ? 'document' : 'list'], [ConditionSource::SelectionOption, ConditionSource::ProductProperty]),
            'operators' => array_map(fn (ConditionOperator $operator): array => ['value' => $operator->value, 'label' => $operator->getLabel(), 'token' => $operators[$operator->value], 'sources' => $operator === ConditionOperator::Contains ? ['ProductProperty'] : ['SelectionOption', 'ProductProperty'], 'tone' => 'compare', 'icon' => 'compare'], ConditionOperator::cases()),
            'mapping_behaviors' => array_map(fn (MappingTargetBehavior $behavior): array => ['value' => $behavior->value, 'label' => __('configurator.copy.'.strtolower($behavior->value)), 'token' => '/other '.strtolower($behavior->value), 'tone' => $behavior === MappingTargetBehavior::Hide ? 'hidden' : 'excluded', 'icon' => $behavior === MappingTargetBehavior::Hide ? 'eye-slash' : 'minus'], MappingTargetBehavior::cases()),
            'effects' => array_map(fn (RuleEffectKind $effect): array => ['value' => $effect->value, 'label' => $effect->getLabel(), 'token' => '/'.$effects[$effect->value][0], 'tone' => $effects[$effect->value][1], 'icon' => $effects[$effect->value][1] === 'text' ? 'pencil' : 'adjustments'], RuleEffectKind::cases()),
            'scopes' => array_map(fn (RuleTargetScope $scope): array => ['value' => $scope->value, 'label' => $scope->getLabel(), 'token' => $scope->value, 'tone' => 'target', 'icon' => 'list'], RuleTargetScope::cases()),
        ];
    }
}
