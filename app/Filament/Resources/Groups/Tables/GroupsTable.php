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
            TextColumn::make('id')->label(__('ui.fields.id'))->searchable(isIndividual: false, isGlobal: true)->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('name')->label(__('ui.fields.name'))->searchable(isIndividual: false, isGlobal: true)->sortable()->toggleable(),
            TextColumn::make('legacy_id')->label(__('catalog.fields.legacy_id'))->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('parent.name')->label(__('catalog.groups.fields.parent'))->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('configurator.name')->label(__('configurator.resource.singular'))->searchable()->placeholder(__('ui.choices.unassigned'))->toggleable(),
            ItemCountColumn::make('card_properties_count', __('catalog.groups.labels.selected_card_properties'), 'group-card-properties')->state(fn (Group $record): int => count(CatalogPolicy::resultSettings($record->result_settings)['card_properties']))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('sort_order')->label(__('catalog.groups.fields.sort_order'))->numeric()->sortable()->toggleable(),
            TextColumn::make('created_at')->label(__('ui.fields.created_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('ui.fields.updated_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('configurator_id')->label(__('configurator.resource.singular'))->relationship('configurator', 'name')->searchable(),
            SelectFilter::make('parent_id')->label(__('catalog.groups.fields.parent'))->relationship('parent', 'name')->searchable()->preload(),
        ])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('sort_order')->reorderable('sort_order')->authorizeReorder(fn (): bool => auth()->user()?->can('manage-catalog') ?? false)->recordActions([
                ...array_map(fn (int $direction): Action => Action::make($direction === -1 ? 'moveUp' : 'moveDown')
                    ->label($direction === -1 ? __('catalog.groups.labels.move_up') : __('catalog.groups.labels.move_down'))->iconButton()->authorize('manage-catalog')
                    ->icon($direction === -1 ? Heroicon::OutlinedArrowUp : Heroicon::OutlinedArrowDown)
                    ->visible(fn ($livewire): bool => $livewire->showOrderControls && ! $livewire->isTableReordering())
                    ->disabled(fn (Group $record, $livewire): bool => ! $livewire->usesGroupOrder() || $livewire->groupNeighbor($record, $direction) === null)
                    ->tooltip(fn (Group $record, $livewire): string => ! $livewire->usesGroupOrder() ? __('catalog.groups.help.restore_the_default_sort_order_to_move') : (($neighbor = $livewire->groupNeighbor($record, $direction)) ? __($direction === -1 ? 'admin.ordering.move_before' : 'admin.ordering.move_after', ['row' => $neighbor->name]) : ($direction === -1 ? __('catalog.groups.help.already_first_among_siblings') : __('catalog.groups.help.already_last_among_siblings'))))
                    ->action(fn (Group $record, $livewire) => $livewire->moveGroup($record->id, $direction)), [-1, 1]),
                Action::make('edit')->label(__('ui.actions.edit'))->url(fn (Group $record): string => GroupResource::getUrl('edit', ['record' => $record])),
                Action::make('openCatalog')->label(__('catalog.groups.labels.open_catalog_page'))->url(fn (Group $record): string => route('catalog.groups.show', $record))->openUrlInNewTab(),
            ]), 'groups', true);
    }
}
