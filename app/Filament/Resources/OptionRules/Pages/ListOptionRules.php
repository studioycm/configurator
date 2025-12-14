<?php

namespace App\Filament\Resources\OptionRules\Pages;

use App\Filament\Resources\OptionRules\OptionRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOptionRules extends ListRecords
{
    protected static string $resource = OptionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
