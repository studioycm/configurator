<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Pages\CreateConfigurationSpecification;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Pages\EditConfigurationSpecification;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Pages\ListConfigurationSpecifications;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Schemas\ConfigurationSpecificationForm;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Tables\ConfigurationSpecificationsTable;
use Tests\Fixtures\Legacy\Models\ConfigurationSpecification;

class ConfigurationSpecificationResource extends Resource
{
    protected static ?string $model = ConfigurationSpecification::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Product Configurations';

    protected static ?string $navigationLabel = 'Specifications';

    protected static ?string $pluralModelLabel = 'Specifications';

    protected static ?string $modelLabel = 'Specification';

    protected static ?int $navigationSort = 22;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConfigurationSpecificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigurationSpecificationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConfigurationSpecifications::route('/'),
            'create' => CreateConfigurationSpecification::route('/create'),
            'edit' => EditConfigurationSpecification::route('/{record}/edit'),
        ];
    }
}
