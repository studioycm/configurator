<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\Parts\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\PartResource;

class EditPart extends EditRecord
{
    protected static string $resource = PartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
