<?php

namespace App\Filament\Resources\Groups\Tables;

use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Models\Group;
use App\Services\CatalogPolicy;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return TablePresentation::configure($table->columns([
            TextColumn::make('id')->label('ID')->searchable(isIndividual: false, isGlobal: true)->sortable()->toggleable(),
            TextColumn::make('name')->label('Name')->searchable(isIndividual: false, isGlobal: true)->sortable()->toggleable(),
            TextColumn::make('legacy_id')->label('Legacy ID')->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('parent.name')->label('Parent')->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('configurator.name')->label('Configurator')->searchable()->placeholder('Unassigned')->toggleable(),
            ItemCountColumn::make('card_properties_count', 'Selected card properties', 'group-card-properties')->state(fn (Group $record): int => count(CatalogPolicy::resultSettings($record->result_settings)['card_properties']))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('sort_order')->label('Sort Order')->numeric()->sortable()->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('configurator_id')->label('Configurator')->relationship('configurator', 'name')->searchable(),
            SelectFilter::make('parent_id')->label('Parent')->relationship('parent', 'name')->searchable()->preload(),
        ])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('sort_order')->recordActions([
                Action::make('edit')->label('Edit')->url(fn (Group $record): string => GroupResource::getUrl('edit', ['record' => $record])),
                Action::make('openCatalog')->label('Open catalog page')->url(fn (Group $record): string => route('catalog.groups.show', $record))->openUrlInNewTab(),
            ]), 'groups', true);
    }
}
