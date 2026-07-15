<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Core\MasterServerSync;
use App\Models\License;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $sync = app(MasterServerSync::class);
        $server = $sync->ensure();

        $installationUuid = Setting::query()
            ->where('group', 'installation')
            ->where('key', 'uuid')
            ->value('value');

        $uuid = is_array($installationUuid) ? ($installationUuid['uuid'] ?? null) : null;

        if (is_string($uuid) && $uuid !== '') {
            License::query()->updateOrCreate(
                ['installation_uuid' => $uuid],
                [
                    'plan' => 'free',
                    'status' => 'active',
                    'activated_at' => now(),
                    'limits' => config('license.plans.free'),
                ],
            );
        }

        Setting::query()->updateOrCreate(
            ['group' => 'general', 'key' => 'app_name'],
            ['value' => ['name' => config('obiora.name')], 'is_public' => true],
        );

        unset($server);
    }
}
