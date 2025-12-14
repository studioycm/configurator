<?php

namespace App\Filament\Resources\ConfigOptions\Pages;

use App\Filament\Resources\ConfigOptions\ConfigOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConfigOption extends CreateRecord
{
    protected static string $resource = ConfigOptionResource::class;
}
