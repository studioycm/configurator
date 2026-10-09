<?php

namespace App\Services;

use App\DTO\ItemListDefinition;
use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\MappingSet;
use App\Models\Option;
use App\Models\Product;
use App\Models\RuleEffect;
use App\Models\User;
use App\Models\Value;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ItemLists
{
    public function definition(User $actor, string $key, int $parentId): ItemListDefinition
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (preg_match('/\Acanonical-(attribute|value|option)-(options|inclusions|defaults|rules)\z/', $key, $parts)) {
            $record = match ($parts[1]) {
                'attribute' => Attribute::findOrFail($parentId), 'value' => Value::findOrFail($parentId), 'option' => Option::findOrFail($parentId),
            };
            $usage = app(CanonicalUsage::class);

            return match ($parts[2]) {
                'options' => new ItemListDefinition('Shared Options', $usage->options($record)->with(['attribute', 'value']), ['code' => 'Code', 'attribute.label' => 'Attribute', 'value.label' => 'Value']),
                'defaults' => new ItemListDefinition('Stored defaults', $usage->defaults($record)->with(['configurator', 'attribute', 'defaultOption.option']), ['configurator.name' => 'Configurator', 'attribute.label' => 'Attribute', 'defaultOption.option.code' => 'Default code']),
                'rules' => new ItemListDefinition('Blocking rules', $usage->rules($record)->with('configurator'), ['configurator.name' => 'Configurator', 'label' => 'Rule', 'kind' => 'Kind']),
                'inclusions' => $this->definition($actor, $record instanceof Attribute ? 'attribute-inclusions' : ($record instanceof Option ? 'option-inclusions' : 'value-option-inclusions'), $parentId),
            };
        }
        $optionColumns = ['code' => 'Code', 'value.label' => 'Value'];
        $inclusionColumns = ['configurator.name' => 'Configurator', 'attribute.label' => 'Attribute', 'label_override' => 'Local label'];

        return match ($key) {
            'inclusion-blocking-rules' => new ItemListDefinition('Blocking rules', app(InclusionUsage::class)->rules(ConfiguratorAttribute::findOrFail($parentId))->with('configurator'), ['label' => 'Rule', 'kind' => 'Kind']),
            'local-option-blocking-rules' => new ItemListDefinition('Blocking rules', app(InclusionUsage::class)->rules(ConfiguratorOption::findOrFail($parentId))->with('configurator'), ['label' => 'Rule', 'kind' => 'Kind']),
            'local-option-defaults' => new ItemListDefinition('Stored defaults', app(InclusionUsage::class)->defaults(ConfiguratorOption::findOrFail($parentId))->with(['configurator', 'attribute']), ['configurator.name' => 'Configurator', 'attribute.label' => 'Attribute']),
            'group-card-properties' => new ItemListDefinition('Selected card properties', collect(CatalogPolicy::resultSettings(Group::findOrFail($parentId)->result_settings)['card_properties'])->mapWithKeys(fn (string $property): array => [$property => ['property_key' => $property, 'label' => str_replace('_', ' ', $property)]])->all(), ['property_key' => 'Property', 'label' => 'Label'], GroupResource::getUrl('edit', ['record' => $parentId, 'group-tab' => 'form.group-settings.presentation::data::tab'])),
            'rule-mappings' => new ItemListDefinition('Mapping sets', ConfiguratorRule::findOrFail($parentId)->mappingSets()->getQuery()->with('rule'), ['label' => 'Set', 'sort_order' => 'Order']),
            'rule-effects' => new ItemListDefinition('Rule effects', ConfiguratorRule::findOrFail($parentId)->effects()->getQuery()->with(['targetAttribute.attribute', 'rule']), ['kind' => 'Effect', 'targetAttribute.attribute.label' => 'Target Attribute']),
            'attribute-options' => new ItemListDefinition('Shared Options', Attribute::findOrFail($parentId)->options()->getQuery()->with('value'), $optionColumns, OptionResource::getUrl('index', ['tableFilters' => ['attribute_id' => ['value' => $parentId]]])),
            'value-options' => new ItemListDefinition('Options using this Master Value', Value::findOrFail($parentId)->options()->getQuery()->with(['attribute', 'value']), ['attribute.label' => 'Attribute', ...$optionColumns]),
            'attribute-inclusions' => new ItemListDefinition('Configurator inclusions', Attribute::findOrFail($parentId)->inclusions()->getQuery()->with(['attribute', 'configurator']), $inclusionColumns),
            'value-option-inclusions' => new ItemListDefinition('Local uses of this Value', ConfiguratorOption::whereIn('option_id', Value::findOrFail($parentId)->options()->select('id'))->with(['configuratorAttribute.configurator', 'option.value']), ['configuratorAttribute.configurator.name' => 'Configurator', 'option.code' => 'Code', 'option.value.label' => 'Value']),
            'option-inclusions' => new ItemListDefinition('Local Option uses', Option::findOrFail($parentId)->inclusions()->getQuery()->with(['option.value', 'configuratorAttribute.configurator', 'configuratorAttribute.attribute']), ['configuratorAttribute.configurator.name' => 'Configurator', 'configuratorAttribute.attribute.label' => 'Attribute', 'option.code' => 'Code', 'option.value.label' => 'Value']),
            'configurator-groups' => new ItemListDefinition('Assigned Groups', Configurator::findOrFail($parentId)->groups()->getQuery(), ['name' => 'Group'], GroupResource::getUrl('index', ['tableFilters' => ['configurator_id' => ['value' => $parentId]]])),
            'configurator-products' => new ItemListDefinition('Products in assigned Groups', Product::whereIn('group_id', Configurator::findOrFail($parentId)->groups()->select('id')), ['product_code' => 'Product code', 'product_name' => 'Product name']),
            'configurator-attributes' => new ItemListDefinition('Included Attributes', Configurator::findOrFail($parentId)->attributes()->getQuery()->with(['attribute', 'configurator']), $inclusionColumns, ConfiguratorResource::getUrl('edit', ['record' => $parentId, 'tab' => 'attributes::data::tab'])),
            'configurator-rules' => new ItemListDefinition('Rules', Configurator::findOrFail($parentId)->rules()->getQuery()->with('configurator'), ['label' => 'Rule', 'kind' => 'Kind', 'priority' => 'Priority'], ConfiguratorResource::getUrl('edit', ['record' => $parentId, 'tab' => 'rules::data::tab'])),
            'inclusion-options' => new ItemListDefinition('Included Options', ConfiguratorAttribute::findOrFail($parentId)->options()->getQuery()->with(['option.value', 'configuratorAttribute.configurator']), ['option.code' => 'Code', 'option.value.label' => 'Shared Value', 'label_override' => 'Local label'], $this->recordUrl(ConfiguratorAttribute::findOrFail($parentId))),
            'group-products' => new ItemListDefinition('Products in this Group', Group::findOrFail($parentId)->products()->getQuery(), ['product_code' => 'Product code', 'product_name' => 'Product name'], ProductResource::getUrl('index', ['tableFilters' => ['group_id' => ['value' => $parentId]]])),
            default => abort(404, 'Unknown item list.'),
        };
    }

    /** @return list<string> */
    public function preview(User $actor, string $key, int $parentId): array
    {
        $definition = $this->definition($actor, $key, $parentId);
        $rows = is_array($definition->query) ? collect($definition->query)->take(6) : (clone $definition->query)->orderBy($definition->query->getModel()->qualifyColumn('id'))->limit(6)->get();
        $lines = $rows->take(5)->map(fn (Model|array $row): string => implode(' · ', array_filter(array_map(fn (string $field): string => (string) data_get($row, $field), array_slice(array_keys($definition->columns), 0, 2)), fn (string $value): bool => $value !== '')))->all();
        if ($rows->count() > 5) {
            $total = is_array($definition->query) ? count($definition->query) : (clone $definition->query)->count();
            $lines[] = ($total - 5).' more items — open list';
        }

        return $lines === [] ? ['No items'] : $lines;
    }

    public function recordUrl(Model $record): string
    {
        return match (true) {
            $record instanceof Option => OptionResource::getUrl('edit', ['record' => $record]),
            $record instanceof Group => GroupResource::getUrl('edit', ['record' => $record]),
            $record instanceof Product => ProductResource::getUrl('view', ['record' => $record]),
            $record instanceof ConfiguratorAttribute => ConfiguratorResource::getUrl('edit', ['record' => $record->configurator_id, 'tab' => 'attributes::data::tab', 'attribute' => $record->id]),
            $record instanceof ConfiguratorRule => ConfiguratorResource::getUrl('edit', ['record' => $record->configurator_id, 'tab' => 'rules::data::tab', 'rule' => $record->id]),
            $record instanceof ConfiguratorOption => ConfiguratorResource::getUrl('edit', ['record' => $record->configuratorAttribute->configurator_id, 'tab' => 'attributes::data::tab', 'attribute' => $record->configurator_attribute_id]),
            $record instanceof MappingSet, $record instanceof RuleEffect => ConfiguratorResource::getUrl('edit', ['record' => $record->rule->configurator_id, 'tab' => 'rules::data::tab', 'rule' => $record->rule_id]),
            default => abort(404),
        };
    }
}
