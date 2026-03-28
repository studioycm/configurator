<?php

namespace App\Filament\Resources\ConfigProfiles\Schemas;

use App\ConfigProfileScope;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConfigProfileForm
{
    public static function configure(Schema $schema, bool $hideProductProfile = false): Schema
    {
        return $schema
            ->components([
                Select::make('product_profile_id')
                    ->relationship('productProfile', 'name')
                    ->searchable()
                    ->preload()
                    ->required(! $hideProductProfile)
                    ->hidden($hideProductProfile)
                    ->dehydrated(! $hideProductProfile),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('scope')
                    ->options(ConfigProfileScope::class),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('extra_rules_json')
                    ->columnSpanFull(),
                Repeater::make('runtime_context_schema')
                    ->label('Runtime Context Schema')
                    ->defaultItems(0)
                    ->table([
                        TableColumn::make('Key')->markAsRequired(),
                        TableColumn::make('Label')->markAsRequired(),
                        TableColumn::make('Default'),
                        TableColumn::make('Options'),
                    ])
                    ->schema([
                        TextInput::make('key')
                            ->required(),
                        TextInput::make('label')
                            ->required(),
                        TextInput::make('type')
                            ->default('select')
                            ->required(),
                        Toggle::make('required')
                            ->default(false),
                        TextInput::make('default'),
                        Repeater::make('options')
                            ->simple(
                                TextInput::make('value')
                                    ->required()
                            )
                            ->addActionLabel('Add option')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
