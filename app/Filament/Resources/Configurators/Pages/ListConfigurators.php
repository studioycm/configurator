<?php

namespace App\Filament\Resources\Configurators\Pages;

use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\SplitListRecords;
use Filament\Actions\Action;

class ListConfigurators extends SplitListRecords
{
    protected static string $resource = ConfiguratorResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label('Create configurator')->url(ConfiguratorResource::getUrl('create'))];
    }
}
