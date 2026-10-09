<?php

namespace App\Filament\Resources\Configurators\Pages;

use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\SplitListRecords;

class ListConfigurators extends SplitListRecords
{
    protected static string $resource = ConfiguratorResource::class;

    protected bool $inlineCreationEnabled = true;

    protected bool $inlineEditingEnabled = false;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
