<?php

namespace App\Filament\Resources\ConfigProfiles\Pages;

use App\Filament\Resources\ConfigProfiles\ConfigProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
