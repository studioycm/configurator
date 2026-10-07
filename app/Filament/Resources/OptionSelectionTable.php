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

        return TablePresentation::configure($table->query(fn (Table $table) => Option::where('is_active', true)->where('is_hidden', false)->where('attribute_id', ConfiguratorAttribute::findOrFail((int) $table->getArguments()['owner_id'])->attribute_id)->whereNotIn('id', ConfiguratorAttribute::findOrFail((int) $table->getArguments()['owner_id'])->options()->select('option_id'))->with('value'))
            ->columns([TextColumn::make('code')->label('Code')->searchable()->toggleable(), TextColumn::make('value.label')->label('Value')->searchable()->toggleable()])->paginationPageOptions([10, 25, 50]), 'select-option', true);
    }
}
