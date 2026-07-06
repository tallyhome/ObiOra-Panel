<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ProcessResult
{
    public function __construct(
        public bool $successful,
        public int $exitCode,
        public string $output,
        public string $errorOutput = '',
        public float $duration = 0.0,
    ) {}

    public static function success(string $output = '', int $exitCode = 0, float $duration = 0.0): self
    {
        return new self(true, $exitCode, $output, '', $duration);
    }

    public static function failure(string $errorOutput, int $exitCode = 1, string $output = '', float $duration = 0.0): self
    {
        return new self(false, $exitCode, $output, $errorOutput, $duration);
    }
}
