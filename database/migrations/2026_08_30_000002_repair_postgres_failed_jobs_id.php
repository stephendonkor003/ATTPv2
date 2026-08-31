<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql'
            || ! Schema::hasTable('failed_jobs')
            || ! Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        $idColumn = DB::selectOne(<<<'SQL'
            SELECT data_type, udt_name
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'failed_jobs'
              AND column_name = 'id'
            LIMIT 1
        SQL);

        if (($idColumn->data_type ?? null) !== 'uuid'
            || ($idColumn->udt_name ?? null) !== 'uuid') {
            return;
        }

        // BIGSERIAL assigns a unique sequence value to every existing row and
        // supplies the auto-incrementing default required by database-uuids.
        DB::statement(<<<'SQL'
            ALTER TABLE "failed_jobs"
            ADD COLUMN "replacement_id" BIGSERIAL
        SQL);

        DB::statement(<<<'SQL'
            DO $$
            DECLARE
                primary_key_name text;
            BEGIN
                SELECT constraint_name
                INTO primary_key_name
                FROM information_schema.table_constraints
                WHERE table_schema = current_schema()
                  AND table_name = 'failed_jobs'
                  AND constraint_type = 'PRIMARY KEY'
                LIMIT 1;

                IF primary_key_name IS NOT NULL THEN
                    EXECUTE format(
                        'ALTER TABLE %I.%I DROP CONSTRAINT %I',
                        current_schema(),
                        'failed_jobs',
                        primary_key_name
                    );
                END IF;
            END;
            $$
        SQL);

        DB::statement('ALTER TABLE "failed_jobs" DROP COLUMN "id"');
        DB::statement('ALTER TABLE "failed_jobs" RENAME COLUMN "replacement_id" TO "id"');
        DB::statement('ALTER TABLE "failed_jobs" ADD PRIMARY KEY ("id")');
    }

    public function down(): void
    {
        // Intentionally irreversible: recreating the former UUID primary keys
        // cannot be done without inventing values and risking failed-job data.
    }
};
