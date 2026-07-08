<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Connexion SSH éphémère — jamais persistée en base.
 */
final readonly class SshConnection
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public ?string $password = null,
        public ?string $privateKey = null,
    ) {}
}
