<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms\Components\ToggleButtons;

class ValueTagField
{
    /** @param array<int|string,string>|Closure $options */
    public static function make(array|Closure $options): ToggleButtons
    {
        return ToggleButtons::make('values')->inline()->extraAttributes(['class' => 'catalog-tag-filters'])
            ->label(__('catalog.master_values.labels.filter_by_tags'))->multiple()->options($options)->default([]);
    }

    /** @param list<string> $vocabulary @param array<mixed> $selected @return array<int|string,string> */
    public static function options(array $vocabulary, array $selected): array
    {
        $options = array_combine($vocabulary, $vocabulary);
        foreach ($selected as $tag) {
            if (is_string($tag) && ! in_array($tag, $vocabulary, true)) {
                $options[$tag] = __('tags.unused_filter', ['tag' => $tag]);
            }
        }

        return $options;
    }
}
