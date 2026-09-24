<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Schemas\OptionRuleForm;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\Tables\OptionRulesTable;

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
