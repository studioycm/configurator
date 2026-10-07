<?php

namespace App\Services;

use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use Illuminate\Database\Eloquent\Builder;

class InclusionUsage
{
    /** @return Builder<ConfiguratorRule> */
    public function rules(ConfiguratorAttribute|ConfiguratorOption $record): Builder
    {
        return ConfiguratorRule::where(function (Builder $query) use ($record): void {
            if ($record instanceof ConfiguratorAttribute) {
                $query->where('driver_configurator_attribute_id', $record->id)->orWhere('target_configurator_attribute_id', $record->id)
                    ->orWhereHas('conditions', fn (Builder $nested): Builder => $nested->where('source_configurator_attribute_id', $record->id))
                    ->orWhereHas('effects', fn (Builder $nested): Builder => $nested->where('target_configurator_attribute_id', $record->id));

                return;
            }
            $query->whereHas('conditions.optionReferences', fn (Builder $nested): Builder => $nested->where('configurator_option_id', $record->id))
                ->orWhereHas('effects.optionReferences', fn (Builder $nested): Builder => $nested->where('configurator_option_id', $record->id))
                ->orWhereHas('mappingSets.sources', fn (Builder $nested): Builder => $nested->where('configurator_option_id', $record->id))
                ->orWhereHas('mappingSets.targets', fn (Builder $nested): Builder => $nested->where('configurator_option_id', $record->id));
        });
    }

    /** @return Builder<ConfiguratorAttribute> */
    public function defaults(ConfiguratorOption $record): Builder
    {
        return ConfiguratorAttribute::where('default_configurator_option_id', $record->id);
    }
}
