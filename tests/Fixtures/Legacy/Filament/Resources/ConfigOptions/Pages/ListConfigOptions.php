<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\ConfigOptionResource;

class ListConfigOptions extends ListRecords
{
    protected static string $resource = ConfigOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
