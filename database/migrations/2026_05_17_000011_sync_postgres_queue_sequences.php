<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('jobs')) {
            return;
        }

        DB::statement(<<<'SQL'
            SELECT setval(
                pg_get_serial_sequence('jobs', 'id'),
                GREATEST((SELECT COALESCE(MAX(id), 0) FROM jobs), 1),
                (SELECT COUNT(*) > 0 FROM jobs)
            )
        SQL);
    }

    public function down(): void
    {
        //
    }
};
