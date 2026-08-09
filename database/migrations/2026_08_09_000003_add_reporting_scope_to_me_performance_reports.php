<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('me_performance_reports', function (Blueprint $table): void {
            $table->json('reporting_scope')->nullable()->after('reporting_period_label');
        });
    }

    public function down(): void
    {
        Schema::table('me_performance_reports', function (Blueprint $table): void {
            $table->dropColumn('reporting_scope');
        });
    }
};
