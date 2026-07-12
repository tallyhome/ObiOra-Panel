<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PanelUpdateIntegrity;
use Tests\TestCase;

final class PanelUpdateIntegrityTest extends TestCase
{
    public function test_all_critical_update_files_exist_in_repository(): void
    {
        $integrity = new PanelUpdateIntegrity;
        $result = $integrity->verify(base_path());

        $this->assertTrue(
            $result['ok'],
            'Fichiers MAJ manquants : '.implode(', ', $result['missing']),
        );
    }

    public function test_update_scripts_are_executable_on_linux(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('Vérification +x uniquement sur Linux.');
        }

        $integrity = new PanelUpdateIntegrity;
        $result = $integrity->verify(base_path());

        $nonExecutable = array_filter(
            $result['warnings'],
            static fn (string $w): bool => str_contains($w, 'n\'est pas exécutable'),
        );

        $this->assertSame([], array_values($nonExecutable));
    }

    public function test_restore_paths_only_include_install_scripts(): void
    {
        $paths = array_filter(
            PanelUpdateIntegrity::CRITICAL_RELATIVE_PATHS,
            static fn (string $path): bool => str_starts_with($path, 'install/'),
        );

        $this->assertContains('install/update-panel.sh', $paths);
        $this->assertContains('install/lib/update-recover.sh', $paths);
    }

    public function test_update_panel_script_applies_migrations_after_composer(): void
    {
        $script = file_get_contents(base_path('install/update-panel.sh'));
        $this->assertNotFalse($script);

        $composerPos = strpos($script, 'composer install --no-dev --optimize-autoloader --no-interaction');
        $migratePos = strpos($script, 'php artisan migrate --force');

        $this->assertNotFalse($composerPos, 'composer install doit être présent dans update-panel.sh');
        $this->assertNotFalse($migratePos, 'migrate --force doit être présent dans update-panel.sh');
        $this->assertGreaterThan($composerPos, $migratePos, 'Les migrations doivent s\'exécuter après composer install');
    }

    public function test_update_recover_script_applies_pending_migrations(): void
    {
        $script = file_get_contents(base_path('install/lib/update-recover.sh'));
        $this->assertNotFalse($script);
        $this->assertStringContainsString('migrate --force', $script);
        $this->assertStringContainsString('obiora:post-deploy', $script);
        $this->assertStringContainsString('optimize:clear', $script);
        $this->assertStringContainsString('ensure_agent_executables', $script);
    }

    public function test_post_deploy_command_clears_caches_and_agent_scripts(): void
    {
        $command = file_get_contents(base_path('app/Console/Commands/PostDeployCommand.php'));
        $this->assertNotFalse($command);
        $this->assertStringContainsString('optimize:clear', $command);
        $this->assertStringContainsString('AgentScripts::ensureExecutable', $command);
    }

    public function test_monitor_agent_scripts_listed_in_update_integrity(): void
    {
        $this->assertContains(
            'agent/scripts/monitor-agent-install.sh',
            PanelUpdateIntegrity::EXECUTABLE_SCRIPTS,
        );
    }
}
