<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\ConfigurationSpecificationResource;

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
