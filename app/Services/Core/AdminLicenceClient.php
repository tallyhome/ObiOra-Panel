<?php

declare(strict_types=1);

namespace App\Services\Core;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AdminLicenceClient
{
    /**
     * @return array{valid: bool, plan: string, status: string, expires_at: ?string, limits: array<string, mixed>, message: ?string}|null
     */
    public function validate(string $licenseKey, string $installationUuid, string $hostname, string $version): ?array
    {
        $baseUrl = (string) config('license.admin_licence_url');

        if ($baseUrl === '') {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post(rtrim($baseUrl, '/').'/api/v1/licenses/validate', [
                    'license_key' => $licenseKey,
                    'installation_uuid' => $installationUuid,
                    'hostname' => $hostname,
                    'panel_version' => $version,
                ]);

            if (! $response->successful()) {
                Log::warning('AdminLicence validation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'valid' => false,
                    'plan' => 'free',
                    'status' => 'invalid',
                    'expires_at' => null,
                    'limits' => config('license.plans.free', []),
                    'message' => $response->json('message') ?? 'Licence refusée par AdminLicence.',
                ];
            }

            /** @var array<string, mixed> $data */
            $data = $response->json('data', $response->json());

            return [
                'valid' => (bool) ($data['valid'] ?? false),
                'plan' => (string) ($data['plan'] ?? 'free'),
                'status' => (string) ($data['status'] ?? 'inactive'),
                'expires_at' => isset($data['expires_at']) ? (string) $data['expires_at'] : null,
                'limits' => is_array($data['limits'] ?? null) ? $data['limits'] : [],
                'message' => isset($data['message']) ? (string) $data['message'] : null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('AdminLicence unreachable', ['message' => $exception->getMessage()]);

            return null;
        }
    }
}
