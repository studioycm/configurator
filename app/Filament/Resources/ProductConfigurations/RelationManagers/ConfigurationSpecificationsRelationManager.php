<?php

namespace App\Filament\Resources\ProductConfigurations\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConfigurationSpecificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'configurationSpecifications';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('spec_group'),
                TextInput::make('key')
                    ->required(),
                TextInput::make('value'),
                TextInput::make('unit'),
                TextInput::make('sort_order')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                TextColumn::make('spec_group'),
                TextColumn::make('key')->searchable(),
                TextColumn::make('value'),
                TextColumn::make('unit'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
