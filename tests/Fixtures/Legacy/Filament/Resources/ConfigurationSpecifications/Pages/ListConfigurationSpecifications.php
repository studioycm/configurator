<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\ConfigurationSpecificationResource;

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
