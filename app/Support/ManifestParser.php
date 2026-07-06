<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\ModuleManifest;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class ManifestParser
{
    public function parseModule(string $modulePath): ModuleManifest
    {
        $manifestPath = $modulePath.DIRECTORY_SEPARATOR.'module.json';

        if (! File::exists($manifestPath)) {
            throw new RuntimeException("Module manifest not found: {$manifestPath}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        return ModuleManifest::fromArray($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function parseApplication(string $packagePath): array
    {
        $manifestPath = $packagePath.DIRECTORY_SEPARATOR.'manifest.json';

        if (! File::exists($manifestPath)) {
            throw new RuntimeException("Application manifest not found: {$manifestPath}");
        }

        /** @var array<string, mixed> */
        return json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    }
}
