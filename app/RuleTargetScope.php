<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum RuleTargetScope: string implements HasLabel
{
    case Attribute = 'Attribute';
    case Options = 'Options';

    public function getLabel(): string
    {
        return match ($this) {
            self::Attribute => __('configurator.rules.scopes.attribute'),
            self::Options => __('configurator.rules.scopes.values'),
        };
    }
}
