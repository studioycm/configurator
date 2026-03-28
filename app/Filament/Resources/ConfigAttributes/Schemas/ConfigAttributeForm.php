<?php

namespace App\Filament\Resources\ConfigAttributes\Schemas;

use App\ConfigInputType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConfigAttributeForm
{
    public static function configure(Schema $schema, bool $hideConfigProfile = false): Schema
    {
        return $schema
            ->components([
                Section::make('Attribute Identity')
                    ->description('Define the core attribute identity used by the configurator builder and runtime.')
                    ->columns(2)
                    ->schema([
                        Select::make('config_profile_id')
                            ->label('Configurator')
                            ->relationship('configProfile', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Choose the configurator that owns this attribute.')
                            ->required(! $hideConfigProfile)
                            ->hidden($hideConfigProfile)
                            ->dehydrated(! $hideConfigProfile),
                        Select::make('input_type')
                            ->label('Input Type')
                            ->options(ConfigInputType::class)
                            ->native(false)
                            ->helperText('Controls how this attribute is rendered in the configurator runtime. Use Toggle for compact option sets and Select for longer lists.')
                            ->default(ConfigInputType::Toggle->value)
                            ->required(),
                        TextInput::make('label')
                            ->label('Attribute Label')
                            ->helperText('Customer-facing label shown in the configurator and admin tables.')
                            ->required(),
                        TextInput::make('name')
                            ->label('Internal Name')
                            ->helperText('Stable internal identifier used by developers, seeders, and debugging tools.')
                            ->required(),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->helperText('Optional stable slug used in manifests and runtime/debug output.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Ordering & Runtime Behavior')
                    ->description('Set how the attribute is ordered, required, and encoded in configuration output.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->helperText('Controls the stage order in builder screens and the demo page.')
                            ->required()
                            ->numeric(),
                        TextInput::make('segment_index')
                            ->label('Segment Index')
                            ->helperText('Optional position in the generated configuration code sequence.')
                            ->numeric(),
                        Toggle::make('is_required')
                            ->label('Required')
                            ->helperText('Required attributes must have a valid selection before a complete configuration code can be generated.')
                            ->required(),
                    ]),
                Section::make('UI Metadata')
                    ->description('Store flat JSON-backed helpers used by the current engine and admin tables.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ui_schema.group')
                            ->label('Group Key')
                            ->helperText('Optional flat grouping key for organizing related attributes in the builder or runtime.'),
                        Toggle::make('ui_schema.auto_select_first_allowed')
                            ->label('Auto Select First Allowed')
                            ->helperText('When enabled, the runtime will fall back to the first allowed option if the current selection becomes invalid.')
                            ->default(true),
                        Textarea::make('ui_schema.help_text')
                            ->label('Help Text')
                            ->helperText('Optional helper copy shown with this attribute in the runtime configurator.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
