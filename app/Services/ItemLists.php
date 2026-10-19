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
                'options' => new ItemListDefinition($record instanceof Attribute ? __('catalog.attribute_values.headings.for_attribute', ['attribute' => $record->label]) : __('catalog.attribute_values.headings.shared_plural'), $usage->options($record)->with(['attribute', 'value']), ['code' => __('catalog.fields.code'), 'attribute.label' => __('catalog.resources.attributes.singular'), 'value.label' => __('catalog.fields.value_ui_name')]),
                'defaults' => new ItemListDefinition(__('catalog.usage.headings.stored_defaults'), $usage->defaults($record)->with(['configurator', 'attribute', 'defaultOption.option']), ['configurator.name' => __('configurator.resource.singular'), 'attribute.label' => __('catalog.resources.attributes.singular'), 'defaultOption.option.code' => __('configurator.inclusions.fields.default_code')]),
                'rules' => new ItemListDefinition(__('catalog.usage.headings.blocking_rules'), $usage->rules($record)->with('configurator'), ['configurator.name' => __('configurator.resource.singular'), 'label' => __('configurator.rules.singular'), 'kind' => __('ui.fields.kind')]),
                'inclusions' => $this->definition($actor, $record instanceof Attribute ? 'attribute-inclusions' : ($record instanceof Option ? 'option-inclusions' : 'value-option-inclusions'), $parentId),
            };
        }
        $optionColumns = ['code' => __('catalog.fields.code'), 'value.label' => __('catalog.fields.value_ui_name')];
        $inclusionColumns = ['configurator.name' => __('configurator.resource.singular'), 'attribute.label' => __('catalog.resources.attributes.singular'), 'label_override' => __('configurator.inclusions.values.fields.local_ui_name')];

        if ($key === 'inclusion-options') {
            $inclusion = ConfiguratorAttribute::with('attribute')->findOrFail($parentId);

            return new ItemListDefinition(__('configurator.inclusions.values.for_attribute', ['attribute' => $inclusion->label_override ?? $inclusion->attribute->label]),
                $inclusion->options()->getQuery()->with(['option.value', 'configuratorAttribute.configurator']),
                ['option.code' => __('catalog.fields.code'), 'option.value.label' => __('configurator.inclusions.values.fields.shared_ui_name'), 'label_override' => __('configurator.inclusions.values.fields.local_ui_name')],
                $this->recordUrl($inclusion));
        }

        return match ($key) {
            'inclusion-blocking-rules' => new ItemListDefinition(__('catalog.usage.headings.blocking_rules'), app(InclusionUsage::class)->rules(ConfiguratorAttribute::findOrFail($parentId))->with('configurator'), ['label' => __('configurator.rules.singular'), 'kind' => __('ui.fields.kind')]),
            'local-option-blocking-rules' => new ItemListDefinition(__('catalog.usage.headings.blocking_rules'), app(InclusionUsage::class)->rules(ConfiguratorOption::findOrFail($parentId))->with('configurator'), ['label' => __('configurator.rules.singular'), 'kind' => __('ui.fields.kind')]),
            'local-option-defaults' => new ItemListDefinition(__('catalog.usage.headings.stored_defaults'), app(InclusionUsage::class)->defaults(ConfiguratorOption::findOrFail($parentId))->with(['configurator', 'attribute']), ['configurator.name' => __('configurator.resource.singular'), 'attribute.label' => __('catalog.resources.attributes.singular')]),
            'group-card-properties' => new ItemListDefinition(__('catalog.headings.selected_card_properties'), collect(CatalogPolicy::resultSettings(Group::findOrFail($parentId)->result_settings)['card_properties'])->mapWithKeys(fn (string $property): array => [$property => ['property_key' => $property, 'label' => str_replace('_', ' ', $property)]])->all(), ['property_key' => __('catalog.labels.property'), 'label' => __('ui.fields.label')], GroupResource::getUrl('edit', ['record' => $parentId, 'group-tab' => 'form.group-settings.presentation::data::tab'])),
            'rule-mappings' => new ItemListDefinition(__('catalog.headings.mapping_sets'), ConfiguratorRule::findOrFail($parentId)->mappingSets()->getQuery()->with('rule'), ['label' => __('catalog.labels.set'), 'sort_order' => __('ui.fields.order')]),
            'rule-effects' => new ItemListDefinition(__('catalog.headings.rule_effects'), ConfiguratorRule::findOrFail($parentId)->effects()->getQuery()->with(['targetAttribute.attribute', 'rule']), ['kind' => __('configurator.rules.fields.effect'), 'targetAttribute.attribute.label' => __('configurator.rules.fields.target_attribute')]),
            'attribute-options' => new ItemListDefinition(__('catalog.attribute_values.headings.for_attribute', ['attribute' => Attribute::findOrFail($parentId)->label]), Attribute::findOrFail($parentId)->options()->getQuery()->with('value'), $optionColumns, OptionResource::getUrl('index', ['tableFilters' => ['attribute_id' => ['value' => $parentId]]])),
            'value-options' => new ItemListDefinition(__('catalog.headings.attribute_values_using_this_master_value'), Value::findOrFail($parentId)->options()->getQuery()->with(['attribute', 'value']), ['attribute.label' => __('catalog.resources.attributes.singular'), ...$optionColumns]),
            'attribute-inclusions' => new ItemListDefinition(__('catalog.headings.configurator_inclusions'), Attribute::findOrFail($parentId)->inclusions()->getQuery()->with(['attribute', 'configurator']), $inclusionColumns),
            'value-option-inclusions' => new ItemListDefinition(__('catalog.headings.configurator_uses_of_this_master_value'), ConfiguratorOption::whereIn('option_id', Value::findOrFail($parentId)->options()->select('id'))->with(['configuratorAttribute.configurator', 'option.value']), ['configuratorAttribute.configurator.name' => __('configurator.resource.singular'), 'option.code' => __('catalog.fields.code'), 'option.value.label' => __('catalog.fields.value_ui_name')]),
            'option-inclusions' => new ItemListDefinition(__('catalog.usage.headings.configurator_uses'), Option::findOrFail($parentId)->inclusions()->getQuery()->with(['option.value', 'configuratorAttribute.configurator', 'configuratorAttribute.attribute']), ['configuratorAttribute.configurator.name' => __('configurator.resource.singular'), 'configuratorAttribute.attribute.label' => __('catalog.resources.attributes.singular'), 'option.code' => __('catalog.fields.code'), 'option.value.label' => __('catalog.fields.value_ui_name')]),
            'configurator-groups' => new ItemListDefinition(__('catalog.headings.assigned_groups'), Configurator::findOrFail($parentId)->groups()->getQuery(), ['name' => __('catalog.resources.groups.singular')], GroupResource::getUrl('index', ['tableFilters' => ['configurator_id' => ['value' => $parentId]]])),
            'configurator-products' => new ItemListDefinition(__('catalog.headings.products_in_assigned_groups'), Product::whereIn('group_id', Configurator::findOrFail($parentId)->groups()->select('id')), ['product_code' => __('catalog.labels.product_code'), 'product_name' => __('catalog.labels.product_name')]),
            'configurator-attributes' => new ItemListDefinition(__('catalog.headings.included_attributes'), Configurator::findOrFail($parentId)->attributes()->getQuery()->with(['attribute', 'configurator']), $inclusionColumns, ConfiguratorResource::getUrl('edit', ['record' => $parentId, 'tab' => 'attributes::data::tab'])),
            'configurator-rules' => new ItemListDefinition(__('configurator.rules.plural'), Configurator::findOrFail($parentId)->rules()->getQuery()->with('configurator'), ['label' => __('configurator.rules.singular'), 'kind' => __('ui.fields.kind'), 'priority' => __('configurator.rules.fields.priority')], ConfiguratorResource::getUrl('edit', ['record' => $parentId, 'tab' => 'rules::data::tab'])),
            'group-products' => new ItemListDefinition(__('catalog.headings.products_in_this_group'), Group::findOrFail($parentId)->products()->getQuery(), ['product_code' => __('catalog.labels.product_code'), 'product_name' => __('catalog.labels.product_name')], ProductResource::getUrl('index', ['tableFilters' => ['group_id' => ['value' => $parentId]]])),
            default => abort(404, __('catalog.validation.unknown_item_list')),
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
            $lines[] = trans_choice('admin.tables.more_items', $total - 5);
        }

        return $lines === [] ? [__('admin.tables.no_items')] : $lines;
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
