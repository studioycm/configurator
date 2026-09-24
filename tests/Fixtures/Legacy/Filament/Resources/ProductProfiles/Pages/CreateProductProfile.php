<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\ProductProfileResource;

class CreateProductProfile extends CreateRecord
{
    protected static string $resource = ProductProfileResource::class;
}
