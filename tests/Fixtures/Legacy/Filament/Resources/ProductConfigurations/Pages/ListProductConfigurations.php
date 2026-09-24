<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\ProductConfigurationResource;

class ListProductConfigurations extends ListRecords
{
    protected static string $resource = ProductConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
