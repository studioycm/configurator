<?php

namespace App\Filament\Resources\ConfigurationSpecifications;

use App\Filament\Resources\ConfigurationSpecifications\Pages\CreateConfigurationSpecification;
use App\Filament\Resources\ConfigurationSpecifications\Pages\EditConfigurationSpecification;
use App\Filament\Resources\ConfigurationSpecifications\Pages\ListConfigurationSpecifications;
use App\Filament\Resources\ConfigurationSpecifications\Schemas\ConfigurationSpecificationForm;
use App\Filament\Resources\ConfigurationSpecifications\Tables\ConfigurationSpecificationsTable;
use App\Models\ConfigurationSpecification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConfigurationSpecificationResource extends Resource
{
    protected static ?string $model = ConfigurationSpecification::class;

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
