<?php

namespace App\Filament\Resources\Values\Pages;

use App\Filament\Resources\SplitListRecords;
use App\Filament\Resources\Values\ValueResource;
use Filament\Actions\Action;

class ListValues extends SplitListRecords
{
    protected static string $resource = ValueResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label('Create master value')->url(ValueResource::getUrl('create'))];
    }
}
