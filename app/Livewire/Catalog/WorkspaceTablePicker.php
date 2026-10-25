<?php

namespace App\Livewire\Catalog;

use App\Filament\Resources\HasWorkspaceTable;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use Filament\Forms\Components\TableSelect\Livewire\TableSelectLivewireComponent;
use Filament\Tables\Table;

class WorkspaceTablePicker extends TableSelectLivewireComponent implements HasWorkspaceTable
{
    /** @return array{owner: int|string, widthVariant: string, profileVariant: string} */
    public function tableHostIdentity(): array
    {
        return ['owner' => $this->tableArguments['owner_id'] ?? 'list', 'widthVariant' => '', 'profileVariant' => $this->tableConfiguration];
    }

    use InteractsWithScopedTableSearch;

    public function table(Table $table): Table
    {
        return parent::table($table)->persistInSession();
    }
}
