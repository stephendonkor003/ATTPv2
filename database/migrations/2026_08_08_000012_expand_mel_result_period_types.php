<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('me_indicator_results')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE me_indicator_results DROP CONSTRAINT IF EXISTS me_indicator_results_period_type_check');
            DB::statement("ALTER TABLE me_indicator_results ADD CONSTRAINT me_indicator_results_period_type_check CHECK (period_type::text = ANY (ARRAY['year','quarter','month','semi_annual','annual','custom']::text[]))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE me_indicator_results MODIFY period_type ENUM('year','quarter','month','semi_annual','annual','custom') NOT NULL");
        }
    }

    public function down(): void
    {
        // Do not narrow the allowed values after semi-annual/annual results exist.
    }
};
