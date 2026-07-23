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
        $gitTag = $this->installedVersion->latestGitTag();

        // Max semver entre release API et tags git locaux (évite latest stale v2.x).
        if ($gitTag !== null && ($latest === null || $this->versionComparator->isNewer($gitTag, $latest))) {
            $latest = $gitTag;
            $remote['changelog_url'] ??= 'https://github.com/'.config('obiora.github.repository').'/releases/tag/v'.$gitTag;
        }

        $commitsBehind = $commitsBehind ?? 0;
        $availableByVersion = $latest !== null && $this->versionComparator->isNewer($latest, $current);

        // Commits behind sur main : OK seulement si la cible n'est pas un downgrade.
        $availableByGit = $commitsBehind > 0
            && ($latest === null || ! $this->versionComparator->isNewer($current, $latest));

        return [
            'current' => $current,
            'latest' => $latest,
            'available' => $availableByVersion || $availableByGit,
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
                    $release = [
                        'latest' => $latest,
                        'changelog_url' => $response->json('html_url'),
                        'error' => null,
                    ];

                    return $this->mergeWithTags($repository, $baseUrl, $release);
                }
            }

            $fallback = $this->fetchLatestFromReleasesList($repository, $baseUrl, $response->status());

            return $this->mergeWithTags($repository, $baseUrl, $fallback);
        } catch (\Throwable $exception) {
            Log::warning('Update check failed', ['message' => $exception->getMessage()]);

            $gitFallback = $this->gitFallbackRelease($repository, $exception->getMessage());
            if ($gitFallback !== null) {
                return $gitFallback;
            }

            return [
                'latest' => null,
                'changelog_url' => null,
                'error' => 'Impossible de contacter GitHub : '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Dernier recours quand l'API REST GitHub est indisponible ou rate-limitée
     * (ex. HTTP 403) : on lit directement les tags git déjà présents/récupérés
     * localement, puisque c'est de toute façon le mécanisme réel utilisé pour
     * appliquer la mise à jour (`git reset --hard origin/main`).
     *
     * @return ?array{latest: ?string, changelog_url: ?string, error: ?string}
     */
    private function gitFallbackRelease(string $repository, string $reason): ?array
    {
        $tag = $this->installedVersion->latestGitTag();

        if ($tag === null) {
            return null;
        }

        Log::info('Update check: fallback sur les tags git locaux (API GitHub indisponible)', [
            'reason' => $reason,
            'tag' => $tag,
        ]);

        return [
            'latest' => $tag,
            'changelog_url' => 'https://github.com/'.$repository.'/releases/tag/v'.$tag,
            'error' => null,
        ];
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
                $gitFallback = $this->gitFallbackRelease($repository, "HTTP {$previousStatus}");
                if ($gitFallback !== null) {
                    return $gitFallback;
                }

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
            $gitFallback = $this->gitFallbackRelease($repository, $exception->getMessage());
            if ($gitFallback !== null) {
                return $gitFallback;
            }

            return [
                'latest' => null,
                'changelog_url' => null,
                'error' => 'Impossible de lister les releases GitHub.',
            ];
        }
    }

    /**
     * @param  array{latest: ?string, changelog_url: ?string, error: ?string}  $release
     * @return array{latest: ?string, changelog_url: ?string, error: ?string}
     */
    private function mergeWithTags(string $repository, string $baseUrl, array $release): array
    {
        $tagLatest = $this->fetchLatestTag($repository, $baseUrl);

        if ($tagLatest === null) {
            return $release;
        }

        $currentLatest = $release['latest'];

        if ($currentLatest === null || $this->versionComparator->isNewer($tagLatest, $currentLatest)) {
            $release['latest'] = $tagLatest;

            if ($release['changelog_url'] === null) {
                $release['changelog_url'] = 'https://github.com/'.$repository.'/releases/tag/v'.$tagLatest;
            }
        }

        if ($release['error'] !== null && $release['latest'] !== null) {
            $release['error'] = null;
        }

        return $release;
    }

    private function fetchLatestTag(string $repository, string $baseUrl): ?string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'ObiOra-Panel/'.$this->installedVersion->current(),
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->get("{$baseUrl}/repos/{$repository}/tags", [
                    'per_page' => 100,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $best = null;

            /** @var list<array<string, mixed>> $tags */
            $tags = $response->json() ?? [];

            foreach ($tags as $tag) {
                $name = ltrim((string) ($tag['name'] ?? ''), 'v');

                if ($name === '' || ! preg_match('/^\d+\.\d+/', $name)) {
                    continue;
                }

                if ($best === null || $this->versionComparator->isNewer($name, $best)) {
                    $best = $name;
                }
            }

            return $best;
        } catch (\Throwable) {
            return null;
        }
    }
}
