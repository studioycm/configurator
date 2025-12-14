<?php

namespace App\Filament\Resources\ConfigAttributes\Pages;

use App\Filament\Resources\ConfigAttributes\ConfigAttributeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
