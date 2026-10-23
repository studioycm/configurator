<?php

namespace App\Filament\Resources\Options\Tables;

use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\TablePresentation;
use App\Models\Option;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OptionsTable
{
    public static function configure(Table $table): Table
    {
        return TablePresentation::configure($table->columns([
            TextColumn::make('id')->label(__('ui.fields.id'))->sortable()->searchable(isIndividual: false, isGlobal: true)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('code')->label(__('catalog.fields.code'))->sortable()->toggleable()->searchable(isIndividual: false, isGlobal: true)->copyable(),
            TextColumn::make('attribute.label')->label(__('catalog.resources.attributes.singular'))->sortable()->toggleable()->searchable(isIndividual: false, isGlobal: true),
            TextColumn::make('value.label')->label(__('catalog.fields.value_ui_name'))->sortable()->toggleable()->searchable(isIndividual: false, isGlobal: true),
            TextColumn::make('value.semantic_id')->label(__('catalog.fields.semantic_id'))->sortable()->toggleable()->searchable()->copyable(),
            ItemCountColumn::make('configurator_options_count', __('catalog.usage.headings.configurator_uses'), 'option-inclusions')->sortable()->toggleable(),
            TextColumn::make('created_at')->label(__('ui.fields.created_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('ui.fields.updated_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('attribute_id')->label(__('catalog.resources.attributes.singular'))->relationship('attribute', 'label')->searchable()->preload(),
            SelectFilter::make('value_id')->label(__('catalog.fields.master_value'))->relationship('value', 'label')->searchable(),
        ])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('code')->recordActions([Action::make('edit')->label(__('ui.actions.edit'))->url(fn (Option $record): string => OptionResource::getUrl('edit', ['record' => $record]))]), 'options', true);
    }
}
