<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\ConfigProfileResource;

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
