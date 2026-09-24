<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\SplitListRecords;
use Filament\Actions\Action;

class ListGroups extends SplitListRecords
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('New group')->url(GroupResource::getUrl('create')),
        ];
    }
}
