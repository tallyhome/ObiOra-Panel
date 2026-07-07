<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('schema_version', 16)->default('1.0');
            $table->string('doctor_version', 16)->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('status', 32)->default('ok');
            $table->string('hostname')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->json('report_json');
            $table->json('critical_findings')->nullable();
            $table->boolean('support_mode')->default(false);
            $table->string('signature')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_reports');
    }
};
