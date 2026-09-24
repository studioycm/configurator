<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Pages\CreateProductProfile;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Pages\EditProductProfile;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Pages\ListProductProfiles;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\RelationManagers\ConfigProfilesRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\RelationManagers\FileAttachmentsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\RelationManagers\ProductConfigurationsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Schemas\ProductProfileForm;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Tables\ProductProfilesTable;
use Tests\Fixtures\Legacy\Models\ProductProfile;

class ProductProfileResource extends Resource
{
    protected static ?string $model = ProductProfile::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog manager';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return 'Products';
    }

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?string $modelLabel = 'Product';

    public static function form(Schema $schema): Schema
    {
        return ProductProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'files' => FileAttachmentsRelationManager::class,
            'configProfiles' => ConfigProfilesRelationManager::class,
            'productConfigurations' => ProductConfigurationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductProfiles::route('/'),
            'create' => CreateProductProfile::route('/create'),
            'edit' => EditProductProfile::route('/{record}/edit'),
        ];
    }
}
