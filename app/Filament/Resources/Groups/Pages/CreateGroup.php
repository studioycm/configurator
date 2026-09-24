<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Actions\SaveCatalogGroup;
use App\Filament\Resources\Groups\GroupResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGroup extends CreateRecord
{
    protected static string $resource = GroupResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveCatalogGroup::class)->handle(auth()->user(), null, $data);
    }
}
