<?php

namespace App\Filament\Resources\TourDefinitions\Pages;

use App\Filament\Resources\TourDefinitions\TourDefinitionResource;
use App\Models\TourDefinition;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageTourDefinitions extends ManageRecords
{
    protected static string $resource = TourDefinitionResource::class;

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('tourHelp')->label(__('tours.help_title'))->icon(Heroicon::OutlinedInformationCircle)->iconButton()->tooltip(__('tours.help_title'))->modalHeading(__('tours.help_title'))->modalDescription(__('tours.intro'))->modalSubmitAction(false)->modalCancelActionLabel(__('ui.actions.close')), CreateAction::make()->slideOver()->using(fn (CreateAction $action, array $data): TourDefinition => TourDefinitionResource::saveAction($action, $data))];
    }
}
