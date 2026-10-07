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
            TextColumn::make('id')->label('ID')->searchable(isIndividual: false, isGlobal: true)->sortable()->toggleable(),
            TextColumn::make('product_code')->label('Product Code')->searchable(isIndividual: false, isGlobal: true)->sortable()->toggleable(),
            TextColumn::make('product_name')->label('Product Name')->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('group.name')->label('Group')->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('legacy_id')->label('Legacy ID')->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('group_id')->label('Group')->relationship('group', 'name')->searchable()->preload(),
        ])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('product_code')->recordActions([
                ViewAction::make()->modal()->modalAutofocus(false),
                Action::make('openCatalog')->label('Open catalog page')->url(fn (Product $record): string => route('catalog.products.show', $record))->openUrlInNewTab(),
            ]), 'products', true);
    }
}
