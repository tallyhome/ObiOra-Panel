<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HealthApiTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'app' => 'ObiOra Panel',
                'version' => '1.6.0',
            ]);
    }
}
