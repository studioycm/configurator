<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum ConfiguratorSubtitleMode: string implements HasLabel
{
    case None = 'none';
    case Custom = 'custom';
    case Master = 'master';

    public function getLabel(): string
    {
        return __('configurator.subtitles.modes.'.$this->value);
    }

    public function resolve(?string $custom, string $masterName): string
    {
        return match ($this) {
            self::None => '',
            self::Custom => $custom ?? '',
            self::Master => $masterName,
        };
    }
}
