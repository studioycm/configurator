<?php

namespace App;

enum ConfiguratorIntentType: string
{
    case Initialize = 'Initialize';
    case SelectOption = 'SelectOption';
    case ChangeContext = 'ChangeContext';
    case Reevaluate = 'Reevaluate';
}
