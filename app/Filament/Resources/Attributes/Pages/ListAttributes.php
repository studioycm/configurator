<?php

namespace App\Filament\Resources\Attributes\Pages;

use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\SplitListRecords;
use Filament\Actions\Action;

class ListAttributes extends SplitListRecords
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label('Create attribute')->url(AttributeResource::getUrl('create'))];
    }
}
