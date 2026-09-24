<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\Pages\CreateConfigProfile;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\Pages\EditConfigProfile;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\Pages\ListConfigProfiles;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\Schemas\ConfigProfileForm;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\Tables\ConfigProfilesTable;
use Tests\Fixtures\Legacy\Models\ConfigProfile;

class ConfigProfileResource extends Resource
{
    protected static ?string $model = ConfigProfile::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurator';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return 'Configurators';
    }

    protected static ?string $pluralModelLabel = 'Configurators';

    protected static ?string $modelLabel = 'Configurator';

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
            'attributes' => RelationManagers\AttributesRelationManager::class,
            'rules' => RelationManagers\RulesRelationManager::class,
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
