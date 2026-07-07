<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Support\InstalledVersion;
use App\Support\VersionComparator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class UpdateManager
{
    public function __construct(
        private readonly VersionComparator $versionComparator,
        private readonly InstalledVersion $installedVersion,
    ) {}

    /**
     * @return array{
     *     current: string,
     *     latest: ?string,
     *     available: bool,
     *     changelog_url: ?string,
     *     commits_behind: ?int,
     *     error: ?string
     * }
     */
    public function checkForUpdates(bool $fresh = false): array
    {
        $current = $this->installedVersion->current();
        $commitsBehind = $this->installedVersion->commitsBehindMain();

        $cacheKey = 'obiora:update_check:'.md5((string) config('obiora.github.repository'));

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        /** @var array{latest: ?string, changelog_url: ?string, error: ?string} $remote */
        $remote = Cache::remember($cacheKey, now()->addMinutes(10), fn () => $this->fetchLatestRelease());

        $latest = $remote['latest'];
        $availableByVersion = $latest !== null && $this->versionComparator->isNewer($latest, $current);
        $availableByGit = ($commitsBehind ?? 0) > 0;
        $available = $availableByVersion || $availableByGit;

        return [
            'current' => $current,
            'latest' => $latest,
            'available' => $available,
            'changelog_url' => $remote['changelog_url'],
            'commits_behind' => $commitsBehind,
            'error' => $remote['error'],
        ];
    }

    /**
     * @return array{latest: ?string, changelog_url: ?string, error: ?string}
     */
    private function fetchLatestRelease(): array
    {
        $repository = (string) config('obiora.github.repository');
        $current = $this->installedVersion->current();
        $baseUrl = rtrim((string) config('obiora.github.api_url'), '/');

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'ObiOra-Panel/'.$current,
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->get("{$baseUrl}/repos/{$repository}/releases/latest");

            if ($response->successful()) {
                $latest = ltrim((string) $response->json('tag_name', ''), 'v');

                if ($latest !== '') {
                    return [
                        'latest' => $latest,
                        'changelog_url' => $response->json('html_url'),
                        'error' => null,
                    ];
                }
            }

            return $this->fetchLatestFromReleasesList($repository, $baseUrl, $response->status());
        } catch (\Throwable $exception) {
            Log::warning('Update check failed', ['message' => $exception->getMessage()]);

            return [
                'latest' => null,
                'changelog_url' => null,
                'error' => 'Impossible de contacter GitHub : '.$exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{latest: ?string, changelog_url: ?string, error: ?string}
     */
    private function fetchLatestFromReleasesList(string $repository, string $baseUrl, int $previousStatus): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'ObiOra-Panel/'.$this->installedVersion->current(),
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->get("{$baseUrl}/repos/{$repository}/releases", [
                    'per_page' => 20,
                ]);

            if (! $response->successful()) {
                return [
                    'latest' => null,
                    'changelog_url' => null,
                    'error' => "GitHub API indisponible (HTTP {$previousStatus}).",
                ];
            }

            /** @var list<array<string, mixed>> $releases */
            $releases = $response->json() ?? [];
            $best = null;

            foreach ($releases as $release) {
                if (($release['draft'] ?? false) || ($release['prerelease'] ?? false)) {
                    continue;
                }

                $tag = ltrim((string) ($release['tag_name'] ?? ''), 'v');

                if ($tag === '') {
                    continue;
                }

                if ($best === null || $this->versionComparator->isNewer($tag, $best['version'])) {
                    $best = [
                        'version' => $tag,
                        'url' => $release['html_url'] ?? null,
                    ];
                }
            }

            if ($best === null) {
                return [
                    'latest' => null,
                    'changelog_url' => null,
                    'error' => 'Aucune release GitHub trouvée.',
                ];
            }

            return [
                'latest' => $best['version'],
                'changelog_url' => is_string($best['url']) ? $best['url'] : null,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'latest' => null,
                'changelog_url' => null,
                'error' => 'Impossible de lister les releases GitHub.',
            ];
        }
    }
}
