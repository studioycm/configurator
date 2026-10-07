<?php

namespace App\Filament\Resources\Attributes\Tables;

use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\ItemCountColumn;
use App\Filament\Resources\TablePresentation;
use App\Models\Attribute;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class AttributesTable
{
    public static function configure(Table $table): Table
    {
        return TablePresentation::configure($table->columns([
            TextColumn::make('id')->label('ID')->sortable()->searchable(isIndividual: false, isGlobal: true)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('key')->label('Key')->sortable()->toggleable(isToggledHiddenByDefault: true)->searchable(isIndividual: false, isGlobal: true),
            TextColumn::make('label')->label('Label')->sortable()->toggleable()->searchable(isIndividual: false, isGlobal: true),
            ItemCountColumn::make('options_count', 'Options', 'attribute-options')->sortable()->toggleable(),
            ItemCountColumn::make('configurator_attributes_count', 'Configurators', 'attribute-inclusions')->sortable()->toggleable(),
            TextColumn::make('created_at')->label('Created At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([])->filtersFormColumns(5)->deferFilters(false)->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('label')->recordActions([Action::make('edit')->label('Edit')->url(fn (Attribute $record): string => AttributeResource::getUrl('edit', ['record' => $record]))]), 'attributes', true);
    }
}
