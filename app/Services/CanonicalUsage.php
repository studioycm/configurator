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
    public function counts(Attribute|Value|Option $record): array
    {
        return ['options' => $this->options($record)->count(), 'inclusions' => $this->inclusions($record)->count(), 'defaults' => $this->defaults($record)->count(), 'rules' => $this->rules($record)->count()];
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
