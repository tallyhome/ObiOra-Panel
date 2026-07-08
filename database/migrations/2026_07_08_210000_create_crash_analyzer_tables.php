<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_analyzer_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('collector', 64);
            $table->timestamp('sampled_at');
            $table->json('payload');
            $table->timestamps();

            $table->index(['server_id', 'sampled_at']);
            $table->index(['server_id', 'collector', 'sampled_at']);
        });

        Schema::create('crash_analyzer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->string('severity', 16)->default('warning');
            $table->string('title');
            $table->text('details')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('detected_at');
            $table->boolean('notified')->default(false);
            $table->timestamps();

            $table->index(['server_id', 'detected_at']);
            $table->index(['server_id', 'event_type']);
            $table->index(['severity', 'notified']);
        });

        Schema::create('crash_analyzer_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('hostname')->nullable();
            $table->string('trigger_type', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->json('report_json');
            $table->string('pdf_path')->nullable();
            $table->string('html_path')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_analyzer_reports');
        Schema::dropIfExists('crash_analyzer_events');
        Schema::dropIfExists('crash_analyzer_metrics');
    }
};
