<?php

namespace App\Filament\Resources\Configurators\Tables;

use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Models\Configurator;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class ConfiguratorsTable
{
    public static function configure(Table $table): Table
    {
        return TablePresentation::configure($table->columns([
            TextColumn::make('id')->label('ID')->sortable()->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('name')->label('Name')->sortable()->toggleable()->searchable(isIndividual: false, isGlobal: true),
            TextColumn::make('description')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ItemCountColumn::make('groups_count', 'Assigned groups', 'configurator-groups')->sortable()->toggleable(),
            ItemCountColumn::make('configurator_attributes_count', 'Attributes', 'configurator-attributes')->sortable()->toggleable(),
            ItemCountColumn::make('rules_count', 'Rules', 'configurator-rules')->sortable()->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('name')->recordUrl(fn (Configurator $record): string => ConfiguratorResource::getUrl('edit', ['record' => $record]))
            ->recordActions([Action::make('edit')->label('Manage')->url(fn (Configurator $record): string => ConfiguratorResource::getUrl('edit', ['record' => $record]))]), 'configurators', true);
    }
}
