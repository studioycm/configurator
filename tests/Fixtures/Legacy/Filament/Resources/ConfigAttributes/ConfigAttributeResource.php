<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\Pages\CreateConfigAttribute;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\Pages\EditConfigAttribute;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\Pages\ListConfigAttributes;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\RelationManagers\OptionsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\Schemas\ConfigAttributeForm;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\Tables\ConfigAttributesTable;
use Tests\Fixtures\Legacy\Models\ConfigAttribute;

class ConfigAttributeResource extends Resource
{
    protected static ?string $model = ConfigAttribute::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurator';

    protected static ?string $navigationLabel = 'Attributes';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return 'Attributes';
    }

    protected static ?string $pluralModelLabel = 'Attributes';

    protected static ?string $modelLabel = 'Attribute';

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
            'options' => OptionsRelationManager::class,
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
