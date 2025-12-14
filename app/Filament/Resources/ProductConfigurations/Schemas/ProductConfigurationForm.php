<?php

namespace App\Filament\Resources\ProductConfigurations\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductConfigurationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_profile_id')
                    ->relationship('productProfile', 'name')
                    ->required(),
                TextInput::make('configuration_code')
                    ->required(),
                TextInput::make('name'),
                Toggle::make('is_active')
                    ->required(),
                FileUpload::make('drawing_image_path')
                    ->image(),
                Textarea::make('config_data')
                    ->columnSpanFull(),
            ]);
    }
}
