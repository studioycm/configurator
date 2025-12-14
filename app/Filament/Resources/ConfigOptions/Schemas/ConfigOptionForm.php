<?php

namespace App\Filament\Resources\ConfigOptions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConfigOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('config_attribute_id')
                    ->required()
                    ->numeric(),
                TextInput::make('label')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric(),
                Toggle::make('is_default')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
