<?php

namespace App\Filament\Resources\ConfigProfiles\Pages;

use App\Filament\Resources\ConfigProfiles\ConfigProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConfigProfile extends EditRecord
{
    protected static string $resource = ConfigProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
