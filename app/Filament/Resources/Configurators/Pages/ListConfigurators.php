<?php

namespace App\Filament\Resources\Configurators\Pages;

use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListConfigurators extends ListRecords
{
    use InteractsWithScopedTableSearch;

    protected static string $resource = ConfiguratorResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label('Create configurator')->url(ConfiguratorResource::getUrl('create'))];
    }
}
