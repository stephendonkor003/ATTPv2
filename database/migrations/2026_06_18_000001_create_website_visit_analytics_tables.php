<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('visitor_uuid', 80)->index();
            $table->string('session_id', 120)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('ip_hash', 80)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->text('referrer')->nullable();
            $table->text('landing_url')->nullable();
            $table->text('current_url')->nullable();
            $table->string('current_path', 1000)->nullable();
            $table->string('country_name', 120)->nullable()->index();
            $table->string('country_iso2', 2)->nullable()->index();
            $table->string('continent', 80)->nullable()->index();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('first_seen_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['country_iso2', 'last_seen_at'], 'website_visits_country_last_seen_idx');
            $table->index(['continent', 'last_seen_at'], 'website_visits_continent_last_seen_idx');
        });

        Schema::create('website_visit_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('website_visit_id')->constrained('website_visits')->cascadeOnDelete();
            $table->string('activity_type', 40)->index();
            $table->text('url')->nullable();
            $table->string('path', 1000)->nullable();
            $table->string('title')->nullable();
            $table->text('referrer')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['website_visit_id', 'occurred_at'], 'website_visit_activity_visit_time_idx');
            $table->index(['activity_type', 'occurred_at'], 'website_visit_activity_type_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_visit_activities');
        Schema::dropIfExists('website_visits');
    }
};
