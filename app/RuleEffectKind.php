<?php

namespace App;

enum RuleEffectKind: string
{
    case AllowOptions = 'AllowOptions';
    case ExcludeOptions = 'ExcludeOptions';
    case HideOptions = 'HideOptions';
    case DisableOptions = 'DisableOptions';
    case HideAttribute = 'HideAttribute';
    case SetLabel = 'SetLabel';
    case SetDisplayValue = 'SetDisplayValue';
    case SetHint = 'SetHint';
}
