<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\FileAttachmentResource;

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
