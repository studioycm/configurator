<?php

namespace App\Filament\Resources\TourDefinitions\Pages;

use App\Filament\Resources\TourDefinitions\TourDefinitionResource;
use App\Models\TourDefinition;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTourDefinitions extends ManageRecords
{
    protected static string $resource = TourDefinitionResource::class;

    public function getSubheading(): ?string
    {
        return __('tours.intro');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->slideOver()->using(fn (CreateAction $action, array $data): TourDefinition => TourDefinitionResource::saveAction($action, $data))];
    }
}
