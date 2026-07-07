<?php

declare(strict_types=1);

use App\Enums\ModuleStatus;
use App\Models\PanelModule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PanelModule::query()->updateOrCreate(
            ['slug' => 'monitoring'],
            [
                'name' => 'Monitoring',
                'version' => '1.0.0',
                'status' => ModuleStatus::Enabled,
                'enabled_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        PanelModule::query()->where('slug', 'monitoring')->update([
            'status' => ModuleStatus::Disabled,
            'enabled_at' => null,
        ]);
    }
};
