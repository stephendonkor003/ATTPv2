<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            if (!Schema::hasColumn('procurements', 'application_start_date')) {
                $table->date('application_start_date')->nullable()->after('fiscal_year');
            }
            if (!Schema::hasColumn('procurements', 'application_end_date')) {
                $table->date('application_end_date')->nullable()->after('application_start_date');
            }
            if (!Schema::hasColumn('procurements', 'application_duration_days')) {
                $table->unsignedInteger('application_duration_days')->nullable()->after('application_end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('procurements', 'application_start_date')) {
                $columns[] = 'application_start_date';
            }
            if (Schema::hasColumn('procurements', 'application_end_date')) {
                $columns[] = 'application_end_date';
            }
            if (Schema::hasColumn('procurements', 'application_duration_days')) {
                $columns[] = 'application_duration_days';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
