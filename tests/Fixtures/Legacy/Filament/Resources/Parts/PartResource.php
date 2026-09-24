<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\Parts;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\Pages\CreatePart;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\Pages\EditPart;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\Pages\ListParts;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\RelationManagers\ConfigurationPartsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\RelationManagers\FileAttachmentsRelationManager;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\Schemas\PartForm;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\Tables\PartsTable;
use Tests\Fixtures\Legacy\Models\Part;

class PartResource extends Resource
{
    protected static ?string $model = Part::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Product Configurations';

    protected static ?string $navigationLabel = 'Parts';

    protected static ?string $pluralModelLabel = 'Parts';

    protected static ?string $modelLabel = 'Part';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PartForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'configurationParts' => ConfigurationPartsRelationManager::class,
            'fileAttachments' => FileAttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParts::route('/'),
            'create' => CreatePart::route('/create'),
            'edit' => EditPart::route('/{record}/edit'),
        ];
    }
}
