<?php

namespace App\Filament\Resources\Configurators;

use App\Filament\Resources\Configurators\Pages\CreateConfigurator;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\Pages\ListConfigurators;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorForm;
use App\Filament\Resources\Configurators\Tables\ConfiguratorsTable;
use App\Models\Configurator;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ConfiguratorResource extends Resource
{
    protected static ?string $model = Configurator::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurator';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Configurator';

    protected static ?string $pluralModelLabel = 'Configurators';

    protected static ?string $recordTitleAttribute = 'name';

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
        return ConfiguratorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfiguratorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListConfigurators::route('/'), 'create' => CreateConfigurator::route('/create'), 'edit' => EditConfigurator::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['groups', 'attributes as configurator_attributes_count', 'rules']);
    }
}
