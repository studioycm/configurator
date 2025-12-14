<?php

namespace App\Filament\Resources\CatalogGroups\Pages;

use App\Filament\Resources\CatalogGroups\CatalogGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCatalogGroup extends CreateRecord
{
    protected static string $resource = CatalogGroupResource::class;
}
