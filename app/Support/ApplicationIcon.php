<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\ApplicationPackage;
use Illuminate\Support\Facades\File;

final class ApplicationIcon
{
    private const HOMARR_CDN = 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons@main/svg';

    /**
     * @return list<string>
     */
    private function localIconCandidates(ApplicationPackage $package): array
    {
        $candidates = [];

        $manifestIcon = $package->manifest['icon'] ?? null;
        if (is_string($manifestIcon) && $manifestIcon !== '' && ! str_starts_with($manifestIcon, 'http')) {
            $candidates[] = $manifestIcon;
        }

        foreach (['icon.svg', 'icon.png', 'logo.svg', 'logo.png'] as $file) {
            $candidates[] = $file;
        }

        return array_values(array_unique($candidates));
    }

    public function hasLocalIcon(ApplicationPackage $package): bool
    {
        foreach ($this->localIconCandidates($package) as $file) {
            if (File::isFile($package->path.DIRECTORY_SEPARATOR.$file)) {
                return true;
            }
        }

        return false;
    }

    public function localIconFilename(ApplicationPackage $package): ?string
    {
        foreach ($this->localIconCandidates($package) as $file) {
            if (File::isFile($package->path.DIRECTORY_SEPARATOR.$file)) {
                return $file;
            }
        }

        return null;
    }

    public function url(ApplicationPackage $package): string
    {
        $manifestIcon = $package->manifest['icon'] ?? null;
        if (is_string($manifestIcon) && str_starts_with($manifestIcon, 'http')) {
            return $manifestIcon;
        }

        if ($this->hasLocalIcon($package)) {
            return route('plugins.icon', ['slug' => $package->slug]);
        }

        $aliases = (array) config('applications.icon_aliases', []);
        $cdnSlug = (string) ($aliases[$package->slug] ?? $package->slug);

        return self::HOMARR_CDN.'/'.$cdnSlug.'.svg';
    }

    public function fallbackDataUri(ApplicationPackage $package): string
    {
        $letter = mb_strtoupper(mb_substr($package->name(), 0, 1));
        $hue = abs(crc32($package->slug)) % 360;
        $bg = sprintf('hsl(%d, 42%%, 32%%)', $hue);
        $fg = '#f0f0f8';

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="%s"/><text x="32" y="32" dominant-baseline="central" text-anchor="middle" font-family="Inter,system-ui,sans-serif" font-size="28" font-weight="700" fill="%s">%s</text></svg>',
            $bg,
            $fg,
            htmlspecialchars($letter, ENT_QUOTES | ENT_XML1),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
