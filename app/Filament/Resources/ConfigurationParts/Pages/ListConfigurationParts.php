<?php

namespace App\Filament\Resources\ConfigurationParts\Pages;

use App\Filament\Resources\ConfigurationParts\ConfigurationPartResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConfigurationParts extends ListRecords
{
    protected static string $resource = ConfigurationPartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
