<?php

namespace App\Enums;

enum FindingSeverity: string
{
    case INFO = 'info';
    case ATTENTION = 'attention';
    case HIGH = 'high';
}
