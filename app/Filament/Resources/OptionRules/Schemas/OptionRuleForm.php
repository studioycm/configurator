<?php

namespace App\Filament\Resources\OptionRules\Schemas;

use App\Filament\Resources\OptionRules\Tables\AllowedOptionsTable;
use App\Models\ConfigAttribute;
use App\Models\ConfigOption;
use App\Models\OptionRule;
use App\OptionRuleDependencyType;
use Filament\Actions\Action;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OptionRuleForm
{
    public static function configure(Schema $schema, bool $hideConfigProfile = false): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Select::make('config_profile_id')
                    ->label('Configurator')
                    ->relationship('configProfile', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(! $hideConfigProfile)
                    ->hidden($hideConfigProfile)
                    ->dehydrated(! $hideConfigProfile),
                Select::make('option_attribute_id')
                    ->label('Attribute')
                    ->disabled(fn (Get $get): bool => empty($get('config_profile_id')))
                    ->options(fn (Get $get) => ConfigAttribute::query()
                        ->where('config_profile_id', $get('config_profile_id'))
                        ->pluck('label', 'id')
                    )
                    ->afterStateHydrated(function (Set $set, OptionRule $record) {
                        if ($record && $record->config_option_id) {
                            // Find the attribute ID that belongs to the saved option
                            $set('option_attribute_id', $record->optionAttribute->id);
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('config_option_id', null))
                    ->dehydrated(false),
                Select::make('config_option_id')
                    ->label('Option')
                    ->disabled(fn (Get $get): bool => empty($get('option_attribute_id')))
                    ->relationship('option', 'label', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('config_attribute_id', $get('option_attribute_id')))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('target_attribute_id')
                    ->label('Target Attribute')
                    ->disabled(fn (Get $get): bool => empty($get('config_option_id')))
                    ->relationship('targetAttribute', 'label', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('config_profile_id', $get('config_profile_id')))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('allowed_option_ids', []))
                    ->required(),
                ModalTableSelect::make('allowed_option_ids')
                    ->label('Allowed Options')
                    ->multiple()
                    ->dehydrateStateUsing(fn (?array $state): array => array_values($state ?? []))
                    ->getOptionLabelsUsing(fn (?array $values): array => ConfigOption::query()
                        ->whereIn('id', $values ?? [])
                        ->pluck('label', 'id')
                        ->all()
                    )
                    ->disabled(fn (Get $get): bool => empty($get('target_attribute_id')))
                    ->hidden(fn (Get $get): bool => empty($get('target_attribute_id')))
                    ->selectAction(fn (Action $action) => $action->modalHeading('Select Allowed Options'))
                    ->tableArguments(fn (Get $get): array => [
                        'attribute_id' => $get('target_attribute_id'),
                    ])
                    ->tableConfiguration(AllowedOptionsTable::class),
                ToggleButtons::make('dependency_type')
                    ->label('Dependency Type')
                    ->options(OptionRuleDependencyType::class)
                    ->grouped()
                    ->live()
                    ->default(OptionRuleDependencyType::Disabled->value)
                    ->required(),
                ToggleButtons::make('is_active')
                    ->label('Rule State')
                    ->boolean()
                    ->grouped()
                    ->default(true),
                Select::make('priority')
                    ->options([
                        -10 => 'Low',
                        0 => 'Normal',
                        10 => 'High',
                    ])
                    ->default(0)
                    ->native(false),
                ModalTableSelect::make('rule_payload.hide_option_ids')
                    ->label('Hide Options')
                    ->multiple()
                    ->dehydrateStateUsing(fn (?array $state): array => array_values($state ?? []))
                    ->getOptionLabelsUsing(fn (?array $values): array => ConfigOption::query()
                        ->whereIn('id', $values ?? [])
                        ->pluck('label', 'id')
                        ->all())
                    ->disabled(fn (Get $get): bool => empty($get('target_attribute_id')))
                    ->hidden(fn (Get $get): bool => empty($get('target_attribute_id')))
                    ->selectAction(fn (Action $action) => $action->modalHeading('Select Hidden Options'))
                    ->tableArguments(fn (Get $get): array => ['attribute_id' => $get('target_attribute_id')])
                    ->tableConfiguration(AllowedOptionsTable::class),
                ModalTableSelect::make('rule_payload.disable_option_ids')
                    ->label('Disable Options')
                    ->multiple()
                    ->dehydrateStateUsing(fn (?array $state): array => array_values($state ?? []))
                    ->getOptionLabelsUsing(fn (?array $values): array => ConfigOption::query()
                        ->whereIn('id', $values ?? [])
                        ->pluck('label', 'id')
                        ->all())
                    ->disabled(fn (Get $get): bool => empty($get('target_attribute_id')))
                    ->hidden(fn (Get $get): bool => empty($get('target_attribute_id')))
                    ->selectAction(fn (Action $action) => $action->modalHeading('Select Disabled Options'))
                    ->tableArguments(fn (Get $get): array => ['attribute_id' => $get('target_attribute_id')])
                    ->tableConfiguration(AllowedOptionsTable::class),
                Repeater::make('rule_payload.label_overrides')
                    ->label('Label Overrides')
                    ->defaultItems(0)
                    ->table([
                        TableColumn::make('Option')->markAsRequired(),
                        TableColumn::make('Label')->markAsRequired(),
                    ])
                    ->schema([
                        Select::make('option_id')
                            ->label('Option')
                            ->options(fn (Get $get): array => ConfigOption::query()
                                ->where('config_attribute_id', $get('../../target_attribute_id'))
                                ->pluck('label', 'id')
                                ->all())
                            ->required(),
                        TextInput::make('label')
                            ->required(),
                    ])
                    ->columnSpanFull(),
                Repeater::make('rule_payload.value_overrides')
                    ->label('Value Overrides')
                    ->defaultItems(0)
                    ->table([
                        TableColumn::make('Option')->markAsRequired(),
                        TableColumn::make('Value')->markAsRequired(),
                    ])
                    ->schema([
                        Select::make('option_id')
                            ->label('Option')
                            ->options(fn (Get $get): array => ConfigOption::query()
                                ->where('config_attribute_id', $get('../../target_attribute_id'))
                                ->pluck('label', 'id')
                                ->all())
                            ->required(),
                        TextInput::make('value')
                            ->required(),
                    ])
                    ->columnSpanFull(),
                Repeater::make('rule_payload.hints')
                    ->label('Hints')
                    ->defaultItems(0)
                    ->table([
                        TableColumn::make('Option')->markAsRequired(),
                        TableColumn::make('Hint')->markAsRequired(),
                    ])
                    ->schema([
                        Select::make('option_id')
                            ->label('Option')
                            ->options(fn (Get $get): array => ConfigOption::query()
                                ->where('config_attribute_id', $get('../../target_attribute_id'))
                                ->pluck('label', 'id')
                                ->all())
                            ->required(),
                        TextInput::make('hint')
                            ->required(),
                    ])
                    ->columnSpanFull(),
                Repeater::make('rule_payload.activate_if')
                    ->label('Activation Conditions')
                    ->defaultItems(0)
                    ->table([
                        TableColumn::make('Source')->markAsRequired(),
                        TableColumn::make('Operator')->markAsRequired(),
                        TableColumn::make('Value')->markAsRequired(),
                    ])
                    ->schema([
                        TextInput::make('source')
                            ->required(),
                        Select::make('operator')
                            ->options([
                                '=' => '=',
                                '!=' => '!=',
                                'in' => 'in',
                                'not_in' => 'not_in',
                            ])
                            ->default('=')
                            ->required(),
                        TextInput::make('value')
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
