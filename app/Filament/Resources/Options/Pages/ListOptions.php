<?php

namespace App\Filament\Resources\Options\Pages;

use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\SplitListRecords;

class ListOptions extends SplitListRecords
{
    protected static string $resource = OptionResource::class;

    protected bool $inlineCreationEnabled = true;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
