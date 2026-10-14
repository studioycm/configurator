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
            self::AllowOptions => 'Allow values',
            self::ExcludeOptions => 'Exclude values',
            self::HideOptions => 'Hide values',
            self::DisableOptions => 'Disable values',
            self::HideAttribute => 'Hide Attribute',
            self::SetLabel => 'Set label',
            self::SetDisplayValue => 'Set display value',
            self::SetHint => 'Set hint',
        };
    }
}
