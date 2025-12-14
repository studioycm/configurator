<?php

namespace App\Filament\Resources\ProductProfiles\Pages;

use App\Filament\Resources\ProductProfiles\ProductProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductProfile extends CreateRecord
{
    protected static string $resource = ProductProfileResource::class;
}
