<?php

namespace App\Livewire\Catalog;

use App\Filament\Resources\InteractsWithScopedTableSearch;
use Filament\Forms\Components\TableSelect\Livewire\TableSelectLivewireComponent;
use Filament\Tables\Table;

class WorkspaceTablePicker extends TableSelectLivewireComponent
{
    use InteractsWithScopedTableSearch;

    public function table(Table $table): Table
    {
        $table = parent::table($table)->persistInSession();
        if ($table->getFilter('tags') !== null) {
            $table->pushHeaderActions([$this->tagFilterPreferencesAction()]);
        }

        return $table;
    }
}
