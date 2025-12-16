<?php

namespace App\Filament\Resources\ConfigProfiles\Tables;

use App\ConfigProfileScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('productProfile.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('scope')
                    ->badge()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
