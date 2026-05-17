<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LegacySqlDumpSeeder extends Seeder
{
    private string $dumpPath;

    private array $tables = [];

    private array $columns = [];

    private array $columnTypes = [];

    private array $foreignKeys = [];

    private array $existsCache = [];

    private array $skippedTables = [];

    private int $insertedRows = 0;

    private int $skippedRows = 0;

    private array $insertedByTable = [];

    private array $skippedByTable = [];

    private array $sampleErrors = [];

    private array $pendingRows = [];

    public function run(): void
    {
        $this->dumpPath = database_path('seeders/ATTP_v5.sql');

        if (!file_exists($this->dumpPath)) {
            $this->command?->error('Legacy SQL dump not found at database/seeders/ATTP_v5.sql.');
            return;
        }

        DB::disableQueryLog();
        $this->loadSchemaMetadata();
        $this->truncateApplicationTables();
        $this->importDumpInserts();

        $skippedTables = count($this->skippedTables);
        $this->command?->info("Legacy SQL import completed: {$this->insertedRows} rows inserted, {$this->skippedRows} rows skipped, {$skippedTables} dump table(s) skipped.");

        if ($this->skippedByTable) {
            arsort($this->skippedByTable);
            foreach (array_slice($this->skippedByTable, 0, 15, true) as $table => $count) {
                $sample = mb_strimwidth($this->sampleErrors[$table] ?? 'No sample error captured.', 0, 500, '...');
                $this->command?->warn("Skipped {$count} row(s) from {$table}: {$sample}");
            }
        }
    }

    private function loadSchemaMetadata(): void
    {
        $rows = DB::select(
            "select table_name, column_name, data_type, udt_name
             from information_schema.columns
             where table_schema = 'public'
             order by table_name, ordinal_position"
        );

        foreach ($rows as $row) {
            $this->tables[$row->table_name] = true;
            $this->columns[$row->table_name][] = $row->column_name;
            $this->columnTypes[$row->table_name][$row->column_name] = [
                'data_type' => $row->data_type,
                'udt_name' => $row->udt_name,
            ];
        }

        $foreignKeys = DB::select(
            "select
                tc.table_name,
                kcu.column_name,
                ccu.table_name as foreign_table_name,
                ccu.column_name as foreign_column_name,
                cols.is_nullable
             from information_schema.table_constraints tc
             join information_schema.key_column_usage kcu
                on tc.constraint_name = kcu.constraint_name
                and tc.table_schema = kcu.table_schema
             join information_schema.constraint_column_usage ccu
                on ccu.constraint_name = tc.constraint_name
                and ccu.table_schema = tc.table_schema
             join information_schema.columns cols
                on cols.table_schema = tc.table_schema
                and cols.table_name = tc.table_name
                and cols.column_name = kcu.column_name
             where tc.constraint_type = 'FOREIGN KEY'
                and tc.table_schema = 'public'"
        );

        foreach ($foreignKeys as $foreignKey) {
            $this->foreignKeys[$foreignKey->table_name][$foreignKey->column_name] = [
                'foreign_table' => $foreignKey->foreign_table_name,
                'foreign_column' => $foreignKey->foreign_column_name,
                'nullable' => $foreignKey->is_nullable === 'YES',
            ];
        }
    }

    private function truncateApplicationTables(): void
    {
        $tables = collect(array_keys($this->tables))
            ->reject(fn ($table) => $table === 'migrations')
            ->map(fn ($table) => '"' . str_replace('"', '""', $table) . '"')
            ->values();

        if ($tables->isEmpty()) {
            return;
        }

        DB::statement('TRUNCATE TABLE ' . $tables->implode(', ') . ' RESTART IDENTITY CASCADE');
    }

    private function importDumpInserts(): void
    {
        $handle = fopen($this->dumpPath, 'rb');
        if (!$handle) {
            $this->command?->error('Unable to open legacy SQL dump.');
            return;
        }

        $statement = '';

        while (($line = fgets($handle)) !== false) {
            if ($statement === '' && !str_starts_with(ltrim($line), 'INSERT INTO')) {
                continue;
            }

            $statement .= $line;

            if (str_ends_with(rtrim($line), ';')) {
                $this->importInsertStatement($statement);
                $statement = '';
            }
        }

        fclose($handle);

        $this->retryPendingRows();
    }

    private function importInsertStatement(string $statement): void
    {
        if (!preg_match('/INSERT INTO `([^`]+)`\s*\((.*?)\)\s*VALUES\s*(.*);$/s', trim($statement), $matches)) {
            return;
        }

        $table = $matches[1];
        if ($table === 'migrations') {
            return;
        }

        if (!isset($this->tables[$table])) {
            $this->skippedTables[$table] = true;
            return;
        }

        $dumpColumns = $this->parseColumnList($matches[2]);
        $knownColumns = array_flip($this->columns[$table] ?? []);
        $columnMap = [];

        foreach ($dumpColumns as $index => $column) {
            if (isset($knownColumns[$column])) {
                $columnMap[$index] = $column;
            }
        }

        if (!$columnMap) {
            $this->skippedTables[$table] = true;
            return;
        }

        $batch = [];
        foreach ($this->parseTuples($matches[3]) as $tuple) {
            $row = [];

            foreach ($columnMap as $index => $column) {
                $row[$column] = $this->normaliseValue($table, $column, $tuple[$index] ?? null);
            }

            $batch[] = $row;

            if (count($batch) >= 500) {
                $this->insertBatch($table, $batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->insertBatch($table, $batch);
        }
    }

    private function parseColumnList(string $columns): array
    {
        preg_match_all('/`([^`]+)`/', $columns, $matches);

        return $matches[1] ?? [];
    }

    private function parseTuples(string $values): iterable
    {
        $length = strlen($values);
        $row = [];
        $token = '';
        $quoted = false;
        $inQuote = false;
        $inTuple = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $values[$i];

            if (!$inTuple) {
                if ($char === '(') {
                    $inTuple = true;
                    $row = [];
                    $token = '';
                    $quoted = false;
                }
                continue;
            }

            if ($inQuote) {
                if ($char === '\\') {
                    $i++;
                    $token .= $this->unescapeMySqlChar($values[$i] ?? '');
                    continue;
                }

                if ($char === "'") {
                    $inQuote = false;
                    continue;
                }

                $token .= $char;
                continue;
            }

            if ($char === "'") {
                $inQuote = true;
                $quoted = true;
                if (trim($token) === '') {
                    $token = '';
                }
                continue;
            }

            if ($char === ',') {
                $row[] = $this->finaliseToken($token, $quoted);
                $token = '';
                $quoted = false;
                continue;
            }

            if ($char === ')') {
                $row[] = $this->finaliseToken($token, $quoted);
                yield $row;
                $inTuple = false;
                $token = '';
                $quoted = false;
                continue;
            }

            $token .= $char;
        }
    }

    private function finaliseToken(string $token, bool $quoted): mixed
    {
        if ($quoted) {
            return $token;
        }

        $value = trim($token);

        return strtoupper($value) === 'NULL' ? null : $value;
    }

    private function unescapeMySqlChar(string $char): string
    {
        return match ($char) {
            '0' => "\0",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'b' => "\b",
            'Z' => chr(26),
            default => $char,
        };
    }

    private function normaliseValue(string $table, string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $this->columnTypes[$table][$column] ?? [];
        $dataType = $type['data_type'] ?? '';
        $udtName = $type['udt_name'] ?? '';

        if ($value === '' && !in_array($dataType, ['character varying', 'text', 'character'], true)) {
            return null;
        }

        if ($dataType === 'boolean') {
            return in_array((string) $value, ['1', 'true', 'TRUE', 'yes', 'on'], true);
        }

        if ($udtName === 'uuid' && (!is_string($value) || !preg_match('/^[0-9a-fA-F-]{36}$/', $value))) {
            return null;
        }

        if (in_array($dataType, ['date', 'timestamp without time zone', 'timestamp with time zone'], true)) {
            if (str_starts_with((string) $value, '0000-00-00')) {
                return null;
            }
        }

        if (in_array($dataType, ['json', 'jsonb'], true)) {
            if ($value === '') {
                return null;
            }

            json_decode((string) $value);
            return json_last_error() === JSON_ERROR_NONE ? $value : json_encode($value);
        }

        return $value;
    }

    private function insertBatch(string $table, array $batch): void
    {
        try {
            DB::table($table)->insert($batch);
            $this->insertedRows += count($batch);
            $this->insertedByTable[$table] = ($this->insertedByTable[$table] ?? 0) + count($batch);
        } catch (Throwable $exception) {
            foreach ($batch as $row) {
                try {
                    DB::table($table)->insert($row);
                    $this->insertedRows++;
                    $this->insertedByTable[$table] = ($this->insertedByTable[$table] ?? 0) + 1;
                    $this->sampleErrors[$table] ??= $exception->getMessage();
                } catch (Throwable $rowException) {
                    $this->pendingRows[$table][] = $row;
                    $this->sampleErrors[$table] ??= $rowException->getMessage();
                }
            }
        }
    }

    private function retryPendingRows(): void
    {
        for ($pass = 1; $pass <= 5; $pass++) {
            if (!$this->pendingRows) {
                return;
            }

            $remaining = [];
            $insertedThisPass = 0;

            foreach ($this->pendingRows as $table => $rows) {
                foreach ($rows as $row) {
                    try {
                        DB::table($table)->insert($row);
                        $this->insertedRows++;
                        $this->insertedByTable[$table] = ($this->insertedByTable[$table] ?? 0) + 1;
                        $insertedThisPass++;
                    } catch (Throwable $exception) {
                        $remaining[$table][] = $row;
                        $this->sampleErrors[$table] = $exception->getMessage();
                    }
                }
            }

            $this->pendingRows = $remaining;

            if ($insertedThisPass === 0) {
                break;
            }
        }

        $this->retryPendingRowsWithNullableForeignKeys();

        foreach ($this->pendingRows as $table => $rows) {
            $count = count($rows);
            $this->skippedRows += $count;
            $this->skippedByTable[$table] = ($this->skippedByTable[$table] ?? 0) + $count;
        }
    }

    private function retryPendingRowsWithNullableForeignKeys(): void
    {
        for ($pass = 1; $pass <= 5; $pass++) {
            if (!$this->pendingRows) {
                return;
            }

            $remaining = [];
            $insertedThisPass = 0;

            foreach ($this->pendingRows as $table => $rows) {
                foreach ($rows as $row) {
                    $cleanRow = $this->nullMissingNullableForeignKeys($table, $row);

                    try {
                        DB::table($table)->insert($cleanRow);
                        $this->insertedRows++;
                        $this->insertedByTable[$table] = ($this->insertedByTable[$table] ?? 0) + 1;
                        $insertedThisPass++;
                    } catch (Throwable $exception) {
                        $remaining[$table][] = $cleanRow;
                        $this->sampleErrors[$table] = $exception->getMessage();
                    }
                }
            }

            $this->pendingRows = $remaining;

            if ($insertedThisPass === 0) {
                return;
            }
        }
    }

    private function nullMissingNullableForeignKeys(string $table, array $row): array
    {
        foreach ($this->foreignKeys[$table] ?? [] as $column => $foreignKey) {
            if (!array_key_exists($column, $row) || $row[$column] === null || !$foreignKey['nullable']) {
                continue;
            }

            if (!$this->parentExists($foreignKey['foreign_table'], $foreignKey['foreign_column'], $row[$column])) {
                $row[$column] = null;
            }
        }

        return $row;
    }

    private function parentExists(string $table, string $column, mixed $value): bool
    {
        $cacheKey = $table . '|' . $column . '|' . $value;

        if (array_key_exists($cacheKey, $this->existsCache)) {
            return $this->existsCache[$cacheKey];
        }

        return $this->existsCache[$cacheKey] = DB::table($table)->where($column, $value)->exists();
    }
}
