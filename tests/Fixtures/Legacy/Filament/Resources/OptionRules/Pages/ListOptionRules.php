<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\OptionRuleResource;

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
