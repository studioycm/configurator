<?php

namespace App\Filament\Resources\ConfigProfiles\Schemas;

use App\ConfigProfileScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use JsonException;

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
                Textarea::make('runtime_context_schema')
                    ->label('Runtime Context Schema')
                    ->rows(10)
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
