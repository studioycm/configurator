<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum ConditionSource: string implements HasLabel
{
    case SelectionOption = 'SelectionOption';
    case SelectionCode = 'SelectionCode';
    case ProductProperty = 'ProductProperty';
    case Territory = 'Territory';
    case Application = 'Application';

    public function getLabel(): string
    {
        return match ($this) {
            self::SelectionOption => 'Selected value',
            self::SelectionCode => 'Selected code',
            self::ProductProperty => 'Product property',
            self::Territory => 'Territory',
            self::Application => 'Application',
        };
    }
}
