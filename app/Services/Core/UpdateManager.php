<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Support\VersionComparator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class UpdateManager
{
    public function __construct(
        private readonly VersionComparator $versionComparator,
    ) {}

    /**
     * @return array{current: string, latest: ?string, available: bool, changelog_url: ?string}
     */
    public function checkForUpdates(): array
    {
        $current = (string) config('obiora.version');
        $repository = (string) config('obiora.github.repository');

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get(config('obiora.github.api_url')."/repos/{$repository}/releases/latest");

            if (! $response->successful()) {
                return $this->unavailableResponse($current);
            }

            $latest = ltrim((string) $response->json('tag_name', ''), 'v');

            return [
                'current' => $current,
                'latest' => $latest,
                'available' => $this->versionComparator->isNewer($latest, $current),
                'changelog_url' => $response->json('html_url'),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Update check failed', ['message' => $exception->getMessage()]);

            return $this->unavailableResponse($current);
        }
    }

    /**
     * @return array{current: string, latest: ?string, available: bool, changelog_url: ?string}
     */
    private function unavailableResponse(string $current): array
    {
        return [
            'current' => $current,
            'latest' => null,
            'available' => false,
            'changelog_url' => null,
        ];
    }
}
