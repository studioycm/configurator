<?php

namespace App\Filament\Resources\OptionRules;

use App\Filament\Resources\OptionRules\Pages\CreateOptionRule;
use App\Filament\Resources\OptionRules\Pages\EditOptionRule;
use App\Filament\Resources\OptionRules\Pages\ListOptionRules;
use App\Filament\Resources\OptionRules\Schemas\OptionRuleForm;
use App\Filament\Resources\OptionRules\Tables\OptionRulesTable;
use App\Models\OptionRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OptionRuleResource extends Resource
{
    protected static ?string $model = OptionRule::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurator';

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
