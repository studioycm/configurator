<?php

namespace App\Filament\Resources\Groups\Tables;

use App\Filament\Resources\Groups\GroupResource;
use App\Models\Group;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('ID')->searchable(isIndividual: true, isGlobal: false)->sortable()->toggleable(),
            TextColumn::make('name')->label('Name')->searchable(isIndividual: true, isGlobal: false)->sortable()->toggleable(),
            TextColumn::make('legacy_id')->label('Legacy ID')->searchable(isIndividual: true, isGlobal: false)->toggleable(),
            TextColumn::make('parent.name')->label('Parent')->searchable(isIndividual: true, isGlobal: false)->toggleable(),
            TextColumn::make('configurator.name')->label('Configurator')->placeholder('Unassigned')->toggleable(),
            TextColumn::make('sort_order')->label('Sort Order')->numeric()->sortable()->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('parent_id')->label('Parent')->relationship('parent', 'name')->searchable()->preload(),
        ])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('sort_order')->recordActions([
                Action::make('edit')->label('Edit')->url(fn (Group $record): string => GroupResource::getUrl('edit', ['record' => $record])),
                Action::make('openCatalog')->label('Open catalog page')->url(fn (Group $record): string => route('catalog.groups.show', $record))->openUrlInNewTab(),
            ]);
    }
}
