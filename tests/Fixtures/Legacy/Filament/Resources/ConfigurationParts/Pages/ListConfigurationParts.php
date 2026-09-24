<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\ConfigurationPartResource;

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
