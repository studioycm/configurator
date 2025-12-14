<?php

namespace App\Filament\Resources\ConfigurationSpecifications\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ConfigurationSpecificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_configuration_id')
                    ->relationship('productConfiguration', 'name')
                    ->required(),
                TextInput::make('spec_group'),
                TextInput::make('key')
                    ->required(),
                TextInput::make('value')
                    ->required(),
                TextInput::make('unit'),
                TextInput::make('sort_order')
                    ->numeric(),
            ]);
    }
}
