<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Garantit que les apps Docker avec port hôte remappé passent aussi le port interne.
 */
final class MarketplaceDockerPortsTest extends TestCase
{
    /** @var array<string, int> slug => port interne attendu */
    private const REMAPPED_INTERNAL = [
        'jellyfin' => 8096,
        'organizr' => 80,
        'rutorrent' => 80,
        'librespeed' => 80,
        'sickchill' => 8081,
        'sickgear' => 8081,
        'subsonic' => 4040,
        'calibrecs' => 8080,
        'sonarrold' => 8989,
        'sabnzbd' => 8080,
        'calibre' => 8080,
        'rapidleech' => 80,
    ];

    public function test_remapped_docker_installs_pass_internal_port(): void
    {
        foreach (self::REMAPPED_INTERNAL as $slug => $internal) {
            $path = base_path("packages/{$slug}/install.sh");
            $this->assertFileExists($path, "install.sh manquant pour {$slug}");

            $script = (string) file_get_contents($path);
            $this->assertMatchesRegularExpression(
                '/obiora_docker_install\s+"'.preg_quote($slug, '/').'"\s+"[^"]+"\s+\d+\s+'.$internal.'\b/',
                $script,
                "{$slug} doit mapper vers le port interne {$internal}",
            );
        }
    }

    public function test_legacy_miners_and_dead_apps_are_not_installable(): void
    {
        foreach (['xmrig', 'xmr-stak', 'xmr-stak-cpu', 'couchpotato', 'headphones', 'plexpy', 'netronome'] as $slug) {
            $manifest = json_decode((string) file_get_contents(base_path("packages/{$slug}/manifest.json")), true);
            $this->assertIsArray($manifest);
            $this->assertFalse(
                $manifest['installable'] ?? true,
                "{$slug} doit être installable:false",
            );
        }
    }

    public function test_apply_marketplace_fixes_documents_internal_ports(): void
    {
        $tool = (string) file_get_contents(base_path('tools/apply-marketplace-fixes.php'));
        $this->assertStringContainsString("'jellyfin' => 8096", $tool);
        $this->assertStringContainsString("'organizr' => 80", $tool);
        $this->assertStringContainsString("'rutorrent' => 80", $tool);
    }
}
