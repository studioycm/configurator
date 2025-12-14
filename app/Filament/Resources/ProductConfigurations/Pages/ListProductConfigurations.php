<?php

namespace App\Filament\Resources\ProductConfigurations\Pages;

use App\Filament\Resources\ProductConfigurations\ProductConfigurationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
