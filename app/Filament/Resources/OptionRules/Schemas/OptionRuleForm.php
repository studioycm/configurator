<?php

namespace App\Filament\Resources\OptionRules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class OptionRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('config_profile_id')
                    ->relationship('configProfile', 'name')
                    ->required(),
                TextInput::make('config_option_id')
                    ->required()
                    ->numeric(),
                Select::make('target_attribute_id')
                    ->relationship('targetAttribute', 'name')
                    ->required(),
                Textarea::make('allowed_option_ids')
                    ->columnSpanFull(),
            ]);
    }
}
