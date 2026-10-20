<?php

namespace App\Filament\Resources;

use App\Models\ConfiguratorAttribute;
use App\Models\Option;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class OptionSelectionTable
{
    public static function configure(Table $table): Table
    {
        Gate::authorize('manage-catalog');

        $owner = null;
        $resolveOwner = function (Table $table) use (&$owner): ConfiguratorAttribute {
            return $owner ??= ConfiguratorAttribute::with('attribute')->findOrFail((int) $table->getArguments()['owner_id']);
        };

        return TablePresentation::configure($table->heading('')->pluralModelLabel(__('catalog.attribute_values.headings.shared_plural'))
            ->query(fn (Table $table) => Option::where('is_active', true)->where('is_hidden', false)->where('attribute_id', $resolveOwner($table)->attribute_id)->whereNotIn('id', $resolveOwner($table)->options()->select('option_id'))->with('value'))
            ->columns([TextColumn::make('code')->label(__('catalog.fields.code'))->searchable()->toggleable(), TextColumn::make('value.label')->label(__('catalog.fields.value_ui_name'))->searchable()->toggleable(), TextColumn::make('value.semantic_id')->label(__('catalog.fields.semantic_id'))->searchable()->toggleable(), TextColumn::make('value.tags')->label(__('catalog.fields.tags'))->badge()])
            ->filters([ValueTagFilter::make('value')])->paginationPageOptions([10, 25, 50]), 'select-option', true);
    }
}
