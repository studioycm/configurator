<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\ProductConfigurationResource;

class CreateProductConfiguration extends CreateRecord
{
    protected static string $resource = ProductConfigurationResource::class;
}
