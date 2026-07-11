<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class DoctorSuiteAgentControlTest extends TestCase
{
    public function test_uninstall_script_route_is_public(): void
    {
        $response = $this->get(route('install.uninstall-doctor-suite'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/x-shellscript; charset=utf-8');
        $response->assertSee('OBIORA_SUITE_PURGED', false);
        $response->assertSee('OBIORA_PURGE_SLAVE', false);
    }
}
