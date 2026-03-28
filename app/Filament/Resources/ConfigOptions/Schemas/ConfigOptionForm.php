<?php

namespace App\Filament\Resources\ConfigOptions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConfigOptionForm
{
    public static function configure(Schema $schema, bool $hideConfigAttribute = false): Schema
    {
        return $schema
            ->components([
                Select::make('config_attribute_id')
                    ->label('Attribute')
                    ->relationship('attribute', 'label')
                    ->searchable()
                    ->preload()
                    ->required(! $hideConfigAttribute)
                    ->hidden($hideConfigAttribute)
                    ->dehydrated(! $hideConfigAttribute),
                TextInput::make('label')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric(),
                Toggle::make('is_default')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('ui_meta.label_short')
                    ->label('Short Label')
                    ->helperText('Optional default compact label. Rules can still override the effective label at runtime.'),
                TextInput::make('ui_meta.badge')
                    ->label('Badge'),
                TextInput::make('ui_meta.color')
                    ->label('Color'),
                Toggle::make('ui_meta.hidden_by_default')
                    ->label('Hidden by Default')
                    ->default(false),
                Toggle::make('ui_meta.disabled_by_default')
                    ->label('Disabled by Default')
                    ->default(false),
                Textarea::make('ui_meta.hint')
                    ->label('Hint')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
