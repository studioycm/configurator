<?php

namespace App\Filament\Resources\Configurators\Tables;

use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Models\Configurator;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class ConfiguratorsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('ID')->sortable()->searchable(isIndividual: true, isGlobal: false)->toggleable(),
            TextColumn::make('name')->label('Name')->sortable()->toggleable()->searchable(isIndividual: true, isGlobal: false),
            TextColumn::make('groups_count')->label('Assigned groups')->sortable()->toggleable(),
            TextColumn::make('configurator_attributes_count')->label('Attributes')->sortable()->toggleable(),
            TextColumn::make('rules_count')->label('Rules')->sortable()->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('name')->recordActions([Action::make('edit')->label('Manage')->url(fn (Configurator $record): string => ConfiguratorResource::getUrl('edit', ['record' => $record]))]);
    }
}
