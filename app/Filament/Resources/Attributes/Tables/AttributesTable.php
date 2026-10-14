<?php

namespace App\Filament\Resources\Attributes\Tables;

use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Models\Attribute;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class AttributesTable
{
    public static function configure(Table $table): Table
    {
        return TablePresentation::configure($table->columns([
            TextColumn::make('id')->label(__('ui.fields.id'))->sortable()->searchable(isIndividual: false, isGlobal: true)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('key')->label(__('catalog.fields.key'))->sortable()->toggleable(isToggledHiddenByDefault: true)->searchable(isIndividual: false, isGlobal: true),
            TextColumn::make('label')->label(__('catalog.resources.attributes.singular'))->sortable()->toggleable()->searchable(isIndividual: false, isGlobal: true),
            ItemCountColumn::make('options_count', __('catalog.attribute_values.short_plural'), 'attribute-options')->sortable()->toggleable(),
            ItemCountColumn::make('configurator_attributes_count', __('catalog.usage.fields.used_by'), 'attribute-inclusions')
                ->suffix(fn (mixed $state): string => ' '.trans_choice('configurator.resource.count_label', (int) $state))
                ->sortable()->toggleable(),
            TextColumn::make('created_at')->label(__('ui.fields.created_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('ui.fields.updated_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('label')->recordActions([Action::make('edit')->label(__('ui.actions.edit'))->url(fn (Attribute $record): string => AttributeResource::getUrl('edit', ['record' => $record]))]), 'attributes', true);
    }
}
