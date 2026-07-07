<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\LicenseValidatorInterface;

final class LicenseManager implements LicenseValidatorInterface
{
    public function __construct(
        private readonly AdminLicenceClient $adminLicenceClient,
    ) {}

    public function validate(string $licenseKey, string $installationUuid): bool
    {
        if (! config('license.enabled', false)) {
            return true;
        }

        if ($licenseKey === '' || $installationUuid === '') {
            return false;
        }

        $remote = $this->adminLicenceClient->validate(
            $licenseKey,
            $installationUuid,
            gethostname() ?: 'localhost',
            (string) config('obiora.version'),
        );

        if ($remote !== null) {
            return $remote['valid'];
        }

        return ! empty($licenseKey) && ! empty($installationUuid);
    }

    public function getPlanLimits(string $plan): array
    {
        /** @var array<string, array<string, mixed>> $plans */
        $plans = config('license.plans', []);

        return $plans[$plan] ?? $plans['free'] ?? [];
    }

    public function isFeatureAllowed(string $feature): bool
    {
        if (! config('license.enabled', false)) {
            return true;
        }

        return true;
    }
}
