<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metric_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->timestamp('sampled_at');
            $table->decimal('cpu_percent', 6, 2)->nullable();
            $table->decimal('cpu_steal_percent', 6, 2)->nullable();
            $table->decimal('memory_percent', 6, 2)->nullable();
            $table->decimal('swap_percent', 6, 2)->nullable();
            $table->decimal('disk_percent', 6, 2)->nullable();
            $table->decimal('load_1', 8, 2)->nullable();
            $table->decimal('load_5', 8, 2)->nullable();
            $table->decimal('load_15', 8, 2)->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metric_samples');
    }
};
