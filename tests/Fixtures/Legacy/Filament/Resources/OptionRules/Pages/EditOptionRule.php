<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\OptionRuleResource;

class EditOptionRule extends EditRecord
{
    protected static string $resource = OptionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
