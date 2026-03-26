<?php

namespace App\Filament\Resources\OptionRules\Tables;

use App\Models\OptionRule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OptionRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['configProfile', 'optionAttribute', 'option', 'targetAttribute']))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('configProfile.name')
                    ->label('Configurator')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('optionAttribute.label')
                    ->label('Attribute')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('option.label')
                    ->label('Option')
                    ->description(fn (OptionRule $record): string => (string) ($record->option?->code))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('targetAttribute.label')
                    ->label('Target Attribute')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('allowed_option_ids')
                    ->label('Allowed Options')
                    ->state(fn (OptionRule $record) => collect($record->allowedOptionLabels())->join(', '))
                    ->limit(50)
                    ->tooltip(fn (OptionRule $record) => collect($record->allowedOptionLabels())->join(', '))
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
                SelectFilter::make('config_profile_id')
                    ->label('Configurator')
                    ->relationship('configProfile', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('option')
                    ->label('Option')
                    ->relationship('option', 'label')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('optionAttribute')
                    ->label('Attribute')
                    ->relationship('optionAttribute', 'label')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('target_attribute_id')
                    ->label('Target Attribute')
                    ->relationship('targetAttribute', 'label')
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
