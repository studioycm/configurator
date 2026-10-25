<?php

namespace App\Services;

use App\CatalogWriteReplan;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\Option;
use App\Models\Value;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CanonicalUsage
{
    /**
     * @param  list<int|string>  $attributeIds
     * @param  list<int|string>  $valueIds
     * @param  list<int|string>  $optionIds
     * @return list<int>
     */
    public function configuratorIds(array $attributeIds = [], array $valueIds = [], array $optionIds = [], bool $current = false): array
    {
        if ($valueIds !== []) {
            $optionIds = [...$optionIds, ...Option::whereIn('value_id', $valueIds)->orderBy('id')->when($current, fn (Builder $query): Builder => $query->lockForUpdate())->pluck('id')->all()];
        }
        $localAttributeIds = $optionIds === [] ? [] : ConfiguratorOption::whereIn('option_id', $optionIds)->orderBy('id')
            ->when($current, fn (Builder $query): Builder => $query->lockForUpdate())->pluck('configurator_attribute_id')->all();
        if ($attributeIds === [] && $localAttributeIds === []) {
            return [];
        }

        return ConfiguratorAttribute::where(fn (Builder $query): Builder => $query->whereIn('attribute_id', $attributeIds)->orWhereIn('id', $localAttributeIds))
            ->orderBy('id')->when($current, fn (Builder $query): Builder => $query->lockForUpdate())->pluck('configurator_id')->unique()->sort()->values()->all();
    }

    /**
     * @param  list<int>  $expected
     * @param  list<int|string>  $attributeIds
     * @param  list<int|string>  $valueIds
     * @param  list<int|string>  $optionIds
     */
    public function assertConfiguratorIds(array $expected, array $attributeIds = [], array $valueIds = [], array $optionIds = []): void
    {
        if (array_diff($this->configuratorIds($attributeIds, $valueIds, $optionIds, current: true), $expected) !== []) {
            throw CatalogWriteReplan::withMessages(['definition' => __('configurator.validation.changes_could_not_be_saved_your_draft')]);
        }
    }

    /**
     * @param  list<int|string>  $attributeIds
     * @param  list<int|string>  $valueIds
     * @param  list<int|string>  $optionIds
     * @return array{attributes: Collection<int, Attribute>, values: Collection<int, Value>, options: Collection<int, Option>}
     */
    public function lockParents(array $attributeIds, array $valueIds = [], array $optionIds = []): array
    {
        $links = $optionIds === [] ? new Collection : Option::whereKey($optionIds)->get(['id', 'attribute_id', 'value_id']);
        $attributeIds = array_values(array_unique([...$attributeIds, ...$links->pluck('attribute_id')->all()]));
        $valueIds = array_values(array_unique([...$valueIds, ...$links->pluck('value_id')->all()]));
        $attributes = $attributeIds === [] ? new Collection : Attribute::whereKey($attributeIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $values = $valueIds === [] ? new Collection : Value::whereKey($valueIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $options = $optionIds === [] ? new Collection : Option::whereKey($optionIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($options as $option) {
            if (! $attributes->has($option->attribute_id) || ! $values->has($option->value_id)) {
                throw CatalogWriteReplan::withMessages(['definition' => __('configurator.validation.changes_could_not_be_saved_your_draft')]);
            }
            $option->setRelation('value', $values->get($option->value_id));
        }

        return compact('attributes', 'values', 'options');
    }

    /** @return Builder<Configurator> */
    public function configurators(Attribute|Value|Option $record): Builder
    {
        if ($record instanceof Attribute) {
            return Configurator::whereHas('attributes', fn (Builder $query) => $query->where('attribute_id', $record->id));
        }

        return Configurator::whereHas('attributes.options.option', function (Builder $query) use ($record): void {
            if ($record instanceof Value) {
                $query->where('value_id', $record->id);
            } else {
                $query->whereKey($record->id);
            }
        });
    }

    /** @return Builder<Option> */
    public function options(Attribute|Value|Option $record): Builder
    {
        return match (true) {
            $record instanceof Attribute => Option::where('attribute_id', $record->id),
            $record instanceof Value => Option::where('value_id', $record->id),
            default => Option::whereKey($record->id),
        };
    }

    /** @return Builder<ConfiguratorOption>|Builder<ConfiguratorAttribute> */
    public function inclusions(Attribute|Value|Option $record): Builder
    {
        return $record instanceof Attribute
            ? ConfiguratorAttribute::where('attribute_id', $record->id)
            : ConfiguratorOption::whereIn('option_id', $this->options($record)->select('id'));
    }

    /** @return Builder<ConfiguratorAttribute> */
    public function defaults(Attribute|Value|Option $record): Builder
    {
        return ConfiguratorAttribute::whereIn('default_configurator_option_id', ConfiguratorOption::whereIn('option_id', $this->options($record)->select('id'))->select('id'));
    }

    /** @return Builder<ConfiguratorRule> */
    public function rules(Attribute|Value|Option $record): Builder
    {
        if ($record instanceof Attribute) {
            $ids = ConfiguratorAttribute::where('attribute_id', $record->id)->select('id');

            return ConfiguratorRule::where(function (Builder $query) use ($ids): void {
                $query->whereIn('driver_configurator_attribute_id', clone $ids)->orWhereIn('target_configurator_attribute_id', clone $ids)
                    ->orWhereHas('conditions', fn (Builder $nested) => $nested->whereIn('source_configurator_attribute_id', clone $ids))
                    ->orWhereHas('effects', fn (Builder $nested) => $nested->whereIn('target_configurator_attribute_id', clone $ids));
            });
        }
        $localIds = ConfiguratorOption::whereIn('option_id', $this->options($record)->select('id'))->select('id');

        return ConfiguratorRule::where(function (Builder $query) use ($localIds): void {
            $query->whereHas('conditions.optionReferences', fn (Builder $nested) => $nested->whereIn('configurator_option_id', clone $localIds))
                ->orWhereHas('effects.optionReferences', fn (Builder $nested) => $nested->whereIn('configurator_option_id', clone $localIds))
                ->orWhereHas('mappingSets.sources', fn (Builder $nested) => $nested->whereIn('configurator_option_id', clone $localIds))
                ->orWhereHas('mappingSets.targets', fn (Builder $nested) => $nested->whereIn('configurator_option_id', clone $localIds));
        });
    }

    /** @return array{options: int, inclusions: int, defaults: int, rules: int} */
    public function counts(Attribute|Value|Option $record, bool $current = false): array
    {
        $options = $current ? $this->options($record)->orderBy('id')->lockForUpdate()->get(['id'])->count() : $this->options($record)->count();

        return ['options' => $options, 'inclusions' => $this->inclusions($record)->count(), 'defaults' => $this->defaults($record)->count(), 'rules' => $this->rules($record)->count()];
    }

    /** @return array{configurators: list<array<string, mixed>>, configurator_count: int, options: list<array<string, mixed>>, defaults: list<array<string, mixed>>, rules: list<array<string, mixed>>, counts: array{options: int, inclusions: int, defaults: int, rules: int}} */
    public function report(Attribute|Value|Option $record): array
    {
        $configurators = $this->configurators($record)->with('groups:id,name,configurator_id')->withCount(['attributes', 'rules'])->orderBy('name')->limit(100)->get();
        $optionIds = $this->options($record)->select('id');
        $localIds = ConfiguratorOption::whereIn('option_id', $optionIds)->select('id');
        $defaults = ConfiguratorAttribute::whereIn('default_configurator_option_id', $localIds)->with(['configurator:id,name', 'attribute:id,label', 'defaultOption.option:id,code'])->orderBy('id')->limit(100)->get()->map(fn (ConfiguratorAttribute $attribute): array => ['configurator_id' => $attribute->configurator_id, 'configurator' => $attribute->configurator->name, 'attribute' => $attribute->label_override ?? $attribute->attribute->label, 'code' => $attribute->defaultOption->option->code])->all();
        $rules = $this->rules($record)->with('configurator:id,name')->orderBy('id')->limit(100)->get()->map(fn (ConfiguratorRule $rule): array => ['id' => $rule->id, 'label' => $rule->label, 'kind' => $rule->kind, 'configurator_id' => $rule->configurator_id, 'configurator' => $rule->configurator->name])->all();

        return [
            'configurators' => $configurators->toArray(), 'configurator_count' => $this->configurators($record)->count(),
            'options' => $this->options($record)->with(['attribute:id,label', 'value:id,label'])->orderBy('code')->limit(100)->get()->toArray(),
            'defaults' => $defaults, 'rules' => $rules,
            'counts' => $this->counts($record),
        ];
    }
}
