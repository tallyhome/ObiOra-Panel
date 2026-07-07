<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Process;

final class InstalledVersion
{
    public function current(): string
    {
        $fromFile = $this->fromVersionFile();

        if ($fromFile !== null) {
            return $fromFile;
        }

        $fromGit = $this->fromGitTag();

        if ($fromGit !== null) {
            return $fromGit;
        }

        return ltrim((string) config('obiora.version', '0.0.0'), 'v');
    }

    /**
     * Nombre de commits sur origin/main non présents en local (0 = à jour).
     */
    public function commitsBehindMain(): ?int
    {
        if (! is_dir(base_path('.git'))) {
            return null;
        }

        $root = base_path();

        Process::path($root)->run('git fetch origin main --quiet 2>/dev/null');

        $result = Process::path($root)->run('git rev-list --count HEAD..origin/main 2>/dev/null');

        if (! $result->successful()) {
            return null;
        }

        $count = (int) trim($result->output());

        return $count >= 0 ? $count : null;
    }

    /**
     * Dernier tag semver connu depuis le dépôt git local (après fetch).
     * Ne dépend pas de l'API REST GitHub, donc insensible à son rate-limit
     * (60 requêtes/heure par IP en non-authentifié — souvent épuisé sur des
     * IP de VPS partagées, ce qui déclenche des erreurs "HTTP 403").
     */
    public function latestGitTag(): ?string
    {
        if (! is_dir(base_path('.git'))) {
            return null;
        }

        $root = base_path();

        Process::path($root)->timeout(20)->run('git fetch origin --tags --quiet 2>/dev/null');

        $result = Process::path($root)->timeout(10)->run('git tag -l --sort=-v:refname 2>/dev/null');

        if (! $result->successful()) {
            return null;
        }

        foreach (preg_split('/\r?\n/', trim($result->output())) ?: [] as $line) {
            $tag = ltrim(trim($line), 'v');

            if ($tag !== '' && preg_match('/^\d+\.\d+/', $tag)) {
                return $tag;
            }
        }

        return null;
    }

    private function fromGitTag(): ?string
    {
        if (! is_dir(base_path('.git'))) {
            return null;
        }

        Process::path(base_path())->run('git fetch --tags --quiet 2>/dev/null');

        $result = Process::path(base_path())->run('git describe --tags --abbrev=0 2>/dev/null');

        if (! $result->successful()) {
            return null;
        }

        $tag = ltrim(trim($result->output()), 'v');

        return $tag !== '' ? $tag : null;
    }

    private function fromVersionFile(): ?string
    {
        $path = base_path('VERSION');

        if (! is_readable($path)) {
            return null;
        }

        $version = ltrim(trim((string) file_get_contents($path)), 'v');

        return $version !== '' ? $version : null;
    }
}
