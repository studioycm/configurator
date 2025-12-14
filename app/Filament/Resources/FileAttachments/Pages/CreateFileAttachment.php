<?php

namespace App\Filament\Resources\FileAttachments\Pages;

use App\Filament\Resources\FileAttachments\FileAttachmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFileAttachment extends CreateRecord
{
    protected static string $resource = FileAttachmentResource::class;
}
