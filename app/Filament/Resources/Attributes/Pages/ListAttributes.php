<?php

namespace App\Filament\Resources\Attributes\Pages;

use App\Filament\CatalogDataActions;
use App\Filament\Exports\AttributeExporter;
use App\Filament\Imports\AttributeImporter;
use App\Filament\Resources\Attributes\Actions\CreateAttributesAction;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\SplitListRecords;
use Filament\Tables\Table;

class ListAttributes extends SplitListRecords
{
    protected static string $resource = AttributeResource::class;

    protected bool $inlineCreationEnabled = false;

    protected function makeTable(): Table
    {
        $table = parent::makeTable();

        return $table->headerActions([CreateAttributesAction::make(), ...$table->getHeaderActions()]);
    }

    protected function getHeaderActions(): array
    {
        return [CatalogDataActions::import('import', AttributeImporter::class), CatalogDataActions::export('export', AttributeExporter::class)];
    }
}
