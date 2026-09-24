<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Schemas\ConfigurationSpecificationForm;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\Tables\ConfigurationSpecificationsTable;

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
