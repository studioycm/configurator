<?php

namespace App\Filament\Resources\ConfigAttributes\RelationManagers;

use App\Filament\Resources\ConfigOptions\Schemas\ConfigOptionForm;
use App\Filament\Resources\ConfigOptions\Tables\ConfigOptionsTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

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
