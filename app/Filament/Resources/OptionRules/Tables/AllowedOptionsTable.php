<?php

namespace App\Filament\Resources\OptionRules\Tables;

use App\Models\ConfigOption;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Table as TableComponent;
use Illuminate\Database\Eloquent\Builder;

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

                return $query
                    ->with('attribute')
                    ->orderBy('sort_order');
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('label')
                    ->label('Name')
                    ->description(fn (ConfigOption $record): string => (string) ($record->code))
                    ->searchable(['label', 'code'], isIndividual: true, isGlobal: false),
                TextColumn::make('attribute.label')
                    ->label('Attribute')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('attribute')
                    ->label('Attribute')
                    ->relationship('attribute', 'label')
                    ->searchable()
                    ->preload()
                    ->hidden(fn (TableComponent $table): bool => ! empty($table->getArguments()['attribute_id'] ?? null)),
            ])
            ->filtersFormColumns(5)
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContent);
    }
}
