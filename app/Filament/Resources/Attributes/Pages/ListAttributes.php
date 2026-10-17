<?php

namespace App\Filament\Resources\Attributes\Pages;

use App\Filament\CatalogDataActions;
use App\Filament\Exports\AttributeExporter;
use App\Filament\Imports\AttributeImporter;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\SplitListRecords;

class ListAttributes extends SplitListRecords
{
    protected static string $resource = AttributeResource::class;

    protected bool $inlineCreationEnabled = true;

    protected function getHeaderActions(): array
    {
        return [CatalogDataActions::import('import', AttributeImporter::class), CatalogDataActions::export('export', AttributeExporter::class)];
    }
}
