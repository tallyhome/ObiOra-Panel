<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('module_slug');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['module_slug', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_metadata');
    }
};
