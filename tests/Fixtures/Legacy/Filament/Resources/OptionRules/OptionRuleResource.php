<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\OptionRules;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Pages\CreateOptionRule;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Pages\EditOptionRule;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Pages\ListOptionRules;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Schemas\OptionRuleForm;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Tables\OptionRulesTable;
use Tests\Fixtures\Legacy\Models\OptionRule;

class OptionRuleResource extends Resource
{
    protected static ?string $model = OptionRule::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurator';

    protected static ?string $navigationLabel = 'Rules';

    protected static ?string $pluralModelLabel = 'Rules';

    protected static ?string $modelLabel = 'Rule';

    protected static ?int $navigationSort = 13;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OptionRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OptionRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOptionRules::route('/'),
            'create' => CreateOptionRule::route('/create'),
            'edit' => EditOptionRule::route('/{record}/edit'),
        ];
    }
}
