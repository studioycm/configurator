<?php

namespace App\Filament\Resources\FileAttachments\Schemas;

use App\FileAttachmentType;
use App\Models\CatalogGroup;
use App\Models\ConfigurationPart;
use App\Models\Part;
use App\Models\ProductConfiguration;
use App\Models\ProductProfile;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FileAttachmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                MorphToSelect::make('attachable')
                    ->types([
                        MorphToSelect\Type::make(CatalogGroup::class)
                            ->titleAttribute('name'),
                        MorphToSelect\Type::make(ProductProfile::class)
                            ->titleAttribute('name'),
                        MorphToSelect\Type::make(ProductConfiguration::class)
                            ->titleAttribute('name'),
                        MorphToSelect\Type::make(Part::class)
                            ->titleAttribute('name'),
                        MorphToSelect\Type::make(ConfigurationPart::class)
                            ->titleAttribute('label'),
                    ])
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('title')
                    ->required(),
                SpatieMediaLibraryFileUpload::make('file_path')
                    ->collection('default')
                    ->disk(config('media-library.disk_name', 'public'))
                    ->visibility('public')
                    ->required(),
                Select::make('file_type')
                    ->options(FileAttachmentType::class),
                TextInput::make('sort_order')
                    ->numeric(),
                Toggle::make('is_primary')
                    ->required(),
            ]);
    }
}
