<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnostic_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('diagnostic_reports', 'signature_verified')) {
                $table->boolean('signature_verified')->default(false)->after('signature');
            }
        });

        Schema::create('server_ping_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('latency_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->string('method', 16)->default('icmp');
            $table->timestamp('sampled_at')->useCurrent();
            $table->timestamps();

            $table->index(['server_id', 'sampled_at']);
        });

        Schema::create('monitoring_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 64);
            $table->string('severity', 16)->default('warning');
            $table->string('title');
            $table->text('message');
            $table->json('payload')->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'created_at']);
            $table->index(['notified', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_alerts');
        Schema::dropIfExists('server_ping_samples');
        Schema::table('diagnostic_reports', function (Blueprint $table) {
            if (Schema::hasColumn('diagnostic_reports', 'signature_verified')) {
                $table->dropColumn('signature_verified');
            }
        });
    }
};
