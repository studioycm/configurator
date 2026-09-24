<?php

namespace App\Filament\Resources\Groups;

use App\Filament\Resources\Groups\Pages\CreateGroup;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Groups\Schemas\GroupForm;
use App\Filament\Resources\Groups\Tables\GroupsTable;
use App\Models\Group;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Group';

    protected static ?string $pluralModelLabel = 'Groups';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-catalog');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('manage-catalog');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('manage-catalog');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('manage-catalog');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return GroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListGroups::route('/'), 'create' => CreateGroup::route('/create'), 'edit' => EditGroup::route('/{record}/edit')];
    }
}
