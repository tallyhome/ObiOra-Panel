<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertPolicyOperator: string
{
    case Gt = 'gt';
    case Lt = 'lt';
    case Gte = 'gte';
    case Lte = 'lte';
    case Eq = 'eq';

    public function label(): string
    {
        return match ($this) {
            self::Gt => '>',
            self::Lt => '<',
            self::Gte => '≥',
            self::Lte => '≤',
            self::Eq => '=',
        };
    }
}
