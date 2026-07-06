<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationStatus: string
{
    case Installing = 'installing';
    case Installed = 'installed';
    case Error = 'error';
    case Removing = 'removing';
}
