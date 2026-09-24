<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Pages\CreateConfigOption;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Pages\EditConfigOption;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Pages\ListConfigOptions;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\RelationManagers\OptionRulesRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Schemas\ConfigOptionForm;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Tables\ConfigOptionsTable;
use Tests\Fixtures\Legacy\Models\ConfigOption;

class ConfigOptionResource extends Resource
{
    protected static ?string $model = ConfigOption::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurator';

    protected static ?string $navigationLabel = 'Options';

    protected static ?string $pluralModelLabel = 'Options';

    protected static ?string $modelLabel = 'Option';

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConfigOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigOptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'rules' => OptionRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConfigOptions::route('/'),
            'create' => CreateConfigOption::route('/create'),
            'edit' => EditConfigOption::route('/{record}/edit'),
        ];
    }
}
