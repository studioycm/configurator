<?php

namespace App\Filament\Resources\FileAttachments\Schemas;

use App\FileAttachmentType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FileAttachmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('catalog_group_id')
                    ->numeric(),
                TextInput::make('product_profile_id')
                    ->numeric(),
                TextInput::make('product_configuration_id')
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('file_path')
                    ->required(),
                Select::make('file_type')
                    ->options(FileAttachmentType::class),
                TextInput::make('mime_type'),
                TextInput::make('sort_order')
                    ->numeric(),
                Toggle::make('is_primary')
                    ->required(),
            ]);
    }
}
