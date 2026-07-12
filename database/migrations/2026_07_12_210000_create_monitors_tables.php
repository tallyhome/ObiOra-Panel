<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16);
            $table->string('target');
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('keyword')->nullable();
            $table->boolean('keyword_present')->default(true);
            $table->unsignedInteger('interval_seconds')->default(300);
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('last_status', 16)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('last_response_ms')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'last_checked_at']);
        });

        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('response_ms')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['monitor_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_checks');
        Schema::dropIfExists('monitors');
    }
};
