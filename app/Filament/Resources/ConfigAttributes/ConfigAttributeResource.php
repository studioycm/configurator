<?php

namespace App\Filament\Resources\ConfigAttributes;

use App\Filament\Resources\ConfigAttributes\Pages\CreateConfigAttribute;
use App\Filament\Resources\ConfigAttributes\Pages\EditConfigAttribute;
use App\Filament\Resources\ConfigAttributes\Pages\ListConfigAttributes;
use App\Filament\Resources\ConfigAttributes\Schemas\ConfigAttributeForm;
use App\Filament\Resources\ConfigAttributes\Tables\ConfigAttributesTable;
use App\Models\ConfigAttribute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConfigAttributeResource extends Resource
{
    protected static ?string $model = ConfigAttribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConfigAttributeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigAttributesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'options' => \App\Filament\Resources\ConfigAttributes\RelationManagers\OptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConfigAttributes::route('/'),
            'create' => CreateConfigAttribute::route('/create'),
            'edit' => EditConfigAttribute::route('/{record}/edit'),
        ];
    }
}
