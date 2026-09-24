<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\FileAttachmentResource;

class EditFileAttachment extends EditRecord
{
    protected static string $resource = FileAttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
