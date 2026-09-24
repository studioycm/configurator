<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\ConfigOptionResource;

class CreateConfigOption extends CreateRecord
{
    protected static string $resource = ConfigOptionResource::class;
}
