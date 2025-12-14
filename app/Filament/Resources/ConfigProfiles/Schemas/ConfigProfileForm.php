<?php

namespace App\Filament\Resources\ConfigProfiles\Schemas;

use App\ConfigProfileScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConfigProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_profile_id')
                    ->relationship('productProfile', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('scope')
                    ->options(ConfigProfileScope::class),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('extra_rules_json')
                    ->columnSpanFull(),
            ]);
    }
}
