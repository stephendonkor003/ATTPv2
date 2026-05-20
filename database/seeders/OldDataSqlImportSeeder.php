<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OldDataSqlImportSeeder extends Seeder
{
    private string $dumpPath;

    private array $tableMap = [
        'applicant_user' => 'applicant_user',
        'applicants' => 'applicants',
        'assignments' => 'assignments',
        'categories' => 'categories',
        'committee_members' => 'committee_members',
        'committees' => 'committees',
        'evaluations' => 'evaluations',
        'evaluator_teams' => 'evaluator_teams',
        'financial_evaluations' => 'financial_evaluations',
        'geo_regions' => 'geo_regions',
        'prescreening_criteria' => 'prescreening_criteria',
        'rework_requests' => 'rework_requests',
        'site_visit_evaluations' => 'site_visit_evaluations',
        'team_consortia' => 'team_consortiums',
        'team_members' => 'team_members',
        'think_datasets' => 'think_datasets',
        'users' => 'users',
    ];

    private array $columns = [];

    private array $columnMeta = [];

    private array $userIdOverrides = [];

    private array $insertedByTable = [];

    private array $skippedByTable = [];

    private array $sampleErrors = [];

    private int $processedRows = 0;

    private int $importedRows = 0;

    private int $skippedRows = 0;

    public function run(): void
    {
        $this->dumpPath = database_path('seeders/olddata.sql');

        if (! file_exists($this->dumpPath)) {
            $this->command?->warn('olddata.sql was not found in database/seeders.');
            return;
        }

        DB::disableQueryLog();
        $this->loadSchemaMetadata();
        $this->primeUserIdOverrides();
        $this->importDumpInserts();

        $this->command?->info("olddata.sql import completed: {$this->importedRows} row(s) imported/upserted, {$this->skippedRows} row(s) skipped.");

        foreach ($this->insertedByTable as $table => $count) {
            $this->command?->line(" - {$table}: {$count}");
        }

        if ($this->skippedByTable) {
            arsort($this->skippedByTable);
            foreach ($this->skippedByTable as $table => $count) {
                $sample = mb_strimwidth($this->sampleErrors[$table] ?? 'No sample error captured.', 0, 360, '...');
                $this->command?->warn("Skipped {$count} row(s) from {$table}: {$sample}");
            }
        }
    }

    private function loadSchemaMetadata(): void
    {
        $rows = DB::select(
            "select table_name, column_name, data_type, udt_name, is_nullable, character_maximum_length
             from information_schema.columns
             where table_schema = 'public'
             order by table_name, ordinal_position"
        );

        foreach ($rows as $row) {
            $this->columns[$row->table_name][] = $row->column_name;
            $this->columnMeta[$row->table_name][$row->column_name] = [
                'data_type' => $row->data_type,
                'udt_name' => $row->udt_name,
                'nullable' => $row->is_nullable === 'YES',
                'max_length' => $row->character_maximum_length ? (int) $row->character_maximum_length : null,
            ];
        }
    }

    private function primeUserIdOverrides(): void
    {
        foreach ($this->readInsertStatements() as $statement) {
            $parsed = $this->parseInsertStatement($statement);
            if (! $parsed || $parsed['table'] !== 'users') {
                continue;
            }

            foreach ($parsed['rows'] as $row) {
                $legacyId = $row['id'] ?? null;
                $email = $row['email'] ?? null;
                if (! $legacyId || ! $email) {
                    continue;
                }

                $existingId = DB::table('users')->where('email', $email)->value('id');
                if ($existingId) {
                    $this->userIdOverrides[(string) $legacyId] = (string) $existingId;
                }
            }
        }
    }

    private function importDumpInserts(): void
    {
        foreach ($this->readInsertStatements() as $statement) {
            $parsed = $this->parseInsertStatement($statement);
            if (! $parsed) {
                continue;
            }

            $legacyTable = $parsed['table'];
            $targetTable = $this->tableMap[$legacyTable] ?? null;
            if (! $targetTable || ! Schema::hasTable($targetTable)) {
                continue;
            }

            foreach ($parsed['rows'] as $legacyRow) {
                $this->processedRows++;
                $row = $this->mapRow($legacyTable, $targetTable, $legacyRow);

                if (! $row) {
                    continue;
                }

                $this->upsertRow($legacyTable, $targetTable, $row);
            }
        }
    }

    private function readInsertStatements(): iterable
    {
        $handle = fopen($this->dumpPath, 'rb');
        if (! $handle) {
            return;
        }

        $statement = '';
        while (($line = fgets($handle)) !== false) {
            if ($statement === '' && ! str_starts_with(ltrim($line), 'INSERT INTO')) {
                continue;
            }

            $statement .= $line;

            if (str_ends_with(rtrim($line), ';')) {
                yield $statement;
                $statement = '';
            }
        }

        fclose($handle);
    }

    private function parseInsertStatement(string $statement): ?array
    {
        if (! preg_match('/INSERT INTO `([^`]+)`\s*\((.*?)\)\s*VALUES\s*(.*);$/s', trim($statement), $matches)) {
            return null;
        }

        $columns = $this->parseColumnList($matches[2]);
        $rows = [];

        foreach ($this->parseTuples($matches[3]) as $tuple) {
            if (count($tuple) !== count($columns)) {
                continue;
            }

            $rows[] = array_combine($columns, $tuple);
        }

        return [
            'table' => $matches[1],
            'rows' => $rows,
        ];
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

            if (! $inTuple) {
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

    private function mapRow(string $legacyTable, string $targetTable, array $legacyRow): array
    {
        $targetColumns = array_flip($this->columns[$targetTable] ?? []);
        $row = [];

        foreach ($legacyRow as $column => $value) {
            if (! isset($targetColumns[$column])) {
                continue;
            }

            $row[$column] = $this->normaliseValue(
                $targetTable,
                $column,
                $this->mapLegacyReference($legacyTable, $column, $value)
            );
        }

        if (isset($targetColumns['id'], $legacyRow['id'])) {
            $row['id'] = $this->legacyUuid($legacyTable, $legacyRow['id']);
        }

        if ($legacyTable === 'users' && isset($legacyRow['id'], $this->userIdOverrides[(string) $legacyRow['id']])) {
            $row['id'] = $this->userIdOverrides[(string) $legacyRow['id']];
        }

        if ($legacyTable === 'evaluations') {
            $row['name'] ??= 'Legacy Application Evaluation #' . ($legacyRow['id'] ?? '');
            $row['type'] ??= 'legacy_application_evaluation';
            $row['description'] ??= 'Imported from database/seeders/olddata.sql.';
            $row['created_by'] ??= $row['evaluator_id'] ?? null;
        }

        if ($legacyTable === 'prescreening_criteria') {
            $row['name'] ??= 'Legacy Prescreening #' . ($legacyRow['id'] ?? '');
            $row['description'] ??= 'Imported prescreening decision from database/seeders/olddata.sql.';
            $row['field_key'] ??= 'legacy_prescreening_' . ($legacyRow['id'] ?? '');
            $row['evaluation_type'] ??= 'legacy_application_prescreening';
        }

        return array_intersect_key($row, $targetColumns);
    }

    private function mapLegacyReference(string $legacyTable, string $column, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $map = [
            'applicant_user' => [
                'applicant_id' => 'applicants',
                'user_id' => 'users',
            ],
            'assignments' => [
                'applicant_id' => 'applicants',
                'evaluator_id' => 'users',
            ],
            'committee_members' => [
                'committee_id' => 'committees',
                'user_id' => 'users',
            ],
            'committees' => [
                'project_id' => 'projects',
                'chairperson_id' => 'users',
            ],
            'evaluations' => [
                'applicant_id' => 'applicants',
                'evaluator_id' => 'users',
            ],
            'evaluator_teams' => [
                'leader_id' => 'users',
                'created_by' => 'users',
            ],
            'financial_evaluations' => [
                'applicant_id' => 'applicants',
                'evaluator_id' => 'users',
            ],
            'prescreening_criteria' => [
                'applicant_id' => 'applicants',
                'evaluator_id' => 'users',
            ],
            'rework_requests' => [
                'evaluation_id' => 'evaluations',
                'evaluator_id' => 'users',
            ],
            'site_visit_evaluations' => [
                'consortium_id' => 'applicants',
                'team_id' => 'evaluator_teams',
                'leader_id' => 'users',
                'evaluator_id' => 'users',
                'rework_requested_by' => 'users',
                'rework_completed_by' => 'users',
            ],
            'team_consortia' => [
                'team_id' => 'evaluator_teams',
                'consortium_id' => 'applicants',
                'assigned_by' => 'users',
            ],
            'team_members' => [
                'team_id' => 'evaluator_teams',
                'user_id' => 'users',
            ],
        ];

        $referenceTable = $map[$legacyTable][$column] ?? null;
        if (! $referenceTable) {
            return $value;
        }

        return $this->legacyUuid($referenceTable, $value);
    }

    private function normaliseValue(string $table, string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $meta = $this->columnMeta[$table][$column] ?? [];
        $dataType = $meta['data_type'] ?? '';
        $udtName = $meta['udt_name'] ?? '';

        if (is_string($value)) {
            $trimmed = trim($value);
            if (in_array(strtoupper($trimmed), ['NULL', 'N/A'], true)) {
                return null;
            }
        }

        if ($dataType === 'boolean') {
            return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y', 'on'], true);
        }

        if ($udtName === 'uuid') {
            return is_string($value) && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value)
                ? strtolower($value)
                : null;
        }

        if (in_array($dataType, ['integer', 'bigint', 'smallint'], true)) {
            return is_numeric($value) ? (int) $value : null;
        }

        if ($dataType === 'numeric') {
            return is_numeric($value) ? $value : null;
        }

        if ($dataType === 'date') {
            return $this->normaliseDate($value);
        }

        if (str_starts_with($dataType, 'timestamp')) {
            return $this->normaliseTimestamp($value);
        }

        if ($dataType === 'character varying' && ($meta['max_length'] ?? null)) {
            return mb_substr((string) $value, 0, (int) $meta['max_length']);
        }

        return $value;
    }

    private function normaliseDate(mixed $value): ?string
    {
        if (! $value || str_starts_with((string) $value, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function normaliseTimestamp(mixed $value): ?string
    {
        if (! $value || str_starts_with((string) $value, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function upsertRow(string $legacyTable, string $targetTable, array $row): void
    {
        if (! $row) {
            return;
        }

        try {
            if (isset($row['id'])) {
                DB::table($targetTable)->updateOrInsert(['id' => $row['id']], $row);
            } elseif ($targetTable === 'applicant_user') {
                DB::table($targetTable)->updateOrInsert([
                    'applicant_id' => $row['applicant_id'] ?? null,
                    'user_id' => $row['user_id'] ?? null,
                ], $row);
            } else {
                DB::table($targetTable)->insert($row);
            }

            $this->importedRows++;
            $this->insertedByTable[$targetTable] = ($this->insertedByTable[$targetTable] ?? 0) + 1;
        } catch (Throwable $exception) {
            $this->skippedRows++;
            $this->skippedByTable[$legacyTable] = ($this->skippedByTable[$legacyTable] ?? 0) + 1;
            $this->sampleErrors[$legacyTable] ??= $exception->getMessage();
        }
    }

    private function legacyUuid(string $table, mixed $legacyId): string
    {
        if ($table === 'users' && isset($this->userIdOverrides[(string) $legacyId])) {
            return $this->userIdOverrides[(string) $legacyId];
        }

        $hash = md5('attp-olddata|' . $table . '|' . $legacyId);

        return sprintf(
            '%s-%s-4%s-%s%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex((hexdec($hash[16]) & 0x3) | 0x8),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }
}
