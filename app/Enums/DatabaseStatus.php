<?php

declare(strict_types=1);

namespace App\Enums;

enum DatabaseStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Error = 'error';
}
