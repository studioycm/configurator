<?php

namespace App\Filament\Resources\ConfigAttributes\Tables;

use App\ConfigInputType;
use App\Models\ConfigAttribute;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
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
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('configProfile.name')
                    ->label('Configurator')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('label')
                    ->label('Name')
                    ->description(fn (ConfigAttribute $record): string => (string) ($record->name))
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),
                TextColumn::make('segment_index')
                    ->label('Segment Index')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ui_schema.presentation.input_mode')
                    ->label('Type')
                    ->badge()
                    ->default(fn (ConfigAttribute $record): string => $record->uiSchema['presentation']['input_mode'] ?? $record->input_type->getLabel())
                    ->searchable(isIndividual: true, isGlobal: false),
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
                SelectFilter::make('config_profile_id')
                    ->label('Configurator')
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
