<?php

namespace App\Filament\Resources\CatalogGroups\Pages;

use App\Filament\Resources\CatalogGroups\CatalogGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
