<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\Pages\CreateCatalogGroup;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\Pages\EditCatalogGroup;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\Pages\ListCatalogGroups;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\RelationManagers\FileAttachmentsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\RelationManagers\ProductProfilesRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\Schemas\CatalogGroupForm;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\Tables\CatalogGroupsTable;
use Tests\Fixtures\Legacy\Models\CatalogGroup;

class CatalogGroupResource extends Resource
{
    protected static ?string $model = CatalogGroup::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog manager';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Groups';

    protected static ?string $pluralModelLabel = 'Groups';

    protected static ?string $modelLabel = 'Group';

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
