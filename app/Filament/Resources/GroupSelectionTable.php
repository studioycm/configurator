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
            ->columns([TextColumn::make('name')->label(__('catalog.resources.groups.singular'))->searchable()->toggleable(), TextColumn::make('configurator.name')->label(__('catalog.labels.assigned_configurator'))->searchable()->toggleable()])->paginationPageOptions([10, 25, 50]), 'select-group', true);
    }
}
