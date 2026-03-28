<?php

namespace App\Filament\Resources\ConfigAttributes\Schemas;

use App\ConfigInputType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConfigAttributeForm
{
    public static function configure(Schema $schema, bool $hideConfigProfile = false): Schema
    {
        return $schema
            ->components([
                Select::make('config_profile_id')
                    ->relationship('configProfile', 'name')
                    ->searchable()
                    ->preload()
                    ->required(! $hideConfigProfile)
                    ->hidden($hideConfigProfile)
                    ->dehydrated(! $hideConfigProfile),
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
                TextInput::make('ui_schema.group')
                    ->label('Group')
                    ->helperText('Optional group key for organizing related attributes.'),
                Toggle::make('ui_schema.auto_select_first_allowed')
                    ->label('Auto Select First Allowed')
                    ->default(true),
                Textarea::make('ui_schema.help_text')
                    ->label('Help Text')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
