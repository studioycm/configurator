<?php

namespace App\Filament\Resources\ConfigurationSpecifications\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConfigurationSpecificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('productConfiguration.name')
                    ->label('Product Configuration')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('spec_group')
                    ->label('Spec Group')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('key')
                    ->label('Key')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('value')
                    ->label('Value')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_configuration_id')
                    ->label('Product Configuration')
                    ->relationship('productConfiguration', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns(5)
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
