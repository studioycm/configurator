<?php

namespace App\Filament\Resources\Parts\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConfigurationPartsRelationManager extends RelationManager
{
    protected static string $relationship = 'configurationParts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('product_configuration_id')
                    ->numeric(),
                TextInput::make('part_number'),
                TextInput::make('label'),
                TextInput::make('material'),
                TextInput::make('quantity')
                    ->numeric(),
                TextInput::make('unit'),
                TextInput::make('sort_order')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('productConfiguration.name')->label('Configuration'),
                TextColumn::make('label')->searchable(),
                TextColumn::make('quantity'),
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
