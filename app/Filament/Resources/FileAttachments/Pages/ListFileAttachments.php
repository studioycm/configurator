<?php

namespace App\Filament\Resources\FileAttachments\Pages;

use App\Filament\Resources\FileAttachments\FileAttachmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFileAttachments extends ListRecords
{
    protected static string $resource = FileAttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
