<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\Parts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\PartResource;

class ListParts extends ListRecords
{
    protected static string $resource = PartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
