<?php

namespace App\Filament\Resources\ConfigOptions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConfigOptionForm
{
    public static function configure(Schema $schema, bool $hideConfigAttribute = false): Schema
    {
        return $schema
            ->components([
                Section::make('Option Identity')
                    ->description('Define the option that can be selected for a configurator attribute.')
                    ->columns(2)
                    ->schema([
                        Select::make('config_attribute_id')
                            ->label('Attribute')
                            ->relationship('attribute', 'label')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Choose the attribute that owns this option.')
                            ->required(! $hideConfigAttribute)
                            ->hidden($hideConfigAttribute)
                            ->dehydrated(! $hideConfigAttribute),
                        TextInput::make('code')
                            ->label('Option Code')
                            ->helperText('This code contributes to the generated configuration code when the option is selected.')
                            ->required(),
                        TextInput::make('label')
                            ->label('Option Label')
                            ->helperText('Default customer-facing label unless an active rule overrides it.')
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Availability & Ordering')
                    ->description('Control defaults, active state, and how the option is ordered inside its attribute.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->helperText('Lower values appear earlier in toggle groups and select menus.')
                            ->numeric(),
                        Toggle::make('is_default')
                            ->label('Default')
                            ->helperText('Used as the preferred default when the runtime first loads or repairs invalid selections.')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive options remain in admin data but are excluded from base runtime selections.')
                            ->required(),
                    ]),
                Section::make('UI Metadata')
                    ->description('Store flat JSON-backed presentation helpers. Active rules can still override effective labels, values, and hints at runtime.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ui_meta.label_short')
                            ->label('Short Label')
                            ->helperText('Optional compact label for tighter UI layouts. Rules can still override the effective label at runtime.'),
                        TextInput::make('ui_meta.badge')
                            ->label('Badge')
                            ->helperText('Optional static badge text shown in admin/runtime surfaces that consume it.'),
                        TextInput::make('ui_meta.color')
                            ->label('Color')
                            ->helperText('Optional flat color keyword for UIs that style option badges or accents.'),
                        Toggle::make('ui_meta.hidden_by_default')
                            ->label('Hidden by Default')
                            ->helperText('Hide this option before any rules run. Useful for partner demos or staged rollout states.')
                            ->default(false),
                        Toggle::make('ui_meta.disabled_by_default')
                            ->label('Disabled by Default')
                            ->helperText('Show this option but keep it disabled until rules or custom logic allow it.')
                            ->default(false),
                        Textarea::make('ui_meta.hint')
                            ->label('Hint')
                            ->helperText('Optional default helper hint shown unless an active rule replaces it.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
