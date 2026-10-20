<?php

namespace App\Filament\Resources;

use App\Models\Value;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ValueSelectionTable
{
    public static function configure(Table $table): Table
    {
        Gate::authorize('manage-catalog');

        return TablePresentation::configure($table->heading('')->query(fn (Table $table) => Value::query()
            ->when($table->getArguments()['attribute_id'] ?? null, fn ($query, $id) => $query->whereDoesntHave('options', fn ($options) => $options->where('attribute_id', $id)))
            ->whereNotIn('id', $table->getArguments()['excluded_ids'] ?? []))
            ->columns([
                TextColumn::make('label')->label(__('catalog.fields.value_ui_name'))->searchable()->sortable(),
                TextColumn::make('semantic_id')->label(__('catalog.fields.semantic_id'))->searchable()->sortable()->toggleable(),
                TextColumn::make('tags')->label(__('catalog.fields.tags'))->badge()->toggleable(),
            ])->filters([
                ValueTagFilter::make(),
            ])->defaultSort('label')->paginationPageOptions([10, 25, 50]), 'select-value', true);
    }
}
