<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('type')->default('local');
            $table->string('status')->default('pending');
            $table->boolean('is_master')->default(false);
            $table->string('os_name')->nullable();
            $table->string('os_version')->nullable();
            $table->string('agent_token', 64)->nullable()->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
