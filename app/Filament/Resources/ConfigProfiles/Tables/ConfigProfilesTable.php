<?php

namespace App\Filament\Resources\ConfigProfiles\Tables;

use App\ConfigProfileScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConfigProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('productProfile'))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('productProfile.name')
                    ->label('Product')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('scope')
                    ->label('Scope')
                    ->badge()
                    ->searchable(isIndividual: true, isGlobal: false),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
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
                SelectFilter::make('product_profile_id')
                    ->label('Product')
                    ->relationship('productProfile', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('is_active')
                    ->label('Active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('scope')
                    ->options(collect(ConfigProfileScope::cases())
                        ->mapWithKeys(fn (ConfigProfileScope $case): array => [$case->value => $case->name])
                        ->all())
                    ->searchable(),
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
