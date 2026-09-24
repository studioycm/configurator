<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\CatalogGroupResource;

class EditCatalogGroup extends EditRecord
{
    protected static string $resource = CatalogGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
