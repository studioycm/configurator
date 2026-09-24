<?php

namespace App\Filament\Resources\Options\Pages;

use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\SplitListRecords;
use Filament\Actions\Action;

class ListOptions extends SplitListRecords
{
    protected static string $resource = OptionResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label('Create option')->url(OptionResource::getUrl('create'))];
    }
}
