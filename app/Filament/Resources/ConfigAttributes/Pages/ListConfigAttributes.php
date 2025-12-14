<?php

namespace App\Filament\Resources\ConfigAttributes\Pages;

use App\Filament\Resources\ConfigAttributes\ConfigAttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
