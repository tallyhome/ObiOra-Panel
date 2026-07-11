<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Diagnostics\DoctorSuitePlainLanguage;
use PHPUnit\Framework\TestCase;

final class DoctorSuitePlainLanguageTest extends TestCase
{
    public function test_summarize_merges_hunter_crash_analyzer_and_doctor(): void
    {
        $service = new DoctorSuitePlainLanguage;

        $summary = $service->summarize([
            'doctor' => [
                'action_findings' => [
                    [
                        'module' => 'disk',
                        'level' => 'CRITICAL',
                        'title' => 'Disque SMART dégradé',
                        'details' => 'Réallocation sectors élevés',
                        'recommendation' => 'Remplacer le disque et migrer les VM.',
                    ],
                ],
            ],
            'crash_analyzer' => [
                'summary' => ['metrics_count' => 10],
                'events' => [
                    [
                        'severity' => 'critical',
                        'event_type' => 'oom_killer',
                        'title' => 'OOM Killer',
                        'details' => 'Process mysql tué',
                    ],
                ],
            ],
            'crash_hunter' => [
                'summary' => ['incident_mode' => false],
                'incidents' => [],
                'latest_report_insights' => [
                    'verdict' => 'FREEZE I/O DISQUE',
                    'reboot_classification' => 'Hard reboot',
                    'causal_story' => 'IOWait monte puis SSH timeout.',
                    'recommendations' => [
                        [
                            'category' => 'iowait_high',
                            'title' => 'I/O wait élevé',
                            'actions' => ['Analyser iostat', 'Vérifier latence disque'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('critical', $summary['severity']);
        $this->assertSame('FREEZE I/O DISQUE', $summary['headline']);
        $this->assertNotEmpty($summary['items']);
        $this->assertSame('CrashHunter', $summary['items'][0]['source']);
    }

    public function test_normalize_recommendations_supports_crashhunter_format(): void
    {
        $service = new DoctorSuitePlainLanguage;

        $actions = $service->normalizeRecommendations([
            [
                'category' => 'disk_timeout',
                'title' => 'Timeout disque',
                'actions' => ['Vérifier SMART', 'Contacter OVH'],
            ],
        ]);

        $this->assertSame(['Vérifier SMART', 'Contacter OVH'], $actions);
    }
}
