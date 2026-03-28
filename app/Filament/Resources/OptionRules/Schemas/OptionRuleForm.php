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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OptionRuleForm
{
    public static function configure(Schema $schema, bool $hideConfigProfile = false): Schema
    {
        return $schema
            ->components([
                Section::make('Activation Conditions')
                    ->description('Add optional context-aware checks that must also match before the rule becomes active.')
                    ->schema([
                        Repeater::make('rule_payload.activate_if')
                            ->label('Activation Conditions')
                            ->defaultItems(0)
                            ->addActionLabel('Add activation condition')
                            ->helperText('Examples: context.territory, context.application, configuration.code_set')
                            ->table([
                                TableColumn::make('Source')->markAsRequired(),
                                TableColumn::make('Operator')->markAsRequired(),
                                TableColumn::make('Value')->markAsRequired(),
                            ])
                            ->schema([
                                TextInput::make('source')
                                    ->label('Source')
                                    ->required(),
                                Select::make('operator')
                                    ->label('Operator')
                                    ->options([
                                        '=' => '=',
                                        '!=' => '!=',
                                        'in' => 'in',
                                        'not_in' => 'not_in',
                                    ])
                                    ->native(false)
                                    ->default('=')
                                    ->required(),
                                TextInput::make('value')
                                    ->label('Value')
                                    ->placeholder('Enter a comma-separated list for multi-value operators')
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Rule Trigger & Scope')
                    ->description('Define which selected option activates this rule and which target attribute it affects.')
                    ->columns(2)
                    ->schema([
                        Select::make('config_profile_id')
                            ->label('Configurator')
                            ->relationship('configProfile', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Choose the configurator that owns this rule.')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('option_attribute_id', null);
                                $set('config_option_id', null);
                                $set('target_attribute_id', null);
                                $set('allowed_option_ids', []);
                                $set('rule_payload.hide_option_ids', []);
                                $set('rule_payload.disable_option_ids', []);
                                $set('rule_payload.label_overrides', []);
                                $set('rule_payload.value_overrides', []);
                                $set('rule_payload.hints', []);
                            })
                            ->required(! $hideConfigProfile)
                            ->hidden($hideConfigProfile)
                            ->dehydrated(! $hideConfigProfile),
                        Select::make('option_attribute_id')
                            ->label('Trigger Attribute')
                            ->disabled(fn (Get $get): bool => blank($get('config_profile_id')))
                            ->options(fn (Get $get): array => self::attributeOptionsForProfile($get('config_profile_id')))
                            ->afterStateHydrated(function (Set $set, ?OptionRule $record): void {
                                if ($record?->config_option_id && $record->optionAttribute) {
                                    $set('option_attribute_id', $record->optionAttribute->id);
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Choose the attribute that contains the option that activates this rule.')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('config_option_id', null);
                            })
                            ->dehydrated(false),
                        Select::make('config_option_id')
                            ->label('Trigger Option')
                            ->disabled(fn (Get $get): bool => blank($get('option_attribute_id')))
                            ->relationship(
                                'option',
                                'label',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('config_attribute_id', $get('option_attribute_id')),
                            )
                            ->getOptionLabelFromRecordUsing(fn (ConfigOption $record): string => self::formatTargetAttributeOptionLabel($record))
                            ->searchable(['label', 'code'])
                            ->preload()
                            ->native(false)
                            ->helperText('When this option is selected, the rule can apply if all activation conditions also match.')
                            ->live()
                            ->required(),
                        Select::make('target_attribute_id')
                            ->label('Target Attribute')
                            ->disabled(fn (Get $get): bool => blank($get('config_option_id')))
                            ->relationship(
                                'targetAttribute',
                                'label',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('config_profile_id', $get('config_profile_id')),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Restriction actions below are applied to this attribute only.')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('allowed_option_ids', []);
                                $set('rule_payload.hide_option_ids', []);
                                $set('rule_payload.disable_option_ids', []);
                            })
                            ->required(),
                    ]),
                Section::make('Rule Lifecycle')
                    ->description('Control how this rule behaves when it matches.')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('dependency_type')
                            ->label('Restriction Mode')
                            ->options(OptionRuleDependencyType::class)
                            ->grouped()
                            ->live()
                            ->helperText('Options outside the allowed target list become hidden or disabled based on this mode.')
                            ->default(OptionRuleDependencyType::Disabled->value)
                            ->required(),
                        ToggleButtons::make('is_active')
                            ->label('Rule State')
                            ->boolean()
                            ->grouped()
                            ->helperText('Inactive rules stay in the admin builder but do not affect the configurator runtime.')
                            ->default(true),
                        Select::make('priority')
                            ->label('Rule Priority')
                            ->options([
                                -10 => 'Low',
                                0 => 'Normal',
                                10 => 'High',
                            ])
                            ->native(false)
                            ->helperText('If multiple active rules override the same option, priority decides the final result.')
                            ->default(0),
                    ]),
                Section::make('Target Attribute Restrictions')
                    ->description('These controls apply only to the selected target attribute while this rule is active.')
                    ->schema([
                        ModalTableSelect::make('allowed_option_ids')
                            ->label('Allowed Target Options')
                            ->multiple()
                            ->helperText('Choose which options remain valid for the target attribute when this rule is active. Options not allowed will be hidden or disabled based on Restriction Mode.')
                            ->dehydrateStateUsing(fn (?array $state): array => array_values($state ?? []))
                            ->getOptionLabelsUsing(fn (Get $get, ?array $values): array => self::targetAttributeOptionLabels($get('target_attribute_id'), $values))
                            ->disabled(fn (Get $get): bool => blank($get('target_attribute_id')))
                            ->hidden(fn (Get $get): bool => blank($get('target_attribute_id')))
                            ->selectAction(fn (Action $action) => $action
                                ->label('Browse target options')
                                ->modalHeading('Select allowed target options')
                                ->modalSubmitActionLabel('Use selected options'))
                            ->tableArguments(fn (Get $get): array => [
                                'attribute_id' => $get('target_attribute_id'),
                            ])
                            ->tableConfiguration(AllowedOptionsTable::class),
                        ModalTableSelect::make('rule_payload.hide_option_ids')
                            ->label('Additional Hidden Target Options')
                            ->multiple()
                            ->helperText('Optionally hide specific target-attribute options in addition to the normal allowed-options restriction.')
                            ->dehydrateStateUsing(fn (?array $state): array => array_values($state ?? []))
                            ->getOptionLabelsUsing(fn (Get $get, ?array $values): array => self::targetAttributeOptionLabels($get('target_attribute_id'), $values))
                            ->disabled(fn (Get $get): bool => blank($get('target_attribute_id')))
                            ->hidden(fn (Get $get): bool => blank($get('target_attribute_id')))
                            ->selectAction(fn (Action $action) => $action
                                ->label('Browse target options')
                                ->modalHeading('Select additional hidden target options')
                                ->modalSubmitActionLabel('Hide selected options'))
                            ->tableArguments(fn (Get $get): array => ['attribute_id' => $get('target_attribute_id')])
                            ->tableConfiguration(AllowedOptionsTable::class),
                        ModalTableSelect::make('rule_payload.disable_option_ids')
                            ->label('Additional Disabled Target Options')
                            ->multiple()
                            ->helperText('Optionally disable specific target-attribute options in addition to the normal allowed-options restriction.')
                            ->dehydrateStateUsing(fn (?array $state): array => array_values($state ?? []))
                            ->getOptionLabelsUsing(fn (Get $get, ?array $values): array => self::targetAttributeOptionLabels($get('target_attribute_id'), $values))
                            ->disabled(fn (Get $get): bool => blank($get('target_attribute_id')))
                            ->hidden(fn (Get $get): bool => blank($get('target_attribute_id')))
                            ->selectAction(fn (Action $action) => $action
                                ->label('Browse target options')
                                ->modalHeading('Select additional disabled target options')
                                ->modalSubmitActionLabel('Disable selected options'))
                            ->tableArguments(fn (Get $get): array => ['attribute_id' => $get('target_attribute_id')])
                            ->tableConfiguration(AllowedOptionsTable::class),
                    ]),
                Section::make('Configurator-Wide Overrides')
                    ->description('Overrides can target any option in this configurator, but they only take effect while this rule is active.')
                    ->schema([
                        Repeater::make('rule_payload.label_overrides')
                            ->label('Override Option Labels')
                            ->defaultItems(0)
                            ->addActionLabel('Add label override')
                            ->helperText('Choose any option in this configurator whose displayed label should change while this rule is active.')
                            ->table([
                                TableColumn::make('Configurator Option')->markAsRequired(),
                                TableColumn::make('Effective Label')->markAsRequired(),
                            ])
                            ->schema([
                                self::makeConfiguratorOptionSelect()
                                    ->required(),
                                TextInput::make('label')
                                    ->label('Effective Label')
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                        Repeater::make('rule_payload.value_overrides')
                            ->label('Override Option Values')
                            ->defaultItems(0)
                            ->addActionLabel('Add value override')
                            ->helperText('Choose any option in this configurator whose effective runtime value should change while this rule is active. This does not edit the original option record.')
                            ->table([
                                TableColumn::make('Configurator Option')->markAsRequired(),
                                TableColumn::make('Effective Value')->markAsRequired(),
                            ])
                            ->schema([
                                self::makeConfiguratorOptionSelect()
                                    ->required(),
                                TextInput::make('value')
                                    ->label('Effective Value')
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                        Repeater::make('rule_payload.hints')
                            ->label('Override Option Hints')
                            ->defaultItems(0)
                            ->addActionLabel('Add hint override')
                            ->helperText('Choose any option in this configurator whose helper hint should be shown or replaced while this rule is active.')
                            ->table([
                                TableColumn::make('Configurator Option')->markAsRequired(),
                                TableColumn::make('Effective Hint')->markAsRequired(),
                            ])
                            ->schema([
                                self::makeConfiguratorOptionSelect()
                                    ->required(),
                                TextInput::make('hint')
                                    ->label('Effective Hint')
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                    ]),

            ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function attributeOptionsForProfile(mixed $configProfileId): array
    {
        if (! is_numeric($configProfileId)) {
            return [];
        }

        return ConfigAttribute::query()
            ->where('config_profile_id', (int) $configProfileId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->mapWithKeys(fn (ConfigAttribute $attribute): array => [$attribute->id => $attribute->label ?: $attribute->name])
            ->all();
    }

    protected static function makeConfiguratorOptionSelect(): Select
    {
        return Select::make('option_id')
            ->label('Configurator Option')
            ->native(false)
            ->searchable()
            ->optionsLimit(50)
            ->loadingMessage('Loading configurator options...')
            ->noSearchResultsMessage('No configurator options found.')
            ->searchPrompt('Search by attribute label, option label, or code')
            ->searchingMessage('Searching configurator options...')
            ->getSearchResultsUsing(fn (Get $get, ?string $search): array => self::searchConfiguratorOptions($get('../../../config_profile_id'), $search))
            ->getOptionLabelUsing(fn (Get $get, mixed $value): ?string => self::resolveConfiguratorOptionLabel($value, $get('../../../config_profile_id')))
            ->helperText("Selecting any option from this configurator. Format: Attribute Label — Option Label. This override is applied only while the current rule matches.");
    }

    /**
     * @return array<int, string>
     */
    protected static function targetAttributeOptionLabels(mixed $targetAttributeId, ?array $values): array
    {
        $optionIds = collect($values ?? [])
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        if (! is_numeric($targetAttributeId) || $optionIds === []) {
            return [];
        }

        return ConfigOption::query()
            ->where('config_attribute_id', (int) $targetAttributeId)
            ->whereIn('id', $optionIds)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (ConfigOption $option): array => [$option->id => self::formatTargetAttributeOptionLabel($option)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function searchConfiguratorOptions(mixed $configProfileId, ?string $search): array
    {
        if (! is_numeric($configProfileId)) {
            return [];
        }

        return ConfigOption::query()
            ->with('attribute:id,label,config_profile_id')
            ->whereHas('attribute', fn (Builder $query) => $query->where('config_profile_id', (int) $configProfileId))
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('label', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('attribute', fn (Builder $attributeQuery) => $attributeQuery->where('label', 'like', "%{$search}%"));
                });
            })
            ->orderBy('config_attribute_id')
            ->orderBy('sort_order')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (ConfigOption $option): array => [$option->id => self::formatConfiguratorOptionLabel($option)])
            ->all();
    }

    protected static function resolveConfiguratorOptionLabel(mixed $value, mixed $configProfileId): ?string
    {
        if (! is_numeric($value) || ! is_numeric($configProfileId)) {
            return null;
        }

        $option = ConfigOption::query()
            ->with('attribute:id,label,config_profile_id')
            ->whereKey((int) $value)
            ->whereHas('attribute', fn (Builder $query) => $query->where('config_profile_id', (int) $configProfileId))
            ->first();

        if (! $option instanceof ConfigOption) {
            return null;
        }

        return self::formatConfiguratorOptionLabel($option);
    }

    protected static function formatConfiguratorOptionLabel(ConfigOption $option): string
    {
        $attributeLabel = $option->attribute?->label ?? 'Unknown Attribute';

        return $attributeLabel.' — '.$option->label;
    }

    protected static function formatTargetAttributeOptionLabel(ConfigOption $option): string
    {
        return filled($option->code)
            ? $option->label.' ('.$option->code.')'
            : $option->label;
    }
}
