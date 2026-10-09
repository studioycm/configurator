<?php

namespace App\Filament\Resources\Groups\Tables;

use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Models\Group;
use App\Services\CatalogPolicy;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
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
            ->defaultSort('sort_order')->reorderable('sort_order')->authorizeReorder(fn (): bool => auth()->user()?->can('manage-catalog') ?? false)->recordActions([
                ...array_map(fn (int $direction): Action => Action::make($direction === -1 ? 'moveUp' : 'moveDown')
                    ->label($direction === -1 ? 'Move up' : 'Move down')->iconButton()->authorize('manage-catalog')
                    ->icon($direction === -1 ? Heroicon::OutlinedArrowUp : Heroicon::OutlinedArrowDown)
                    ->visible(fn ($livewire): bool => $livewire->showOrderControls && ! $livewire->isTableReordering())
                    ->disabled(fn (Group $record, $livewire): bool => ! $livewire->usesGroupOrder() || $livewire->groupNeighbor($record, $direction) === null)
                    ->tooltip(fn (Group $record, $livewire): string => ! $livewire->usesGroupOrder() ? 'Restore the default sort order to move Groups.' : (($neighbor = $livewire->groupNeighbor($record, $direction)) ? ($direction === -1 ? 'Move before ' : 'Move after ').$neighbor->name : ($direction === -1 ? 'Already first among siblings' : 'Already last among siblings')))
                    ->action(fn (Group $record, $livewire) => $livewire->moveGroup($record->id, $direction)), [-1, 1]),
                Action::make('edit')->label('Edit')->url(fn (Group $record): string => GroupResource::getUrl('edit', ['record' => $record])),
                Action::make('openCatalog')->label('Open catalog page')->url(fn (Group $record): string => route('catalog.groups.show', $record))->openUrlInNewTab(),
            ]), 'groups', true);
    }
}
