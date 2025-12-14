<?php

namespace App\Filament\Resources\ProductProfiles\Pages;

use App\Filament\Resources\ProductProfiles\ProductProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
