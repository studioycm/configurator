<?php

namespace App\Filament\Resources\ConfigurationParts\Pages;

use App\Filament\Resources\ConfigurationParts\ConfigurationPartResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConfigurationPart extends EditRecord
{
    protected static string $resource = ConfigurationPartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
