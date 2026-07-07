<?php

declare(strict_types=1);

use App\Enums\ModuleStatus;
use App\Models\PanelModule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'ssl', 'firewall', 'users', 'nginx', 'redis', 'apache', 'ftp', 'dns',
            'applications', 'virtualizor', 'cluster',
        ] as $slug) {
            PanelModule::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucfirst($slug),
                    'version' => '1.0.0',
                    'status' => ModuleStatus::Enabled,
                    'enabled_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        // Ne pas désactiver automatiquement en rollback.
    }
};
