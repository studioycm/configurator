<?php

namespace App\Filament\Resources\ConfigOptions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use JsonException;

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
                Textarea::make('ui_meta')
                    ->label('UI Meta')
                    ->rows(8)
                    ->formatStateUsing(fn (mixed $state): string => self::encodeJson($state))
                    ->dehydrateStateUsing(fn (?string $state): ?array => self::decodeJson($state))
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
