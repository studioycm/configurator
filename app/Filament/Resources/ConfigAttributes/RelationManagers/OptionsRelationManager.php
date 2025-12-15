<?php

namespace App\Filament\Resources\ConfigAttributes\RelationManagers;

use App\Filament\Resources\ConfigOptions\ConfigOptionResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('config_attribute_id')
                    ->label('Attribute')
                    ->relationship('attribute', 'label')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('sort_order')->numeric()->sortable(),
                IconColumn::make('is_default')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->url(fn () => ConfigOptionResource::getUrl('create')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => ConfigOptionResource::getUrl('edit', ['record' => $record])),
                EditAction::make()
                    ->url(fn ($record) => ConfigOptionResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
