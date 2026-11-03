<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum RuleKind: string implements HasLabel
{
    case Mapping = 'Mapping';
    case Advanced = 'Advanced';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mapping => 'Mapping',
            self::Advanced => 'Advanced',
        };
    }
}
