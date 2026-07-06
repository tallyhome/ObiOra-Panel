<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ServerStatus;
use App\Enums\ServerType;
use App\Models\License;
use App\Models\Server;
use App\Models\ServerNode;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $installationUuid = (string) Str::uuid();

        Setting::query()->updateOrCreate(
            ['group' => 'installation', 'key' => 'uuid'],
            ['value' => ['uuid' => $installationUuid], 'is_public' => false],
        );

        Setting::query()->updateOrCreate(
            ['group' => 'general', 'key' => 'app_name'],
            ['value' => ['name' => config('obiora.name')], 'is_public' => true],
        );

        License::query()->updateOrCreate(
            ['installation_uuid' => $installationUuid],
            [
                'plan' => 'free',
                'status' => 'active',
                'activated_at' => now(),
                'limits' => config('license.plans.free'),
            ],
        );

        $server = Server::query()->updateOrCreate(
            ['is_master' => true],
            [
                'name' => config('obiora.default_server.name'),
                'hostname' => gethostname() ?: 'localhost',
                'ip_address' => '127.0.0.1',
                'type' => ServerType::Local,
                'status' => ServerStatus::Online,
                'os_name' => PHP_OS_FAMILY,
                'agent_token' => Str::random(64),
                'last_seen_at' => now(),
            ],
        );

        ServerNode::query()->updateOrCreate(
            ['server_id' => $server->id, 'is_primary' => true],
            [
                'connection_type' => 'local',
                'host' => '127.0.0.1',
                'is_active' => true,
                'last_ping_at' => now(),
            ],
        );
    }
}
