<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->uuid('track_token')->nullable()->unique()->after('last_response_ms');
        });

        foreach (DB::table('monitors')->whereNull('track_token')->pluck('id') as $id) {
            DB::table('monitors')->where('id', $id)->update(['track_token' => (string) Str::uuid()]);
        }

        Schema::create('monitor_visit_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();
            $table->date('visit_date');
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->timestamps();

            $table->unique(['monitor_id', 'visit_date']);
        });

        Schema::create('status_page_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->string('title')->default('ObiOra Status');
            $table->string('slug')->default('status');
            $table->boolean('noindex')->default(true);
            $table->json('visible_server_ids')->nullable();
            $table->json('visible_monitor_ids')->nullable();
            $table->timestamps();
        });

        DB::table('status_page_settings')->insert([
            'is_enabled' => true,
            'title' => 'ObiOra Status',
            'slug' => 'status',
            'noindex' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('status_page_settings');
        Schema::dropIfExists('monitor_visit_daily');
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('track_token');
        });
    }
};
