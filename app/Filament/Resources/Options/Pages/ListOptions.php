<?php

namespace App\Filament\Resources\Options\Pages;

use App\Filament\Resources\Options\OptionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOptions extends ListRecords
{
    protected static string $resource = OptionResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label('Create option')->url(OptionResource::getUrl('create'))];
    }
}
