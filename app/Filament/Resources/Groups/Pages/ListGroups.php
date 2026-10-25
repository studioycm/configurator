<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Filament\Resources\Groups\Concerns\InteractsWithGroupOrdering;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\SplitListRecords;

class ListGroups extends SplitListRecords
{
    /** @return list<string> */
    public function tableProfileControlNames(): array
    {
        return ['tableSearchScope', 'tableSearchFields', 'showOrderControls'];
    }

    use InteractsWithGroupOrdering;

    protected static string $resource = GroupResource::class;

    protected bool $inlineCreationEnabled = true;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
