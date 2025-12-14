<?php

namespace App\Filament\Resources\ConfigurationSpecifications\Pages;

use App\Filament\Resources\ConfigurationSpecifications\ConfigurationSpecificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConfigurationSpecifications extends ListRecords
{
    protected static string $resource = ConfigurationSpecificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
