<?php

namespace App\Filament\Resources\ConfigAttributes\Schemas;

use App\ConfigInputType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConfigAttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('config_profile_id')
                    ->relationship('configProfile', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('label'),
                TextInput::make('slug'),
                Select::make('input_type')
                    ->options(ConfigInputType::class)
                    ->default('toggle')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric(),
                Toggle::make('is_required')
                    ->required(),
                TextInput::make('segment_index')
                    ->numeric(),
            ]);
    }
}
