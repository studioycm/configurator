<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum RuleEffectKind: string implements HasLabel
{
    case AllowOptions = 'AllowOptions';
    case ExcludeOptions = 'ExcludeOptions';
    case HideOptions = 'HideOptions';
    case DisableOptions = 'DisableOptions';
    case HideAttribute = 'HideAttribute';
    case SetLabel = 'SetLabel';
    case SetDisplayValue = 'SetDisplayValue';
    case SetHint = 'SetHint';

    public function getLabel(): string
    {
        return match ($this) {
            self::AllowOptions => __('configurator.rules.effects.allow_values'),
            self::ExcludeOptions => __('configurator.rules.effects.exclude_values'),
            self::HideOptions => __('configurator.rules.effects.hide_values'),
            self::DisableOptions => __('configurator.rules.effects.disable_values'),
            self::HideAttribute => __('configurator.rules.effects.hide_attribute'),
            self::SetLabel => __('configurator.rules.effects.set_label'),
            self::SetDisplayValue => __('configurator.rules.effects.set_display_value'),
            self::SetHint => __('configurator.rules.effects.set_hint'),
        };
    }
}
