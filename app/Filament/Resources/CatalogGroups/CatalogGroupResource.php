<?php

namespace App\Filament\Resources\CatalogGroups;

use App\Filament\Resources\CatalogGroups\Pages\CreateCatalogGroup;
use App\Filament\Resources\CatalogGroups\Pages\EditCatalogGroup;
use App\Filament\Resources\CatalogGroups\Pages\ListCatalogGroups;
use App\Filament\Resources\CatalogGroups\RelationManagers\FileAttachmentsRelationManager;
use App\Filament\Resources\CatalogGroups\RelationManagers\ProductProfilesRelationManager;
use App\Filament\Resources\CatalogGroups\Schemas\CatalogGroupForm;
use App\Filament\Resources\CatalogGroups\Tables\CatalogGroupsTable;
use App\Models\CatalogGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CatalogGroupResource extends Resource
{
    protected static ?string $model = CatalogGroup::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CatalogGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CatalogGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'files' => FileAttachmentsRelationManager::class,
            'products' => ProductProfilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCatalogGroups::route('/'),
            'create' => CreateCatalogGroup::route('/create'),
            'edit' => EditCatalogGroup::route('/{record}/edit'),
        ];
    }
}
