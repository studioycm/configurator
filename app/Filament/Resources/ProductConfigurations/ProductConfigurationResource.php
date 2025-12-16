<?php

namespace App\Filament\Resources\ProductConfigurations;

use App\Filament\Resources\ProductConfigurations\Pages\CreateProductConfiguration;
use App\Filament\Resources\ProductConfigurations\Pages\EditProductConfiguration;
use App\Filament\Resources\ProductConfigurations\Pages\ListProductConfigurations;
use App\Filament\Resources\ProductConfigurations\Schemas\ProductConfigurationForm;
use App\Filament\Resources\ProductConfigurations\Tables\ProductConfigurationsTable;
use App\Models\ProductConfiguration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductConfigurationResource extends Resource
{
    protected static ?string $model = ProductConfiguration::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurations';

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
            'configurationParts' => \App\Filament\Resources\ProductConfigurations\RelationManagers\ConfigurationPartsRelationManager::class,
            'configurationSpecifications' => \App\Filament\Resources\ProductConfigurations\RelationManagers\ConfigurationSpecificationsRelationManager::class,
            'fileAttachments' => \App\Filament\Resources\ProductConfigurations\RelationManagers\FileAttachmentsRelationManager::class,
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
