<?php

namespace App\Filament\Resources\ProductProfiles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('catalog_group_id')
                    ->relationship('catalogGroup', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('product_code')
                    ->required(),
                TextInput::make('slug'),
                TextInput::make('short_label'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric(),
            ]);
    }
}
