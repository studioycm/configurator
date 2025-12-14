<?php

namespace App\Filament\Resources\ConfigOptions\Pages;

use App\Filament\Resources\ConfigOptions\ConfigOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConfigOption extends EditRecord
{
    protected static string $resource = ConfigOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
