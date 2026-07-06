<?php

declare(strict_types=1);

namespace App\Enums;

enum ServerType: string
{
    case Local = 'local';
    case Vps = 'vps';
    case Dedicated = 'dedicated';
    case Docker = 'docker';
    case Cluster = 'cluster';
}
