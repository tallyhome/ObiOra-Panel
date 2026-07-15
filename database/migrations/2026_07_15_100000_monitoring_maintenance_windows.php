<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 16)->default('all');
            $table->json('resource_ids')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
            $table->index(['resource_type', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');
    }
};
