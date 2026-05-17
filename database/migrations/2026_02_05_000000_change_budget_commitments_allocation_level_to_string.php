<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE myb_budget_commitments ALTER COLUMN allocation_level TYPE VARCHAR(50) USING allocation_level::VARCHAR(50)');
        DB::statement('ALTER TABLE myb_budget_commitments ALTER COLUMN allocation_level DROP NOT NULL');
    }

    public function down(): void
    {
        DB::table('myb_budget_commitments')
            ->whereNotNull('allocation_level')
            ->whereRaw("allocation_level !~ '^-?[0-9]+(\\.[0-9]+)?$'")
            ->update(['allocation_level' => null]);

        DB::statement('ALTER TABLE myb_budget_commitments ALTER COLUMN allocation_level TYPE DECIMAL(15,2) USING allocation_level::DECIMAL(15,2)');
        DB::statement('ALTER TABLE myb_budget_commitments ALTER COLUMN allocation_level DROP NOT NULL');
    }
};
