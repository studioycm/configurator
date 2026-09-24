<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\CatalogGroupResource;

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
