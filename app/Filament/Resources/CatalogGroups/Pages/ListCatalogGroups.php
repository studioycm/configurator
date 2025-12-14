<?php

namespace App\Filament\Resources\CatalogGroups\Pages;

use App\Filament\Resources\CatalogGroups\CatalogGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCatalogGroups extends ListRecords
{
    protected static string $resource = CatalogGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
