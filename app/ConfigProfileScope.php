<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum ConfigProfileScope: string implements HasLabel
{
    case ConfigurationSelection = 'configuration_selection';
    case CatalogFiltering = 'catalog_filtering';
    case Workflow = 'workflow';

    public function getLabel(): string
    {
        return match ($this) {
            self::ConfigurationSelection => 'Configuration Selection',
            self::CatalogFiltering => 'Catalog Filtering',
            self::Workflow => 'Workflow',
        };
    }
}
