<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('me_reporting_periods')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE me_reporting_periods DROP CONSTRAINT IF EXISTS me_reporting_periods_period_type_check');
            DB::statement("ALTER TABLE me_reporting_periods ADD CONSTRAINT me_reporting_periods_period_type_check CHECK (period_type::text = ANY (ARRAY['year','quarter','month','semi_annual','annual','custom']::text[]))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE me_reporting_periods MODIFY period_type ENUM('year','quarter','month','semi_annual','annual','custom') NOT NULL");
        }
    }

    public function down(): void
    {
        // Semi-annual and annual records may exist after this migration. A
        // rollback must not install a narrower constraint that rejects them.
    }
};
