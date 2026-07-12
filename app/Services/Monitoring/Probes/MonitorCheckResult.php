<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Probes;

final class MonitorCheckResult
{
    /**
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        public readonly string $status,
        public readonly ?int $responseMs,
        public readonly array $metrics = [],
        public readonly ?string $error = null,
    ) {}

    public function isUp(): bool
    {
        return $this->status === 'up';
    }
}
