<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\License;
use App\Models\Setting;
use Illuminate\Support\Carbon;

final class LicenseService
{
    public function __construct(
        private readonly AdminLicenceClient $adminLicenceClient,
        private readonly LicenseManager $licenseManager,
    ) {}

    public function getInstallationUuid(): string
    {
        $setting = Setting::query()
            ->where('group', 'installation')
            ->where('key', 'uuid')
            ->first();

        $uuid = $setting?->value['uuid'] ?? null;

        if (is_string($uuid) && $uuid !== '') {
            return $uuid;
        }

        $license = License::query()->first();

        return (string) ($license?->installation_uuid ?? '');
    }

    public function current(): ?License
    {
        $uuid = $this->getInstallationUuid();

        if ($uuid === '') {
            return null;
        }

        return License::query()->where('installation_uuid', $uuid)->first();
    }

    /**
     * @return array{success: bool, message: string, license: ?License}
     */
    public function activate(string $licenseKey): array
    {
        $licenseKey = trim($licenseKey);
        $uuid = $this->getInstallationUuid();

        if ($uuid === '') {
            return ['success' => false, 'message' => 'UUID d\'installation introuvable.', 'license' => null];
        }

        if ($licenseKey === '') {
            return ['success' => false, 'message' => 'Clé de licence requise.', 'license' => null];
        }

        if (! config('license.enabled', false)) {
            $license = $this->storeLicense($uuid, $licenseKey, 'free', 'active', null, config('license.plans.free', []));

            return [
                'success' => true,
                'message' => 'Mode développement : licence enregistrée localement (AdminLicence désactivé).',
                'license' => $license,
            ];
        }

        $remote = $this->adminLicenceClient->validate(
            $licenseKey,
            $uuid,
            gethostname() ?: 'localhost',
            (string) config('obiora.version'),
        );

        if ($remote === null) {
            if ($this->licenseManager->validate($licenseKey, $uuid)) {
                $license = $this->storeLicense($uuid, $licenseKey, 'pro', 'active', null, config('license.plans.pro', []));

                return [
                    'success' => true,
                    'message' => 'Licence activée (AdminLicence injoignable, validation locale).',
                    'license' => $license,
                ];
            }

            return [
                'success' => false,
                'message' => 'Impossible de contacter AdminLicence. Réessayez plus tard.',
                'license' => null,
            ];
        }

        if (! $remote['valid']) {
            return [
                'success' => false,
                'message' => $remote['message'] ?? 'Clé de licence invalide.',
                'license' => null,
            ];
        }

        $limits = $remote['limits'] !== []
            ? $remote['limits']
            : $this->licenseManager->getPlanLimits($remote['plan']);

        $license = $this->storeLicense(
            $uuid,
            $licenseKey,
            $remote['plan'],
            $remote['status'],
            $remote['expires_at'],
            $limits,
        );

        return [
            'success' => true,
            'message' => 'Licence activée avec succès.',
            'license' => $license,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function refresh(): array
    {
        $license = $this->current();

        if ($license === null || $license->license_key === null || $license->license_key === '') {
            return ['success' => false, 'message' => 'Aucune licence à synchroniser.'];
        }

        $result = $this->activate($license->license_key);

        return ['success' => $result['success'], 'message' => $result['message']];
    }

    /**
     * @param  array<string, mixed>  $limits
     */
    private function storeLicense(
        string $uuid,
        string $licenseKey,
        string $plan,
        string $status,
        ?string $expiresAt,
        array $limits,
    ): License {
        return License::query()->updateOrCreate(
            ['installation_uuid' => $uuid],
            [
                'license_key' => $licenseKey,
                'plan' => $plan,
                'status' => $status,
                'activated_at' => now(),
                'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : null,
                'limits' => $limits,
                'metadata' => ['last_sync' => now()->toIso8601String()],
            ],
        );
    }
}
