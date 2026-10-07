<?php

namespace App\Filament\Resources\Options\Tables;

use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\TablePresentation;
use App\Models\Option;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OptionsTable
{
    public static function configure(Table $table): Table
    {
        return TablePresentation::configure($table->columns([
            TextColumn::make('id')->label('ID')->sortable()->searchable(isIndividual: false, isGlobal: true)->toggleable(),
            TextColumn::make('code')->label('Code')->sortable()->toggleable()->searchable(isIndividual: false, isGlobal: true)->copyable(),
            TextColumn::make('attribute.label')->label('Attribute')->sortable()->toggleable()->searchable(isIndividual: false, isGlobal: true),
            TextColumn::make('value.label')->label('Value')->sortable()->toggleable()->searchable(isIndividual: false, isGlobal: true),
            ItemCountColumn::make('configurator_options_count', 'Local uses', 'option-inclusions')->sortable()->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([SelectFilter::make('attribute_id')->label('Attribute')->relationship('attribute', 'label')->searchable()->preload()])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('code')->recordActions([Action::make('edit')->label('Edit')->url(fn (Option $record): string => OptionResource::getUrl('edit', ['record' => $record]))]), 'options', true);
    }
}
