<?php

namespace App\Filament\Resources\ProductConfigurations\RelationManagers;

use App\Filament\Resources\ConfigurationSpecifications\Schemas\ConfigurationSpecificationForm;
use App\Filament\Resources\ConfigurationSpecifications\Tables\ConfigurationSpecificationsTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ConfigurationSpecificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'configurationSpecifications';

    public function form(Schema $schema): Schema
    {
        return ConfigurationSpecificationForm::configure($schema, hideProductConfiguration: true);
    }

    public function table(Table $table): Table
    {
        return ConfigurationSpecificationsTable::configure($table)
            ->recordTitleAttribute('key')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
