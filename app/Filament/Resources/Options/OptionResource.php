<?php

namespace App\Filament\Resources\Options;

use App\Filament\Resources\Options\Pages\CreateOption;
use App\Filament\Resources\Options\Pages\EditOption;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\Options\Schemas\OptionForm;
use App\Filament\Resources\Options\Tables\OptionsTable;
use App\Models\Option;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class OptionResource extends Resource
{
    protected static ?string $model = Option::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Product configuration';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Option';

    protected static ?string $pluralModelLabel = 'Options';

    protected static ?string $recordTitleAttribute = 'code';

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
        return OptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListOptions::route('/'), 'create' => CreateOption::route('/create'), 'edit' => EditOption::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['attribute', 'value'])->withCount('inclusions as configurator_options_count');
    }
}
