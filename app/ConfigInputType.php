<?php

namespace App;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ConfigInputType: string implements HasLabel
{
    case Toggle = 'toggle';
    case Select = 'select';
    case Radio = 'radio';
    case Text = 'text';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Toggle => 'Toggle',
            self::Select => 'Select',
            self::Radio => 'Radio',
            self::Text => 'Text',
        };
    }
}
