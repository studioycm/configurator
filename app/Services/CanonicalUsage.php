<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\Option;
use App\Models\Value;
use Illuminate\Database\Eloquent\Builder;

class CanonicalUsage
{
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

    /** @return array{configurators: list<array<string, mixed>>, configurator_count: int, options: list<array<string, mixed>>, defaults: list<array<string, mixed>>, rules: list<array<string, mixed>>} */
    public function report(Attribute|Value|Option $record): array
    {
        $configurators = $this->configurators($record)->with('groups:id,name,configurator_id')->withCount(['attributes', 'rules'])->orderBy('name')->limit(100)->get();
        $optionIds = $this->options($record)->select('id');
        $localIds = ConfiguratorOption::whereIn('option_id', $optionIds)->select('id');
        $defaults = ConfiguratorAttribute::whereIn('default_configurator_option_id', $localIds)->with(['configurator:id,name', 'attribute:id,label', 'defaultOption.option:id,code'])->orderBy('id')->limit(100)->get()->map(fn (ConfiguratorAttribute $attribute): array => ['configurator_id' => $attribute->configurator_id, 'configurator' => $attribute->configurator->name, 'attribute' => $attribute->label_override ?? $attribute->attribute->label, 'code' => $attribute->defaultOption->option->code])->all();
        $localAttributeIds = $record instanceof Attribute ? ConfiguratorAttribute::where('attribute_id', $record->id)->pluck('id') : ConfiguratorOption::whereIn('option_id', $this->options($record)->select('id'))->pluck('configurator_attribute_id');
        $rules = ConfiguratorRule::where(function (Builder $query) use ($localAttributeIds): void {
            $query->whereIn('driver_configurator_attribute_id', $localAttributeIds)->orWhereIn('target_configurator_attribute_id', $localAttributeIds)
                ->orWhereHas('conditions', fn (Builder $query) => $query->whereIn('source_configurator_attribute_id', $localAttributeIds))
                ->orWhereHas('effects', fn (Builder $query) => $query->whereIn('target_configurator_attribute_id', $localAttributeIds));
        })->with('configurator:id,name')->orderBy('id')->limit(100)->get()->map(fn (ConfiguratorRule $rule): array => ['id' => $rule->id, 'label' => $rule->label, 'kind' => $rule->kind, 'configurator_id' => $rule->configurator_id, 'configurator' => $rule->configurator->name])->all();

        return [
            'configurators' => $configurators->toArray(), 'configurator_count' => $this->configurators($record)->count(),
            'options' => $this->options($record)->with(['attribute:id,label', 'value:id,label'])->orderBy('code')->limit(100)->get()->toArray(),
            'defaults' => $defaults, 'rules' => $rules,
        ];
    }
}
