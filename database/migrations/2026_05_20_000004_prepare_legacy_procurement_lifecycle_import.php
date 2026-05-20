<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alterColumnsToText('form_submission_values', ['value']);
        $this->alterColumnsToText('evaluation_section_scores', ['weaknesses']);
        $this->alterColumnsToText('procurement_audit_logs', ['metadata']);
    }

    public function down(): void
    {
        // Intentionally non-destructive. Legacy imported notes and JSON payloads can exceed
        // the original varchar sizes, so shrinking them back could truncate user data.
    }

    private function alterColumnsToText(string $table, array $columns): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE TEXT USING %s::TEXT',
                $this->quoteIdentifier($table),
                $this->quoteIdentifier($column),
                $this->quoteIdentifier($column)
            ));
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
};
