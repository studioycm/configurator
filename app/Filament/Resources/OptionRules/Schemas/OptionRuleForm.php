<?php

namespace App\Filament\Resources\OptionRules\Schemas;

use App\Filament\Resources\OptionRules\Tables\AllowedOptionsTable;
use App\Models\ConfigAttribute;
use App\Models\ConfigOption;
use App\Models\OptionRule;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use JsonException;

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
                    ->options([
                        'hidden' => 'Hidden',
                        'disabled' => 'Disabled',
                    ])
                    ->grouped()
                    ->live()
                    ->formatStateUsing(fn (?OptionRule $record): ?string => $record?->uiMode())
                    ->dehydrated(false),
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
                //                CodeEditor::make('rule_payload')
                //                    ->label('Rules (json)')
                //                    ->language(Language::Json),
                Textarea::make('rule_payload')
                    ->label('Rule Payload')
                    ->rows(8)
                    ->formatStateUsing(fn (mixed $state): string => self::encodeJson($state))
                    ->dehydrateStateUsing(function (?string $state, Get $get): ?array {
                        $payload = self::decodeJson($state) ?? [];
                        $dependencyType = $get('dependency_type');

                        if (is_string($dependencyType) && $dependencyType !== '') {
                            $payload['ui_mode'] = $dependencyType;
                        }

                        return $payload === [] ? null : $payload;
                    })
                    ->columnSpanFull(),
            ]);
    }

    private static function encodeJson(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '';
        }

        if (is_string($state)) {
            return $state;
        }

        try {
            return json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private static function decodeJson(?string $state): ?array
    {
        $state = trim((string) $state);

        if ($state === '') {
            return null;
        }

        try {
            /** @var array<int|string, mixed> $decoded */
            $decoded = json_decode($state, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException) {
            return null;
        }
    }
}
