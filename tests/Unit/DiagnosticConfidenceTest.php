<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CrashAnalyzerTriggerLabels;
use App\Support\DiagnosticConfidence;
use PHPUnit\Framework\TestCase;

final class DiagnosticConfidenceTest extends TestCase
{
    public function test_formats_fraction_as_percent(): void
    {
        $result = DiagnosticConfidence::format(0.87);

        $this->assertSame(87, $result['percent']);
        $this->assertSame('87 % de confiance', $result['label']);
        $this->assertSame('high', $result['level']);
    }

    public function test_trigger_labels_include_rcu_stall(): void
    {
        $this->assertStringContainsString('RCU', CrashAnalyzerTriggerLabels::label('rcu_stall'));
        $this->assertNotEmpty(CrashAnalyzerTriggerLabels::hints('rcu_stall'));
    }
}
