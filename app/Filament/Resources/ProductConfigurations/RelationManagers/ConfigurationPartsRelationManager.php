<?php

namespace App\Filament\Resources\ProductConfigurations\RelationManagers;

use App\Filament\Resources\ConfigurationParts\Schemas\ConfigurationPartForm;
use App\Filament\Resources\ConfigurationParts\Tables\ConfigurationPartsTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ConfigurationPartsRelationManager extends RelationManager
{
    protected static string $relationship = 'configurationParts';

    public function form(Schema $schema): Schema
    {
        return ConfigurationPartForm::configure($schema, hideProductConfiguration: true);
    }

    public function table(Table $table): Table
    {
        return ConfigurationPartsTable::configure($table)
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
