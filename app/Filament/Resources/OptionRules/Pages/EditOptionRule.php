<?php

namespace App\Filament\Resources\OptionRules\Pages;

use App\Filament\Resources\OptionRules\OptionRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
