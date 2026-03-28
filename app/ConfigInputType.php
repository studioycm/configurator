<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum ConfigInputType: string implements HasLabel, HasIcon, HasColor
{
    case Toggle = 'toggle';
    case Select = 'select';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Toggle => 'Toggle',
            self::Select => 'Select',
        };
    }

    public function getIcon(): string|Heroicon|Htmlable|null
    {
        return match ($this) {
            self::Toggle => Heroicon::SquaresPlus,
            self::Select => Heroicon::QueueList,
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Toggle => 'success',
            self::Select => 'warning',
        };
    }
}
