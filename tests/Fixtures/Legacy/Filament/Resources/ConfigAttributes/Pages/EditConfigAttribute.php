<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\ConfigAttributeResource;

class EditConfigAttribute extends EditRecord
{
    protected static string $resource = ConfigAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
