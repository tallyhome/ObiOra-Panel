<?php

declare(strict_types=1);

namespace App\Enums;

enum ServerStatus: string
{
    case Online = 'online';
    case Degraded = 'degraded';
    case Offline = 'offline';
    case Pending = 'pending';
    case Error = 'error';
    case Maintenance = 'maintenance';
}
