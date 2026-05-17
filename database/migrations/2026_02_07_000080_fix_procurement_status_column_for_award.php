<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('procurements')) {
            return;
        }

        // Ensure status can store "awarded" (avoid enum truncation).
        try {
            DB::statement('ALTER TABLE procurements ALTER COLUMN status TYPE VARCHAR(50) USING status::VARCHAR(50)');
            DB::statement('ALTER TABLE procurements ALTER COLUMN status DROP NOT NULL');
        } catch (\Throwable $exception) {
            // Ignore if the column is already compatible.
        }

        // Backfill status for awarded records if it was truncated or empty.
        DB::statement("
            UPDATE procurements
            SET status = 'awarded'
            WHERE awarded_vendor_id IS NOT NULL
              AND (status IS NULL OR status = '' OR status = 'award')
        ");
    }

    public function down(): void
    {
        // No down migration to avoid restoring an incompatible enum.
    }
};
