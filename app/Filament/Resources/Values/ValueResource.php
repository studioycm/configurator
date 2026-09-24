<?php

namespace App\Filament\Resources\Values;

use App\Filament\Resources\Values\Pages\CreateValue;
use App\Filament\Resources\Values\Pages\EditValue;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Filament\Resources\Values\Schemas\ValueForm;
use App\Filament\Resources\Values\Tables\ValuesTable;
use App\Models\Value;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ValueResource extends Resource
{
    protected static ?string $model = Value::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Product configuration';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Master value';

    protected static ?string $pluralModelLabel = 'Master values';

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
        return ValueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValuesTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListValues::route('/'), 'create' => CreateValue::route('/create'), 'edit' => EditValue::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('options');
    }
}
