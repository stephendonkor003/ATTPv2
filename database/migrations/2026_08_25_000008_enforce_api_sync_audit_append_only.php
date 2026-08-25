<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'api_sync_events',
        'api_sync_invitation_events',
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION attp_api_sync_audit_append_only_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = '55000',
                    MESSAGE = format('%s is an append-only API synchronization audit table', TG_TABLE_NAME);
            END;
            $$;
        SQL);

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rowTrigger = $table.'_append_only_row_guard';
            $truncateTrigger = $table.'_append_only_truncate_guard';

            DB::statement("DROP TRIGGER IF EXISTS {$rowTrigger} ON {$table}");
            DB::statement("CREATE TRIGGER {$rowTrigger} BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION attp_api_sync_audit_append_only_guard()");
            DB::statement("DROP TRIGGER IF EXISTS {$truncateTrigger} ON {$table}");
            DB::statement("CREATE TRIGGER {$truncateTrigger} BEFORE TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION attp_api_sync_audit_append_only_guard()");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("DROP TRIGGER IF EXISTS {$table}_append_only_row_guard ON {$table}");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_append_only_truncate_guard ON {$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS attp_api_sync_audit_append_only_guard()');
    }
};
