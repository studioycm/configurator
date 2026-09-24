<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Schemas\ConfigOptionForm;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\Tables\ConfigOptionsTable;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    public function form(Schema $schema): Schema
    {
        return ConfigOptionForm::configure($schema, hideConfigAttribute: true);
    }

    public function table(Table $table): Table
    {
        return ConfigOptionsTable::configure($table)
            ->recordTitleAttribute('label')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
