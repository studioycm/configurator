<?php

namespace App\Filament\Resources\ProductConfigurations\Pages;

use App\Filament\Resources\ProductConfigurations\ProductConfigurationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductConfiguration extends EditRecord
{
    protected static string $resource = ProductConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
