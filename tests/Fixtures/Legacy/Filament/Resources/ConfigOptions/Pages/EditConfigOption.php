<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\ConfigOptionResource;

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
