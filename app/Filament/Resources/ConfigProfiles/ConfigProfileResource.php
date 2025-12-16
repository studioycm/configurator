<?php

namespace App\Filament\Resources\ConfigProfiles;

use App\Filament\Resources\ConfigProfiles\Pages\CreateConfigProfile;
use App\Filament\Resources\ConfigProfiles\Pages\EditConfigProfile;
use App\Filament\Resources\ConfigProfiles\Pages\ListConfigProfiles;
use App\Filament\Resources\ConfigProfiles\Schemas\ConfigProfileForm;
use App\Filament\Resources\ConfigProfiles\Tables\ConfigProfilesTable;
use App\Models\ConfigProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConfigProfileResource extends Resource
{
    protected static ?string $model = ConfigProfile::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurator';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConfigProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'attributes' => \App\Filament\Resources\ConfigProfiles\RelationManagers\AttributesRelationManager::class,
            'rules' => \App\Filament\Resources\ConfigProfiles\RelationManagers\RulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConfigProfiles::route('/'),
            'create' => CreateConfigProfile::route('/create'),
            'edit' => EditConfigProfile::route('/{record}/edit'),
        ];
    }
}
