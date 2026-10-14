<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum ConditionOperator: string implements HasLabel
{
    case Equals = 'Equals';
    case NotEquals = 'NotEquals';
    case In = 'In';
    case NotIn = 'NotIn';
    case Contains = 'Contains';

    public function getLabel(): string
    {
        return match ($this) {
            self::Equals => __('configurator.rules.operators.Equals'),
            self::NotEquals => __('configurator.rules.operators.NotEquals'),
            self::In => __('configurator.rules.operators.In'),
            self::NotIn => __('configurator.rules.operators.NotIn'),
            self::Contains => __('configurator.rules.operators.Contains'),
        };
    }
}
