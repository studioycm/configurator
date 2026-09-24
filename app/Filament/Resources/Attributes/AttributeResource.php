<?php

namespace App\Filament\Resources\Attributes;

use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Attributes\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Attributes\Schemas\AttributeForm;
use App\Filament\Resources\Attributes\Tables\AttributesTable;
use App\Models\Attribute;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Product configuration';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Attribute';

    protected static ?string $pluralModelLabel = 'Attributes';

    protected static ?string $recordTitleAttribute = 'label';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-catalog');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('manage-catalog');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('manage-catalog');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('manage-catalog');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('manage-catalog');
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return AttributeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttributesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [OptionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListAttributes::route('/'), 'create' => CreateAttribute::route('/create'), 'edit' => EditAttribute::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['options', 'inclusions as configurator_attributes_count']);
    }
}
