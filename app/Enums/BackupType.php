<?php

declare(strict_types=1);

namespace App\Enums;

enum BackupType: string
{
    case Database = 'database';
    case Files = 'files';
    case Full = 'full';
}
