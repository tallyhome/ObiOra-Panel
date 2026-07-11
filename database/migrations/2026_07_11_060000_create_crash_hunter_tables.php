<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_hunter_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('collector', 64);
            $table->timestamp('sampled_at');
            $table->json('payload');
            $table->timestamps();

            $table->index(['server_id', 'sampled_at']);
            $table->index(['server_id', 'collector', 'sampled_at']);
        });

        Schema::create('crash_hunter_events', function (Blueprint $table) {
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
        });

        Schema::create('crash_hunter_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('slot')->nullable();
            $table->timestamp('sampled_at');
            $table->json('payload');
            $table->timestamps();

            $table->index(['server_id', 'sampled_at']);
        });

        Schema::create('crash_hunter_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->json('triggers')->nullable();
            $table->unsignedInteger('snapshot_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'external_id']);
            $table->index(['server_id', 'started_at']);
        });

        Schema::create('crash_hunter_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('hostname')->nullable();
            $table->string('trigger_type', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->json('report_json');
            $table->string('bundle_path')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'generated_at']);
        });

        Schema::create('crash_hunter_witness', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('alive');
            $table->timestamp('received_at');
            $table->json('payload');
            $table->timestamps();

            $table->index(['server_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_hunter_witness');
        Schema::dropIfExists('crash_hunter_reports');
        Schema::dropIfExists('crash_hunter_incidents');
        Schema::dropIfExists('crash_hunter_snapshots');
        Schema::dropIfExists('crash_hunter_events');
        Schema::dropIfExists('crash_hunter_metrics');
    }
};
