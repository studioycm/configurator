<?php

namespace App\Filament\Resources\CatalogGroups\RelationManagers;

use App\Filament\Resources\ProductProfiles\ProductProfileResource;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'productProfiles';

    protected static ?string $relatedResource = ProductProfileResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(),
                TextColumn::make('product_code')
                    ->label('Product Code')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(),
                TextColumn::make('short_label')
                    ->label('Short Label')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
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
            ])
            ->filtersFormColumns(5)
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
