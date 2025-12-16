<?php

namespace App\Filament\Resources\ConfigOptions;

use App\Filament\Resources\ConfigOptions\Pages\CreateConfigOption;
use App\Filament\Resources\ConfigOptions\Pages\EditConfigOption;
use App\Filament\Resources\ConfigOptions\Pages\ListConfigOptions;
use App\Filament\Resources\ConfigOptions\Schemas\ConfigOptionForm;
use App\Filament\Resources\ConfigOptions\Tables\ConfigOptionsTable;
use App\Models\ConfigOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConfigOptionResource extends Resource
{
    protected static ?string $model = ConfigOption::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurator';

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
            'rules' => \App\Filament\Resources\ConfigOptions\RelationManagers\OptionRulesRelationManager::class,
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
