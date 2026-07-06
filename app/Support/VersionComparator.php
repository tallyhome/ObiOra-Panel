<?php

declare(strict_types=1);

namespace App\Support;

final class VersionComparator
{
    public function isNewer(string $remote, string $current): bool
    {
        return version_compare(ltrim($remote, 'v'), ltrim($current, 'v'), '>');
    }

    public function satisfies(string $version, string $constraint): bool
    {
        return version_compare(ltrim($version, 'v'), ltrim($constraint, 'v'), '>=');
    }
}
