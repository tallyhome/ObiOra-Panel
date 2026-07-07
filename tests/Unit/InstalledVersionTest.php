<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\InstalledVersion;
use Tests\TestCase;

final class InstalledVersionTest extends TestCase
{
    public function test_prefers_version_file_over_git_tag(): void
    {
        $path = base_path('VERSION');
        $backup = is_readable($path) ? file_get_contents($path) : null;

        file_put_contents($path, "9.9.9\n");

        try {
            $version = (new InstalledVersion)->current();

            $this->assertSame('9.9.9', $version);
        } finally {
            if ($backup !== null) {
                file_put_contents($path, $backup);
            } else {
                @unlink($path);
            }
        }
    }
}
