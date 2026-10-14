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
            self::SelectionOption => __('configurator.rules.sources.selected_value'),
            self::SelectionCode => __('configurator.rules.sources.selected_code'),
            self::ProductProperty => __('catalog.fields.product_property'),
            self::Territory => __('configurator.labels.territory'),
            self::Application => __('configurator.labels.application'),
        };
    }
}
