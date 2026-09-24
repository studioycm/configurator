<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\ConfigAttributeResource;

class ListConfigAttributes extends ListRecords
{
    protected static string $resource = ConfigAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
