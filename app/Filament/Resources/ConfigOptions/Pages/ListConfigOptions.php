<?php

namespace App\Filament\Resources\ConfigOptions\Pages;

use App\Filament\Resources\ConfigOptions\ConfigOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
