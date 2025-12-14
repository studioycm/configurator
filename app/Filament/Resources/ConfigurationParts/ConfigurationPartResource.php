<?php

namespace App\Filament\Resources\ConfigurationParts;

use App\Filament\Resources\ConfigurationParts\Pages\CreateConfigurationPart;
use App\Filament\Resources\ConfigurationParts\Pages\EditConfigurationPart;
use App\Filament\Resources\ConfigurationParts\Pages\ListConfigurationParts;
use App\Filament\Resources\ConfigurationParts\Schemas\ConfigurationPartForm;
use App\Filament\Resources\ConfigurationParts\Tables\ConfigurationPartsTable;
use App\Models\ConfigurationPart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConfigurationPartResource extends Resource
{
    protected static ?string $model = ConfigurationPart::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConfigurationPartForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigurationPartsTable::configure($table);
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
            'index' => ListConfigurationParts::route('/'),
            'create' => CreateConfigurationPart::route('/create'),
            'edit' => EditConfigurationPart::route('/{record}/edit'),
        ];
    }
}
