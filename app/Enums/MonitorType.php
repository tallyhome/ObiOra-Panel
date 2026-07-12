<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitorType: string
{
    case Https = 'https';
    case Http = 'http';
    case Ping = 'ping';
    case Port = 'port';
    case Keyword = 'keyword';
    case Dns = 'dns';

    public function label(): string
    {
        return match ($this) {
            self::Https => 'HTTPS',
            self::Http => 'HTTP',
            self::Ping => 'Ping',
            self::Port => 'Port',
            self::Keyword => 'Keyword',
            self::Dns => 'DNS',
        };
    }

    /** @return list<self> */
    public static function choices(): array
    {
        return self::cases();
    }
}
