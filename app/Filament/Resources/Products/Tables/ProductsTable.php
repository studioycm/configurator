<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\TablePresentation;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return TablePresentation::configure($table->columns([
            TextColumn::make('id')->label(__('ui.fields.id'))->searchable(isIndividual: false, isGlobal: true)->sortable()->toggleable(),
            TextColumn::make('product_code')->label(__('catalog.products.labels.product_code'))->searchable(isIndividual: false, isGlobal: true)->sortable()->toggleable(),
            TextColumn::make('product_name')->sortable()->label(__('catalog.products.labels.product_name'))->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('group.name')->label(__('catalog.resources.groups.singular'))->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('legacy_id')->label(__('catalog.fields.legacy_id'))->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('created_at')->label(__('ui.fields.created_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('ui.fields.updated_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('group_id')->label(__('catalog.resources.groups.singular'))->relationship('group', 'name')->searchable()->preload(),
        ])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('product_code')->recordActions([
                ViewAction::make()->modal()->modalAutofocus(false),
                Action::make('openCatalog')->label(__('catalog.products.labels.open_catalog_page'))->url(fn (Product $record): string => route('catalog.products.show', $record))->openUrlInNewTab(),
            ]), 'products', true);
    }
}
