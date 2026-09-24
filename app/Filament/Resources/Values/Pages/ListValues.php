<?php

namespace App\Filament\Resources\Values\Pages;

use App\Filament\Resources\Values\ValueResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListValues extends ListRecords
{
    protected static string $resource = ValueResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label('Create value')->url(ValueResource::getUrl('create'))];
    }
}
