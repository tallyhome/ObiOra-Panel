<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PanelDatabase;
use App\Support\PanelInfrastructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class PanelBootAvailabilityTest extends TestCase
{
    public function test_login_page_returns_service_unavailable_when_redis_unreachable(): void
    {
        $originalCache = (string) config('cache.default');
        $originalRedis = config('database.redis.default');

        try {
            Config::set('cache.default', 'redis');
            Config::set('database.redis.default', [
                'url' => null,
                'host' => '127.0.0.1',
                'username' => null,
                'password' => null,
                'port' => '59998',
                'database' => '0',
                'read_timeout' => 1,
                'timeout' => 1,
            ]);

            PanelInfrastructure::resetCache();
            PanelDatabase::resetCache();

            $this->get(route('login'))
                ->assertStatus(503)
                ->assertSee('démarrage en cours', false);
        } finally {
            Config::set('cache.default', $originalCache);
            Config::set('database.redis.default', $originalRedis);
            PanelInfrastructure::resetCache();
            PanelDatabase::resetCache();
        }
    }
}
