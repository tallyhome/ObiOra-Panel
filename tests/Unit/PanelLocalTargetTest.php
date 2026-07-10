<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Support\PanelLocalTarget;
use Tests\TestCase;

final class PanelLocalTargetTest extends TestCase
{
    public function test_localhost_is_detected(): void
    {
        $this->assertTrue(PanelLocalTarget::isLocalHost('127.0.0.1'));
        $this->assertTrue(PanelLocalTarget::isLocalHost('localhost'));
        $this->assertTrue(PanelLocalTarget::isLocalHost('::1'));
        $this->assertFalse(PanelLocalTarget::isLocalHost('203.0.113.50'));
    }

    public function test_master_server_ip_is_panel_local(): void
    {
        $server = Server::factory()->master()->make([
            'ip_address' => '54.37.103.239',
        ]);

        $this->assertTrue(PanelLocalTarget::isPanelServer($server, '54.37.103.239'));
        $this->assertTrue(PanelLocalTarget::isPanelServer($server, '127.0.0.1'));
        $this->assertFalse(PanelLocalTarget::isPanelServer($server, '54.37.103.241'));
    }

    public function test_remote_slave_ip_is_not_panel_local(): void
    {
        $server = Server::factory()->make([
            'is_master' => false,
            'ip_address' => '54.37.103.241',
        ]);

        $this->assertFalse(PanelLocalTarget::isPanelServer($server, '54.37.103.239'));
    }
}
