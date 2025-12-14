<?php

namespace App\Filament\Resources\ConfigOptions\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OptionRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('config_profile_id')
                    ->required()
                    ->numeric(),
                TextInput::make('target_attribute_id')
                    ->required()
                    ->numeric(),
                TextInput::make('allowed_option_ids')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('config_profile_id')->label('Profile'),
                TextColumn::make('target_attribute_id')->label('Target Attribute'),
                TextColumn::make('allowed_option_ids')->label('Allowed Option Ids'),
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
