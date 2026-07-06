<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\DTOs\ApplicationPackage;
use App\Support\ManifestParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class ApplicationCatalog
{
    public function __construct(
        private readonly ManifestParser $manifestParser,
    ) {}

    /**
     * @return Collection<int, ApplicationPackage>
     */
    public function all(): Collection
    {
        $path = (string) config('applications.path');

        if (! File::isDirectory($path)) {
            return collect();
        }

        return collect(File::directories($path))
            ->filter(fn (string $dir) => ! str_starts_with(basename($dir), '_'))
            ->filter(fn (string $dir) => File::exists($dir.DIRECTORY_SEPARATOR.'manifest.json'))
            ->map(function (string $dir) {
                $manifest = $this->manifestParser->parseApplication($dir);

                return new ApplicationPackage(
                    slug: (string) ($manifest['slug'] ?? basename($dir)),
                    path: $dir,
                    manifest: $manifest,
                );
            })
            ->sortBy(fn (ApplicationPackage $p) => $p->name())
            ->values();
    }

    public function find(string $slug): ?ApplicationPackage
    {
        return $this->all()->first(fn (ApplicationPackage $p) => $p->slug === $slug);
    }

    /**
     * @return array<string, string>
     */
    public function categories(): array
    {
        return (array) config('applications.categories', []);
    }
}
