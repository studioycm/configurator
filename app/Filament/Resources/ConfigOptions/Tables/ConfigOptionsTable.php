<?php

namespace App\Filament\Resources\ConfigOptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ConfigOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['configProfile', 'attribute']))
            ->columns([
                TextColumn::make('configProfile.name')
                    ->label('Config Profile')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('attribute.label')
                    ->label('Attribute')
                    ->state(function (Model $record): ?string {
                        $attribute = $record->getRelationValue('attribute');

                        return $attribute?->label ?? $attribute?->name;
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->boolean(),
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
                SelectFilter::make('config_profile_id')
                    ->label('Config Profile')
                    ->relationship('configProfile', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('attribute')
                    ->relationship('attribute', 'label')
                    ->preload(),
                SelectFilter::make('is_active')
                    ->label('Active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('is_default')
                    ->label('Default')
                    ->options([
                        '1' => 'Default',
                        '0' => 'Non-default',
                    ]),
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
