<?php

namespace App\Services;

class CatalogPolicy
{
    public const int MAX_PAGE_SIZE = 100;

    public const int MAX_CARDS_PER_ROW = 6;

    public const int MAX_RESULT_THRESHOLD = 24;

    public const array RESULT_SETTINGS = [
        'default_page_size' => 10,
        'allow_page_size_change' => false,
        'page_size_options' => [1, 2, 10],
    ];

    /** @return array{default_page_size: int, allow_page_size_change: bool, page_size_options: list<int>, card_properties: list<string>, cards_per_row: int, max_results: int|'all'} */
    public static function resultSettings(?array $settings): array
    {
        $settings ??= self::RESULT_SETTINGS;
        $default = $settings['default_page_size'] ?? 10;
        $default = is_int($default) && $default >= 1 && $default <= self::MAX_PAGE_SIZE ? $default : 10;
        $options = is_array($settings['page_size_options'] ?? null) ? array_values(array_unique(array_filter($settings['page_size_options'], fn (mixed $size): bool => is_int($size) && $size >= 1 && $size <= self::MAX_PAGE_SIZE))) : [1, 2, 10];
        $properties = CatalogImportParser::propertyKeys();
        $cardProperties = is_array($settings['card_properties'] ?? null)
            ? array_values(array_unique(array_filter($settings['card_properties'], fn (mixed $key): bool => is_string($key) && in_array($key, $properties, true))))
            : [];
        $columns = $settings['cards_per_row'] ?? 4;
        $maximum = $settings['max_results'] ?? 'all';

        return [
            'default_page_size' => $default,
            'allow_page_size_change' => ($settings['allow_page_size_change'] ?? false) === true && in_array($default, $options, true),
            'page_size_options' => $options,
            'card_properties' => $cardProperties,
            'cards_per_row' => is_int($columns) && $columns >= 1 && $columns <= self::MAX_CARDS_PER_ROW ? $columns : 4,
            'max_results' => is_int($maximum) && $maximum >= 1 && $maximum <= self::MAX_RESULT_THRESHOLD ? $maximum : 'all',
        ];
    }
}
