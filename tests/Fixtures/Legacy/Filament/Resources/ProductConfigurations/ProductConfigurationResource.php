<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\Pages\CreateProductConfiguration;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\Pages\EditProductConfiguration;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\Pages\ListProductConfigurations;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\RelationManagers\ConfigurationPartsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\RelationManagers\ConfigurationSpecificationsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\RelationManagers\FileAttachmentsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\Schemas\ProductConfigurationForm;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\Tables\ProductConfigurationsTable;
use Tests\Fixtures\Legacy\Models\ProductConfiguration;

class ProductConfigurationResource extends Resource
{
    protected static ?string $model = ProductConfiguration::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Product Configurations';

    protected static ?string $navigationLabel = 'Configurations';

    protected static ?string $pluralModelLabel = 'Configurations';

    protected static ?string $modelLabel = 'Configuration';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ProductConfigurationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductConfigurationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'configurationParts' => ConfigurationPartsRelationManager::class,
            'configurationSpecifications' => ConfigurationSpecificationsRelationManager::class,
            'fileAttachments' => FileAttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductConfigurations::route('/'),
            'create' => CreateProductConfiguration::route('/create'),
            'edit' => EditProductConfiguration::route('/{record}/edit'),
        ];
    }
}
