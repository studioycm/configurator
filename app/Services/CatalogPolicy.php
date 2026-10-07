<?php

namespace App\Services;

class CatalogPolicy
{
    public const int MAX_PAGE_SIZE = 100;

    public const int MAX_CARDS_PER_ROW = 6;

    public const int MAX_RESULT_THRESHOLD = 24;

    public const array CARD_LAYOUTS = [
        'inline_start' => 'Inline · Start',
        'inline_center' => 'Inline · Center',
        'inline_space_between' => 'Inline · Space between',
        'above_start' => 'Label above · Start',
        'above_center' => 'Label above · Center',
        'below_start' => 'Label below · Start',
        'below_center' => 'Label below · Center',
    ];

    public const array RESULT_SETTINGS = [
        'default_page_size' => 10,
        'allow_page_size_change' => false,
        'page_size_options' => [1, 2, 10],
    ];

    /** @return array{default_page_size: int, allow_page_size_change: bool, page_size_options: list<int>, card_properties: list<string>, cards_per_row: int, max_results: int|'all', products_debounce_ms: int, card_only_differences: bool, card_show_labels: bool, card_property_layout: string, card_property_columns: int, card_padding_block: int, card_padding_inline: int} */
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
        $delay = $settings['products_debounce_ms'] ?? 0;
        $layout = $settings['card_property_layout'] ?? null;
        $propertyColumns = $settings['card_property_columns'] ?? null;
        $blockPadding = $settings['card_padding_block'] ?? null;
        $inlinePadding = $settings['card_padding_inline'] ?? null;

        return [
            'default_page_size' => $default,
            'allow_page_size_change' => ($settings['allow_page_size_change'] ?? false) === true && in_array($default, $options, true),
            'page_size_options' => $options,
            'card_properties' => $cardProperties,
            'cards_per_row' => is_int($columns) && $columns >= 1 && $columns <= self::MAX_CARDS_PER_ROW ? $columns : 4,
            'products_debounce_ms' => is_int($delay) && $delay >= 0 && $delay <= 2147483600 && $delay % 100 === 0 ? $delay : 0,
            'max_results' => is_int($maximum) && $maximum >= 1 && $maximum <= self::MAX_RESULT_THRESHOLD ? $maximum : 'all',
            'card_only_differences' => is_bool($settings['card_only_differences'] ?? null) ? $settings['card_only_differences'] : true,
            'card_show_labels' => is_bool($settings['card_show_labels'] ?? null) ? $settings['card_show_labels'] : false,
            'card_property_layout' => is_string($layout) && array_key_exists($layout, self::CARD_LAYOUTS) ? $layout : 'inline_space_between',
            'card_property_columns' => is_int($propertyColumns) && in_array($propertyColumns, [1, 2], true) ? $propertyColumns : 2,
            'card_padding_block' => is_int($blockPadding) && $blockPadding >= 0 && $blockPadding <= 16 ? $blockPadding : 4,
            'card_padding_inline' => is_int($inlinePadding) && $inlinePadding >= 0 && $inlinePadding <= 20 ? $inlinePadding : 6,
        ];
    }
}
