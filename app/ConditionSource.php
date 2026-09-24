<?php

namespace App;

enum ConditionSource: string
{
    case SelectionOption = 'SelectionOption';
    case SelectionCode = 'SelectionCode';
    case ProductProperty = 'ProductProperty';
    case Territory = 'Territory';
    case Application = 'Application';
}
