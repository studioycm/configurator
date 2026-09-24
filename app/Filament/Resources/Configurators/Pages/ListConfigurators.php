<?php

namespace App\Filament\Resources\Configurators\Pages;

use App\Filament\Resources\Configurators\ConfiguratorResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListConfigurators extends ListRecords
{
    protected static string $resource = ConfiguratorResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label('Create configurator')->url(ConfiguratorResource::getUrl('create'))];
    }
}
