<?php

namespace App\Filament\Resources\ProductProfiles;

use App\Filament\Resources\ProductProfiles\Pages\CreateProductProfile;
use App\Filament\Resources\ProductProfiles\Pages\EditProductProfile;
use App\Filament\Resources\ProductProfiles\Pages\ListProductProfiles;
use App\Filament\Resources\ProductProfiles\Schemas\ProductProfileForm;
use App\Filament\Resources\ProductProfiles\Tables\ProductProfilesTable;
use App\Models\ProductProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductProfileResource extends Resource
{
    protected static ?string $model = ProductProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
            'files' => \App\Filament\Resources\ProductProfiles\RelationManagers\FileAttachmentsRelationManager::class,
            'configProfiles' => \App\Filament\Resources\ProductProfiles\RelationManagers\ConfigProfilesRelationManager::class,
            'productConfigurations' => \App\Filament\Resources\ProductProfiles\RelationManagers\ProductConfigurationsRelationManager::class,
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
