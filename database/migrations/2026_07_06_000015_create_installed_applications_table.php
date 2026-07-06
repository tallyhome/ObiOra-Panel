<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installed_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('version')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->default('installing');
            $table->timestamp('installed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_applications');
    }
};
