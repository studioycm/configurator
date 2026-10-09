<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Filament\Resources\Groups\Concerns\InteractsWithGroupOrdering;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\SplitListRecords;

class ListGroups extends SplitListRecords
{
    use InteractsWithGroupOrdering;

    protected static string $resource = GroupResource::class;

    protected bool $inlineCreationEnabled = true;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
