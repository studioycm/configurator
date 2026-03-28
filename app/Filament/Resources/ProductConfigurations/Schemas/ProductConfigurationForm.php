<?php

namespace App\Filament\Resources\ProductConfigurations\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use JsonException;

class ProductConfigurationForm
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
                TextInput::make('configuration_code')
                    ->required(),
                TextInput::make('name'),
                Toggle::make('is_active')
                    ->required(),
                FileUpload::make('drawing_image_path')
                    ->image()
                    ->maxSize(4096),
                Textarea::make('config_data')
                    ->rows(8)
                    ->formatStateUsing(fn (mixed $state): string => self::encodeJson($state))
                    ->dehydrateStateUsing(fn (?string $state): ?array => self::decodeJson($state))
                    ->columnSpanFull(),
                Textarea::make('resolved_state')
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
