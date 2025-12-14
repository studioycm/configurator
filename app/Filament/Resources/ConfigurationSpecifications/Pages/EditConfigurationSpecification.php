<?php

namespace App\Filament\Resources\ConfigurationSpecifications\Pages;

use App\Filament\Resources\ConfigurationSpecifications\ConfigurationSpecificationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConfigurationSpecification extends EditRecord
{
    protected static string $resource = ConfigurationSpecificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
