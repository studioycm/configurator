<?php

namespace App\Filament\Resources\FileAttachments\Pages;

use App\Filament\Resources\FileAttachments\FileAttachmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
