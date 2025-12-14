<?php

namespace App\Filament\Resources\ProductProfiles\Pages;

use App\Filament\Resources\ProductProfiles\ProductProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
