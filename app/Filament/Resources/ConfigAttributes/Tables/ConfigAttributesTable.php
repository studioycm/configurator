<?php

namespace App\Filament\Resources\ConfigAttributes\Tables;

use App\ConfigInputType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConfigAttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('configProfile'))
            ->columns([
                TextColumn::make('configProfile.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('input_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_required')
                    ->boolean(),
                TextColumn::make('segment_index')
                    ->numeric()
                    ->sortable(),
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
                SelectFilter::make('is_required')
                    ->label('Required')
                    ->options([
                        '1' => 'Required',
                        '0' => 'Optional',
                    ]),
                SelectFilter::make('input_type')
                    ->label('Input Type')
                    ->options(collect(ConfigInputType::cases())
                        ->mapWithKeys(fn (ConfigInputType $case): array => [$case->value => $case->name])
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
