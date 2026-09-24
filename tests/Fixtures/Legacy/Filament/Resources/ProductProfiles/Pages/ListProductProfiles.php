<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\ProductProfileResource;

class ListProductProfiles extends ListRecords
{
    protected static string $resource = ProductProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
