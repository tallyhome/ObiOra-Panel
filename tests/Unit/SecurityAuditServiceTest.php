<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DiagnosticReport;
use App\Models\Server;
use App\Services\Diagnostics\DoctorSuitePlainLanguage;
use App\Services\Security\SecurityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SecurityAuditServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_extracts_security_findings_and_builds_plan(): void
    {
        $server = Server::factory()->master()->create();

        DiagnosticReport::query()->create([
            'server_id' => $server->id,
            'score' => 55,
            'status' => 'warning',
            'report_json' => [
                'results' => [
                    [
                        'module' => 'security',
                        'findings' => [
                            [
                                'level' => 'WARNING',
                                'title' => 'Fail2ban inactif',
                                'details' => 'Service arrete',
                                'recommendation' => 'Activer fail2ban',
                            ],
                        ],
                    ],
                    [
                        'module' => 'obiora',
                        'findings' => [
                            [
                                'level' => 'WARNING',
                                'title' => 'Fail2ban inactif',
                                'details' => 'Service arrete',
                                'recommendation' => 'Activer fail2ban',
                            ],
                        ],
                    ],
                    [
                        'module' => 'cpu',
                        'findings' => [
                            ['level' => 'INFO', 'title' => 'CPU OK', 'details' => '', 'recommendation' => ''],
                        ],
                    ],
                ],
            ],
            'generated_at' => now(),
        ]);

        $service = new SecurityAuditService(new DoctorSuitePlainLanguage);
        $audit = $service->serverAudit($server->fresh());

        $this->assertTrue($audit['eligible']);
        $this->assertTrue($audit['has_report']);
        $this->assertSame(2, count($audit['findings']));
        $this->assertSame(0, $audit['critical_count']);
        $this->assertNotEmpty($audit['plan']);
        $this->assertSame('enable-fail2ban', $audit['findings'][0]['harden_action']);
    }

    public function test_master_server_is_eligible(): void
    {
        $server = Server::factory()->master()->create();
        $service = new SecurityAuditService(new DoctorSuitePlainLanguage);

        $this->assertTrue($service->isEligible($server));
    }

    public function test_remote_without_agent_not_eligible(): void
    {
        $server = Server::factory()->create(['is_master' => false, 'metadata' => []]);
        $service = new SecurityAuditService(new DoctorSuitePlainLanguage);

        $this->assertFalse($service->isEligible($server));
    }
}
