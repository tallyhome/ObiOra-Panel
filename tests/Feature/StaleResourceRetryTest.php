<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DatabaseStatus;
use App\Enums\WebsiteStatus;
use App\Models\ManagedDatabase;
use App\Models\Server;
use App\Models\Website;
use App\Services\Database\DatabaseManager;
use App\Services\Web\WebsiteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StaleResourceRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_create_retries_after_failed_attempt(): void
    {
        $this->seed();

        $server = Server::query()->where('is_master', true)->firstOrFail();

        Website::query()->create([
            'server_id' => $server->id,
            'domain' => 'test.example.com',
            'document_root' => '',
            'php_version' => '8.3',
            'status' => WebsiteStatus::Error,
        ]);

        $manager = $this->app->make(WebsiteManager::class);

        try {
            $manager->create(['domain' => 'test.example.com'], $server);
        } catch (\InvalidArgumentException) {
            // Provisionnement peut échouer en environnement de test
        }

        $this->assertFalse(
            Website::query()
                ->where('server_id', $server->id)
                ->where('domain', 'test.example.com')
                ->where('status', WebsiteStatus::Error)
                ->exists()
        );
    }

    public function test_database_create_retries_after_failed_attempt(): void
    {
        $this->seed();

        $server = Server::query()->where('is_master', true)->firstOrFail();

        ManagedDatabase::query()->create([
            'server_id' => $server->id,
            'name' => 'retry_db',
            'username' => 'u',
            'password' => 'x',
            'host' => 'localhost',
            'status' => DatabaseStatus::Error,
        ]);

        $manager = $this->app->make(DatabaseManager::class);

        try {
            $manager->create(['name' => 'retry_db'], $server);
        } catch (\InvalidArgumentException) {
            //
        }

        $this->assertFalse(
            ManagedDatabase::query()
                ->where('server_id', $server->id)
                ->where('name', 'retry_db')
                ->where('status', DatabaseStatus::Error)
                ->exists()
        );
    }
}
