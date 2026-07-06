<?php

declare(strict_types=1);

namespace App\Enums;

enum ModuleStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Installing = 'installing';
    case Error = 'error';
}
