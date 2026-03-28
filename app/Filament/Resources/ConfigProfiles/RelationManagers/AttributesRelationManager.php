<?php

namespace App\Filament\Resources\ConfigProfiles\RelationManagers;

use App\Filament\Resources\ConfigAttributes\Schemas\ConfigAttributeForm;
use App\Filament\Resources\ConfigAttributes\Tables\ConfigAttributesTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributes';

    public function form(Schema $schema): Schema
    {
        return ConfigAttributeForm::configure($schema, hideConfigProfile: true);
    }

    public function table(Table $table): Table
    {
        return ConfigAttributesTable::configure($table)
            ->recordTitleAttribute('name')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
