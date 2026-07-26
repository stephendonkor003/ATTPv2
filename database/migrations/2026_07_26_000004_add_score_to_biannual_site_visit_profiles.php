<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('biannual_site_visit_profiles')
            || Schema::hasColumn('biannual_site_visit_profiles', 'score_percentage')
        ) {
            return;
        }

        Schema::table('biannual_site_visit_profiles', function (Blueprint $table): void {
            $table->decimal('score_percentage', 5, 2)
                ->nullable()
                ->after('completion_percentage');
            $table->index(
                ['score_percentage', 'submitted_at'],
                'bsv_profiles_score_submitted_idx'
            );
        });
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('biannual_site_visit_profiles')
            || ! Schema::hasColumn('biannual_site_visit_profiles', 'score_percentage')
        ) {
            return;
        }

        Schema::table('biannual_site_visit_profiles', function (Blueprint $table): void {
            $table->dropIndex('bsv_profiles_score_submitted_idx');
            $table->dropColumn('score_percentage');
        });
    }
};
