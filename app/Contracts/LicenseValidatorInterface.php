<?php

declare(strict_types=1);

namespace App\Contracts;

interface LicenseValidatorInterface
{
    public function validate(string $licenseKey, string $installationUuid): bool;

    public function getPlanLimits(string $plan): array;

    public function isFeatureAllowed(string $feature): bool;
}
