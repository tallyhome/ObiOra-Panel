<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Services\Diagnostics\ReportSignatureVerifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReportSignatureVerifierTest extends TestCase
{
    #[Test]
    public function it_verifies_hmac_sha256_signature_matching_python(): void
    {
        $key = random_bytes(32);
        $server = new Server([
            'metadata' => ['doctor_signing_key' => bin2hex($key)],
        ]);

        $payload = [
            'version' => '0.5.0',
            'score' => 95,
            'results' => [],
            'host' => ['hostname' => 'test'],
        ];

        $sorted = $payload;
        ksort($sorted);
        $json = json_encode($sorted, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $json !== false ? $json : '', $key);

        $signed = $payload;
        $signed['signature'] = ['algorithm' => 'HMAC-SHA256', 'value' => $signature];

        $verifier = new ReportSignatureVerifier;

        $this->assertTrue($verifier->verify($signed, $server));
    }

    #[Test]
    public function it_rejects_tampered_payload(): void
    {
        $key = random_bytes(32);
        $server = new Server([
            'metadata' => ['doctor_signing_key' => bin2hex($key)],
        ]);

        $payload = [
            'version' => '0.5.0',
            'score' => 95,
            'results' => [],
        ];
        $signed = $payload;
        $signed['signature'] = ['algorithm' => 'HMAC-SHA256', 'value' => str_repeat('a', 64)];

        $verifier = new ReportSignatureVerifier;

        $this->assertFalse($verifier->verify($signed, $server));
    }
}
