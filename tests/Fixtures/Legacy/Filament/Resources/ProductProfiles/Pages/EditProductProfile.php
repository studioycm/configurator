<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\ProductProfileResource;

class EditProductProfile extends EditRecord
{
    protected static string $resource = ProductProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
