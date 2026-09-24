<?php

namespace App\Services;

class CatalogPolicy
{
    public const int MAX_PAGE_SIZE = 100;

    public const array RESULT_SETTINGS = [
        'default_page_size' => 10,
        'allow_page_size_change' => false,
        'page_size_options' => [1, 2, 10],
    ];

    /** @return array{default_page_size: int, allow_page_size_change: bool, page_size_options: list<int>} */
    public static function resultSettings(?array $settings): array
    {
        $settings ??= self::RESULT_SETTINGS;
        $default = $settings['default_page_size'] ?? 10;
        $default = is_int($default) && $default >= 1 && $default <= self::MAX_PAGE_SIZE ? $default : 10;
        $options = is_array($settings['page_size_options'] ?? null) ? array_values(array_unique(array_filter($settings['page_size_options'], fn (mixed $size): bool => is_int($size) && $size >= 1 && $size <= self::MAX_PAGE_SIZE))) : [1, 2, 10];

        return ['default_page_size' => $default, 'allow_page_size_change' => ($settings['allow_page_size_change'] ?? false) === true && in_array($default, $options, true), 'page_size_options' => $options];
    }
}
