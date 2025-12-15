<?php

namespace App\Filament\Resources\OptionRules\Tables;

use App\Models\ConfigOption;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table as TableComponent;

class AllowedOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ConfigOption::query())
            ->modifyQueryUsing(function (Builder $query) use ($table): Builder {
                $args = $table->getArguments();

                if (! empty($args['attribute_id'])) {
                    $query->where('config_attribute_id', $args['attribute_id']);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('attribute.label')
                    ->label('Attribute')
                    ->searchable(),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('attribute')
                    ->relationship('attribute', 'label')
                    ->searchable()
                    ->preload()
                    ->hidden(fn (TableComponent $table): bool => ! empty($table->getArguments()['attribute_id'] ?? null)),
            ]);
    }
}
