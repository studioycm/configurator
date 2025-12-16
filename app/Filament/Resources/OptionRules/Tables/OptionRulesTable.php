<?php

namespace App\Filament\Resources\OptionRules\Tables;

use App\Models\OptionRule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('configProfile.name')
                    ->label('Config Profile')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('optionAttribute.label')
                    ->label('Attribute')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('option.label')
                    ->label('Option')
                    ->description(fn (OptionRule $record): string => (string) ($record->option?->code))
                    ->sortable(),
                TextColumn::make('targetAttribute.label')
                    ->label('Target Attribute')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('allowed_option_ids')
                    ->label('Allowed Options')
                    ->state(fn (Model $record) => collect($record->allowedOptionLabels())->join(', '))
                    ->limit(50)
                    ->tooltip(fn (Model $record) => collect($record->allowedOptionLabels())->join(', ')),
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
                //
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
