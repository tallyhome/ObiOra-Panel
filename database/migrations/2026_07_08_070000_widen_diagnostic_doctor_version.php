<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('diagnostic_reports') || ! Schema::hasColumn('diagnostic_reports', 'doctor_version')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE diagnostic_reports MODIFY doctor_version VARCHAR(64) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE diagnostic_reports ALTER COLUMN doctor_version TYPE VARCHAR(64)');
        }
        // SQLite : pas de limite stricte sur VARCHAR — bootstrap-1.0 tient dans 16 car.
    }

    public function down(): void
    {
        if (! Schema::hasTable('diagnostic_reports')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE diagnostic_reports MODIFY doctor_version VARCHAR(16) NULL');
        }
    }
};
