<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\ConfigurationPartResource;

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
