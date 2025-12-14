<?php

namespace App\Filament\Resources\ConfigurationParts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ConfigurationPartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_configuration_id')
                    ->relationship('productConfiguration', 'name')
                    ->required(),
                Select::make('part_id')
                    ->relationship('part', 'name'),
                TextInput::make('part_number')
                    ->required()
                    ->numeric(),
                TextInput::make('label'),
                TextInput::make('material'),
                TextInput::make('quantity')
                    ->numeric(),
                TextInput::make('unit'),
                TextInput::make('segment_index')
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->numeric(),
            ]);
    }
}
