<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('update_history', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
            $table->string('progress_message')->nullable()->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('update_history', function (Blueprint $table) {
            $table->dropColumn(['progress', 'progress_message']);
        });
    }
};
