<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('world_bank_indicator_observations')) {
            return;
        }

        // Handle very large aggregates (e.g., GDP totals for large regions/world).
        DB::statement('ALTER TABLE world_bank_indicator_observations ALTER COLUMN value TYPE DECIMAL(30,10)');
        DB::statement('ALTER TABLE world_bank_indicator_observations ALTER COLUMN value DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('world_bank_indicator_observations')) {
            return;
        }

        DB::statement('ALTER TABLE world_bank_indicator_observations ALTER COLUMN value TYPE DECIMAL(20,6)');
        DB::statement('ALTER TABLE world_bank_indicator_observations ALTER COLUMN value DROP NOT NULL');
    }
};
