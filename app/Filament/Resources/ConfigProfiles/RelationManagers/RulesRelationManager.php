<?php

namespace App\Filament\Resources\ConfigProfiles\RelationManagers;

use App\Filament\Resources\OptionRules\Schemas\OptionRuleForm;
use App\Filament\Resources\OptionRules\Tables\OptionRulesTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return OptionRuleForm::configure($schema, hideConfigProfile: true);
    }

    public function table(Table $table): Table
    {
        return OptionRulesTable::configure($table)
            ->recordTitleAttribute('id')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
