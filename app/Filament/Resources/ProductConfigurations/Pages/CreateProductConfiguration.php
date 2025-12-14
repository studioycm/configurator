<?php

namespace App\Filament\Resources\ProductConfigurations\Pages;

use App\Filament\Resources\ProductConfigurations\ProductConfigurationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductConfiguration extends CreateRecord
{
    protected static string $resource = ProductConfigurationResource::class;
}
