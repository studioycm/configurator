<?php

namespace App\Filament\Resources;

use App\Models\Attribute;
use App\Models\Configurator;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class AttributeSelectionTable
{
    public static function configure(Table $table): Table
    {
        Gate::authorize('manage-catalog');

        return TablePresentation::configure($table->query(fn (Table $table) => Attribute::where('is_active', true)->whereNotIn('id', Configurator::findOrFail((int) $table->getArguments()['owner_id'])->attributes()->select('attribute_id')))
            ->columns([TextColumn::make('label')->label('Attribute')->searchable()->toggleable(), TextColumn::make('key')->label('Key')->searchable()->toggleable()])->paginationPageOptions([10, 25, 50]), 'select-attribute', true);
    }
}
