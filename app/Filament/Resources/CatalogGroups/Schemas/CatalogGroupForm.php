<?php

namespace App\Filament\Resources\CatalogGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CatalogGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('parent_id')
                    ->relationship('parent', 'name'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric(),
                TextInput::make('path'),
            ]);
    }
}
