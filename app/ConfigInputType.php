<?php

namespace App;

enum ConfigInputType: string
{
    case Toggle = 'toggle';
    case Select = 'select';
    case Radio = 'radio';
    case Text = 'text';
}
