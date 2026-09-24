<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\RelationManagers;

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
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\Schemas\ConfigProfileForm;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\Tables\ConfigProfilesTable;

class ConfigProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'configProfiles';

    public function form(Schema $schema): Schema
    {
        return ConfigProfileForm::configure($schema, hideProductProfile: true);
    }

    public function table(Table $table): Table
    {
        return ConfigProfilesTable::configure($table)
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
