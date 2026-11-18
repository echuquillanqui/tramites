<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('session_key', 36)->unique();
            $table->string('device', 20)->default('other');
            $table->string('browser', 40)->default('other');
            $table->string('landing_page', 255)->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('last_seen_at')->index();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamps();
            $table->index(['started_at', 'device']);
        });

        Schema::create('prototype_cases', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('title', 180);
            $table->text('summary');
            $table->json('content')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('visitor_session_id')->constrained('visitor_sessions')->cascadeOnDelete();
            $table->string('event_type', 40)->index();
            $table->foreignId('prototype_case_id')->nullable()->constrained('prototype_cases')->nullOnDelete();
            $table->string('page', 255)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['prototype_case_id', 'event_type', 'created_at'], 'analytics_case_type_date_idx');
            $table->index(['visitor_session_id', 'event_type', 'created_at'], 'analytics_session_type_date_idx');
        });

        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->json('setting_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('admin_settings');
        Schema::dropIfExists('prototype_cases');
        Schema::dropIfExists('visitor_sessions');
    }
};
