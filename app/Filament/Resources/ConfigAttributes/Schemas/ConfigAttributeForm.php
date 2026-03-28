<?php

namespace App\Filament\Resources\ConfigAttributes\Schemas;

use App\ConfigInputType;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use JsonException;

class ConfigAttributeForm
{
    public static function configure(Schema $schema, bool $hideConfigProfile = false): Schema
    {
        return $schema
            ->components([
                Select::make('config_profile_id')
                    ->relationship('configProfile', 'name')
                    ->searchable()
                    ->preload()
                    ->required(! $hideConfigProfile)
                    ->hidden($hideConfigProfile)
                    ->dehydrated(! $hideConfigProfile),
                TextInput::make('name')
                    ->required(),
                TextInput::make('label'),
                TextInput::make('slug'),
                Select::make('input_type')
                    ->options(ConfigInputType::class)
                    ->default('toggle')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric(),
                Toggle::make('is_required')
                    ->required(),
                TextInput::make('segment_index')
                    ->numeric(),
                //                CodeEditor::make('ui_schema')
                //                    ->label('UI Schema')
                //                    ->language(Language::Json),
                Textarea::make('ui_schema')
                    ->label('UI Schema')
                    ->rows(8)
                    ->formatStateUsing(fn (mixed $state): string => self::encodeJson($state))
                    ->dehydrateStateUsing(function (?string $state, Get $get): ?array {
                        $payload = self::decodeJson($state) ?? [];
                        $inputType = $get('input_type');

                        if (is_string($inputType) && $inputType !== '') {
                            $payload['input_mode'] = $inputType;
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
