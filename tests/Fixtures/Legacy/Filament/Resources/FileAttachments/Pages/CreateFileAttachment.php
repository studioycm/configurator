<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\FileAttachmentResource;

class CreateFileAttachment extends CreateRecord
{
    protected static string $resource = FileAttachmentResource::class;
}
