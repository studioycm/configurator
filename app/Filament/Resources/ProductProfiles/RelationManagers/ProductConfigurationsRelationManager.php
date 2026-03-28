<?php

namespace App\Filament\Resources\ProductProfiles\RelationManagers;

use App\Filament\Resources\ProductConfigurations\Schemas\ProductConfigurationForm;
use App\Filament\Resources\ProductConfigurations\Tables\ProductConfigurationsTable;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProductConfigurationsRelationManager extends RelationManager
{
    protected static string $relationship = 'productConfigurations';

    public function form(Schema $schema): Schema
    {
        return ProductConfigurationForm::configure($schema, hideProductProfile: true);
    }

    public function table(Table $table): Table
    {
        return ProductConfigurationsTable::configure($table)
            ->recordTitleAttribute('name')
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
