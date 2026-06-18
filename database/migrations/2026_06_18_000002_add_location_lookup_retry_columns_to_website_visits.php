<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('website_visits', 'location_lookup_attempts')) {
                $table->unsignedTinyInteger('location_lookup_attempts')->default(0)->after('is_active');
            }

            if (!Schema::hasColumn('website_visits', 'location_lookup_last_attempt_at')) {
                $table->timestamp('location_lookup_last_attempt_at')->nullable()->index()->after('location_lookup_attempts');
            }

            if (!Schema::hasColumn('website_visits', 'location_lookup_provider')) {
                $table->string('location_lookup_provider', 80)->nullable()->after('location_lookup_last_attempt_at');
            }

            if (!Schema::hasColumn('website_visits', 'location_lookup_failed_at')) {
                $table->timestamp('location_lookup_failed_at')->nullable()->index()->after('location_lookup_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('website_visits', function (Blueprint $table) {
            if (Schema::hasColumn('website_visits', 'location_lookup_failed_at')) {
                $table->dropColumn('location_lookup_failed_at');
            }

            if (Schema::hasColumn('website_visits', 'location_lookup_provider')) {
                $table->dropColumn('location_lookup_provider');
            }

            if (Schema::hasColumn('website_visits', 'location_lookup_last_attempt_at')) {
                $table->dropColumn('location_lookup_last_attempt_at');
            }

            if (Schema::hasColumn('website_visits', 'location_lookup_attempts')) {
                $table->dropColumn('location_lookup_attempts');
            }
        });
    }
};
