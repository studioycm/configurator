<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\ProductConfigurationResource;

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
