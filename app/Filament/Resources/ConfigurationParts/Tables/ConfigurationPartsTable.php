<?php

namespace App\Filament\Resources\ConfigurationParts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConfigurationPartsTable
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
                TextColumn::make('part.name')
                    ->label('Part')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('part_number')
                    ->label('Part Number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Name')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('material')
                    ->label('Material')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('segment_index')
                    ->label('Segment Index')
                    ->numeric()
                    ->sortable(),
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
                SelectFilter::make('part_id')
                    ->label('Part')
                    ->relationship('part', 'name')
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
