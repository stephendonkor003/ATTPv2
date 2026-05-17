<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:health-check
                            {--detailed : Show detailed information}
                            {--fix : Attempt to fix common issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check PostgreSQL database health and identify potential issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Running PostgreSQL Database Health Check...');
        $this->newLine();

        $issues = 0;
        $warnings = 0;

        if (! $this->checkDatabaseConnection()) {
            $this->error('Database connection failed!');
            return 1;
        }

        $tables = $this->getAllTables();
        $this->info("Found {$tables->count()} tables in database");
        $this->newLine();

        foreach ($tables as $tableName) {
            if ($this->option('detailed')) {
                $this->line("Checking table: <comment>{$tableName}</comment>");
            }

            if (! $this->hasTimestamps($tableName)) {
                $warnings++;
                $this->warn("Table '{$tableName}' missing created_at/updated_at timestamps");
            }

            $missingIndexes = $this->checkForeignKeyIndexes($tableName);
            if (! empty($missingIndexes)) {
                $issues++;
                $this->error("Table '{$tableName}' missing indexes: " . implode(', ', $missingIndexes));
            }

            $orphaned = $this->checkOrphanedRecords($tableName);
            if ($orphaned > 0) {
                $issues++;
                $this->error("Table '{$tableName}' has {$orphaned} orphaned records");
            }
        }

        $this->newLine();
        $this->info('Database Health Check Summary');
        $this->line("Total Tables: <info>{$tables->count()}</info>");
        $this->line('Issues Found: ' . ($issues > 0 ? "<error>{$issues}</error>" : '<info>0</info>'));
        $this->line('Warnings: ' . ($warnings > 0 ? "<comment>{$warnings}</comment>" : '<info>0</info>'));

        if ($issues === 0 && $warnings === 0) {
            $this->newLine();
            $this->info('Database is healthy. No issues found.');
        }

        $this->newLine();

        return $issues > 0 ? 1 : 0;
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getAllTables(): Collection
    {
        return collect(Schema::getTableListing())
            ->reject(fn (string $table) => str_starts_with($table, 'pg_') || $table === 'information_schema')
            ->values();
    }

    private function hasTimestamps(string $table): bool
    {
        try {
            return Schema::hasColumn($table, 'created_at') && Schema::hasColumn($table, 'updated_at');
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkForeignKeyIndexes(string $table): array
    {
        $missing = [];

        try {
            $columns = Schema::getColumnListing($table);
            $indexedColumns = $this->indexedColumns($table);

            foreach ($columns as $column) {
                if (str_ends_with($column, '_id') && ! in_array($column, $indexedColumns, true)) {
                    $missing[] = $column;
                }
            }
        } catch (\Exception $e) {
            // Ignore inaccessible tables.
        }

        return $missing;
    }

    private function indexedColumns(string $table): array
    {
        $rows = DB::select("
            SELECT a.attname AS column_name
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE n.nspname = current_schema()
              AND t.relname = ?
        ", [$table]);

        return collect($rows)->pluck('column_name')->unique()->values()->all();
    }

    private function checkOrphanedRecords(string $table): int
    {
        $orphaned = 0;

        try {
            $foreignKeys = DB::select("
                SELECT
                    kcu.column_name,
                    ccu.table_name AS referenced_table_name,
                    ccu.column_name AS referenced_column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                    ON tc.constraint_name = kcu.constraint_name
                   AND tc.table_schema = kcu.table_schema
                JOIN information_schema.constraint_column_usage ccu
                    ON ccu.constraint_name = tc.constraint_name
                   AND ccu.table_schema = tc.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND tc.table_schema = current_schema()
                  AND tc.table_name = ?
            ", [$table]);

            foreach ($foreignKeys as $fk) {
                $tableSql = $this->wrapIdentifier($table);
                $columnSql = $this->wrapIdentifier($fk->column_name);
                $refTableSql = $this->wrapIdentifier($fk->referenced_table_name);
                $refColumnSql = $this->wrapIdentifier($fk->referenced_column_name);

                $count = DB::selectOne("
                    SELECT COUNT(*) AS count
                    FROM {$tableSql} t
                    LEFT JOIN {$refTableSql} ref
                        ON t.{$columnSql} = ref.{$refColumnSql}
                    WHERE t.{$columnSql} IS NOT NULL
                      AND ref.{$refColumnSql} IS NULL
                ");

                if ($count && (int) $count->count > 0) {
                    $orphaned += (int) $count->count;

                    if ($this->option('detailed')) {
                        $this->warn("  {$count->count} orphaned records in {$table}.{$fk->column_name}");
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore tables that cannot be checked.
        }

        return $orphaned;
    }

    private function wrapIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
