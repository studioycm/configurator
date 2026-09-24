<?php

namespace App;

enum ConditionOperator: string
{
    case Equals = 'Equals';
    case NotEquals = 'NotEquals';
    case In = 'In';
    case NotIn = 'NotIn';
    case Contains = 'Contains';
}
