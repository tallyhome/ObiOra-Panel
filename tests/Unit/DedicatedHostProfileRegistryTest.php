<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DedicatedHostProfile;
use App\Enums\ServerType;
use App\Models\Server;
use App\Support\DedicatedHostProfileRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DedicatedHostProfileRegistryTest extends TestCase
{
    public function test_definitions_cover_all_selectable_profiles(): void
    {
        foreach (DedicatedHostProfile::selectable() as $profile) {
            $this->assertArrayHasKey(
                $profile->value,
                DedicatedHostProfileRegistry::definitions(),
                "Missing definition for {$profile->value}",
            );
        }
    }

    #[DataProvider('resolveProvider')]
    public function test_resolve_from_metadata(?string $metadataProfile, ServerType $type, DedicatedHostProfile $expected): void
    {
        $server = Server::factory()->make([
            'type' => $type,
            'metadata' => $metadataProfile !== null ? ['host_profile' => $metadataProfile] : [],
        ]);

        $this->assertSame($expected, DedicatedHostProfileRegistry::resolve($server));
    }

    /** @return list<array{0: ?string, 1: ServerType, 2: DedicatedHostProfile}> */
    public static function resolveProvider(): array
    {
        return [
            ['virtualizor', ServerType::Dedicated, DedicatedHostProfile::Virtualizor],
            ['proxmox', ServerType::Dedicated, DedicatedHostProfile::Proxmox],
            [null, ServerType::Dedicated, DedicatedHostProfile::BareMetal],
            [null, ServerType::Vps, DedicatedHostProfile::BareMetal],
        ];
    }

    public function test_virtualizor_profile_includes_steal_alert_hint(): void
    {
        $def = DedicatedHostProfileRegistry::definition(DedicatedHostProfile::Virtualizor);

        $this->assertNotNull($def);
        $this->assertContains('cpu_steal_percent', $def['alert_hints']);
        $this->assertTrue($def['kvm_udev']);
    }
}
