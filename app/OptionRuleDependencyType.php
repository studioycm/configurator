<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum OptionRuleDependencyType: string implements HasLabel
{
    case Hidden = 'hidden';
    case Disabled = 'disabled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hidden => 'Hidden',
            self::Disabled => 'Disabled',
        };
    }
}
