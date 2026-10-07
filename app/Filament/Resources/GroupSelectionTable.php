<?php

namespace App\Filament\Resources;

use App\Models\Group;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class GroupSelectionTable
{
    public static function configure(Table $table): Table
    {
        Gate::authorize('manage-catalog');

        return TablePresentation::configure($table->query(fn (Table $table) => Group::doesntHave('children')->where(fn ($query) => $query->whereNull('configurator_id')->orWhere('configurator_id', (int) $table->getArguments()['owner_id'])))
            ->columns([TextColumn::make('name')->label('Group')->searchable()->toggleable(), TextColumn::make('configurator.name')->label('Assigned Configurator')->searchable()->toggleable()])->paginationPageOptions([10, 25, 50]), 'select-group', true);
    }
}
