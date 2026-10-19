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
        return parent::table($table)->persistInSession();
    }
}
