<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('document_root');
            $table->string('php_version', 8)->default('8.3');
            $table->boolean('ssl_enabled')->default(false);
            $table->timestamp('ssl_expires_at')->nullable();
            $table->string('ssl_email')->nullable();
            $table->string('status')->default('pending');
            $table->string('nginx_config_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
