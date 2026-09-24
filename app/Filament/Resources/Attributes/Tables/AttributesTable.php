<?php

namespace App\Filament\Resources\Attributes\Tables;

use App\Filament\Resources\Attributes\AttributeResource;
use App\Models\Attribute;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class AttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('ID')->sortable()->searchable(isIndividual: true, isGlobal: false)->toggleable(),
            TextColumn::make('key')->label('Key')->sortable()->toggleable()->searchable(isIndividual: true, isGlobal: false),
            TextColumn::make('label')->label('Label')->sortable()->toggleable()->searchable(isIndividual: true, isGlobal: false),
            TextColumn::make('options_count')->label('Options')->sortable()->toggleable(),
            TextColumn::make('configurator_attributes_count')->label('Used in configurators')->sortable()->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('label')->recordActions([Action::make('edit')->label('Edit')->url(fn (Attribute $record): string => AttributeResource::getUrl('edit', ['record' => $record]))]);
    }
}
