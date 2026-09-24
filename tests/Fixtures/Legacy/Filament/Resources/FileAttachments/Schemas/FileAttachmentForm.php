<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\Schemas;

use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Tests\Fixtures\Legacy\FileAttachmentType;
use Tests\Fixtures\Legacy\Models\CatalogGroup;
use Tests\Fixtures\Legacy\Models\ConfigurationPart;
use Tests\Fixtures\Legacy\Models\Part;
use Tests\Fixtures\Legacy\Models\ProductConfiguration;
use Tests\Fixtures\Legacy\Models\ProductProfile;

class FileAttachmentForm
{
    public static function configure(Schema $schema, bool $hideAttachable = false): Schema
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
                    ->required(! $hideAttachable)
                    ->hidden($hideAttachable)
                    ->dehydrated(! $hideAttachable),
                TextInput::make('title')
                    ->required(),
                SpatieMediaLibraryFileUpload::make('file_path')
                    ->collection('default')
                    ->disk(config('media-library.disk_name', 'public'))
                    ->visibility('public')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(4096)
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
