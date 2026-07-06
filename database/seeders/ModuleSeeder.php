<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ModuleStatus;
use App\Models\PanelModule;
use App\Services\Core\ModuleManager;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $manager = app(ModuleManager::class);

        foreach ($manager->discover() as $manifest) {
            PanelModule::query()->updateOrCreate(
                ['slug' => $manifest->slug],
                [
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'status' => $manifest->defaultEnabled ? ModuleStatus::Enabled : ModuleStatus::Disabled,
                    'enabled_at' => $manifest->defaultEnabled ? now() : null,
                ],
            );
        }
    }
}
