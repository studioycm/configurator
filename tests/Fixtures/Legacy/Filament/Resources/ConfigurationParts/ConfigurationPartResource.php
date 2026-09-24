<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\Pages\CreateConfigurationPart;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\Pages\EditConfigurationPart;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\Pages\ListConfigurationParts;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\RelationManagers\FileAttachmentsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\Schemas\ConfigurationPartForm;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\Tables\ConfigurationPartsTable;
use Tests\Fixtures\Legacy\Models\ConfigurationPart;

class ConfigurationPartResource extends Resource
{
    protected static ?string $model = ConfigurationPart::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Product Configurations';

    protected static ?string $navigationLabel = 'Configuration associated Parts';

    protected static ?string $pluralModelLabel = 'Configuration Parts';

    protected static ?string $modelLabel = 'Configuration Part';

    protected static ?int $navigationSort = 21;

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
            'fileAttachments' => FileAttachmentsRelationManager::class,
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
