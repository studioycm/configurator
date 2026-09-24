<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\ConfigProfileResource;

class ListConfigProfiles extends ListRecords
{
    protected static string $resource = ConfigProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
