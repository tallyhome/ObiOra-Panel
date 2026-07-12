<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('slack_webhook')->nullable();
            $table->string('discord_webhook')->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->string('webhook_url')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('alert_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('metric', 64);
            $table->string('operator', 8)->default('gt');
            $table->decimal('value', 12, 4);
            $table->string('value_unit', 16)->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedInteger('repeat_minutes')->default(60);
            $table->string('apply_to', 16)->default('all');
            $table->json('apply_target_ids')->nullable();
            $table->json('notify_contact_ids')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('monitoring_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 16);
            $table->unsignedBigInteger('resource_id');
            $table->string('resource_name');
            $table->string('trigger');
            $table->text('message');
            $table->foreignId('alert_policy_id')->nullable()->constrained('alert_policies')->nullOnDelete();
            $table->timestamp('went_down_at');
            $table->timestamp('recovered_at')->nullable();
            $table->string('status', 16)->default('open');
            $table->json('metadata')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'went_down_at']);
            $table->index(['resource_type', 'resource_id', 'status']);
        });

        Schema::create('alert_policy_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_policy_id')->constrained('alert_policies')->cascadeOnDelete();
            $table->string('resource_type', 16);
            $table->unsignedBigInteger('resource_id');
            $table->timestamp('condition_met_since')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['alert_policy_id', 'resource_type', 'resource_id'], 'alert_policy_states_unique');
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitoring_incident_id')->nullable()->constrained('monitoring_incidents')->nullOnDelete();
            $table->foreignId('alert_contact_id')->nullable()->constrained('alert_contacts')->nullOnDelete();
            $table->string('channel', 32);
            $table->string('status', 16);
            $table->text('response')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('alert_policy_states');
        Schema::dropIfExists('monitoring_incidents');
        Schema::dropIfExists('alert_policies');
        Schema::dropIfExists('alert_contacts');
    }
};
