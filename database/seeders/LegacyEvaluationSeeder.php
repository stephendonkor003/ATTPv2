<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegacyEvaluationSeeder extends Seeder
{
    private const TABLES = [
        'dynamic_forms',
        'dynamic_form_fields',
        'evaluations',
        'evaluation_assignments',
        'evaluation_sections',
        'evaluation_criteria',
        'evaluation_criteria_scores',
    ];

    public function run(): void
    {
        $path = $this->resolveSqlPath();
        if (!$path) {
            $this->command?->warn('Legacy evaluation SQL not found. Place the file in database/seeders/data/ATTP_v4 (2).sql or set LEGACY_SQL_PATH.');
            return;
        }

        $data = $this->parseSqlFile($path);

        $this->upsertRows('evaluations', $this->mapEvaluations($data['evaluations'] ?? []));
        $this->upsertRows('evaluation_sections', $this->mapEvaluationSections($data['evaluation_sections'] ?? []));
        $this->upsertRows('evaluation_criteria', $this->mapEvaluationCriteria($data['evaluation_criteria'] ?? []));
        $this->upsertRows('dynamic_forms', $this->mapDynamicForms($data['dynamic_forms'] ?? []));
        $this->upsertRows('dynamic_form_fields', $this->mapDynamicFormFields($data['dynamic_form_fields'] ?? []));
        $this->upsertRows('evaluation_assignments', $this->mapEvaluationAssignments($data['evaluation_assignments'] ?? []));
        $this->upsertRows('evaluation_criteria_scores', $this->mapEvaluationCriteriaScores($data['evaluation_criteria_scores'] ?? []));
    }

    private function resolveSqlPath(): ?string
    {
        $path = env('LEGACY_SQL_PATH');
        if ($path && File::exists($path)) {
            return $path;
        }

        $default = database_path('seeders/data/ATTP_v4 (2).sql');
        if (File::exists($default)) {
            return $default;
        }

        $alt = database_path('seeders/data/ATTP_v4_2.sql');
        if (File::exists($alt)) {
            return $alt;
        }

        return null;
    }

    private function parseSqlFile(string $path): array
    {
        $sql = File::get($path);
        $pattern = '/INSERT INTO `(?P<table>[^`]+)` \\((?P<cols>[^)]+)\\) VALUES\\s*(?P<values>.*?);/s';

        preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);

        $data = [];
        foreach ($matches as $match) {
            $table = $match['table'];
            if (!in_array($table, self::TABLES, true)) {
                continue;
            }

            $cols = array_map(
                fn ($col) => trim($col, " \t\n\r`"),
                explode(',', $match['cols'])
            );

            $rows = $this->parseValues($match['values']);
            foreach ($rows as $row) {
                if (count($row) !== count($cols)) {
                    continue;
                }
                $data[$table][] = array_combine($cols, $row);
            }
        }

        return $data;
    }

    private function parseValues(string $values): array
    {
        $rows = [];
        $length = strlen($values);
        $i = 0;

        while ($i < $length) {
            $ch = $values[$i];
            if ($ch !== '(') {
                $i++;
                continue;
            }
            $i++;

            $row = [];
            $value = '';
            $inString = false;
            $escape = false;

            while ($i < $length) {
                $ch = $values[$i];
                if ($inString) {
                    if ($escape) {
                        $value .= $ch;
                        $escape = false;
                    } elseif ($ch === '\\\\') {
                        $escape = true;
                    } elseif ($ch === "'") {
                        $inString = false;
                    } else {
                        $value .= $ch;
                    }
                    $i++;
                    continue;
                }

                if ($ch === "'") {
                    $inString = true;
                    $i++;
                    continue;
                }

                if ($ch === ',') {
                    $row[] = $this->castValue(trim($value));
                    $value = '';
                    $i++;
                    continue;
                }

                if ($ch === ')') {
                    $row[] = $this->castValue(trim($value));
                    $value = '';
                    $i++;
                    break;
                }

                $value .= $ch;
                $i++;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function castValue(string $value)
    {
        if ($value === '' || strtoupper($value) === 'NULL') {
            return null;
        }
        if (preg_match('/^-?\\d+$/', $value)) {
            return (int) $value;
        }
        if (preg_match('/^-?\\d+\\.\\d+$/', $value)) {
            return (float) $value;
        }
        return $value;
    }

    private function mapDynamicForms(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'id' => $this->legacyUuid('dynamic_forms', (int) $row['id']),
                'resource_id' => $this->uuidOrNull('resources', $row['resource_id']),
                'name' => $row['name'],
                'applies_to' => $row['applies_to'],
                'status' => $row['status'],
                'is_active' => isset($row['is_active']) ? (bool) $row['is_active'] : null,
                'created_by' => $this->mapUserId($row['created_by']),
                'procurement_id' => $this->uuidOrNull('procurements', $row['procurement_id']),
                'submitted_at' => $row['submitted_at'],
                'approved_at' => $row['approved_at'],
                'approved_by' => $this->mapUserId($row['approved_by']),
                'rejection_reason' => $row['rejection_reason'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);
    }

    private function mapDynamicFormFields(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'id' => $this->legacyUuid('dynamic_form_fields', (int) $row['id']),
                'form_id' => $this->uuidOrNull('dynamic_forms', $row['form_id']),
                'label' => $row['label'],
                'field_key' => $row['field_key'],
                'field_type' => $row['field_type'],
                'is_required' => isset($row['is_required']) ? (bool) $row['is_required'] : null,
                'options' => $row['options'],
                'sort_order' => $row['sort_order'],
                'created_by' => $this->mapUserId($row['created_by']),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);
    }

    private function mapEvaluations(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'id' => $this->legacyUuid('evaluations', (int) $row['id']),
                'name' => $row['name'],
                'description' => $row['description'],
                'status' => $row['status'],
                'type' => $row['type'],
                'created_by' => $this->mapUserId($row['created_by']),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);
    }

    private function mapEvaluationAssignments(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'id' => $this->legacyUuid('evaluation_assignments', (int) $row['id']),
                'evaluation_id' => $this->uuidOrNull('evaluations', $row['evaluation_id']),
                'procurement_id' => $this->uuidOrNull('procurements', $row['procurement_id']),
                'form_submission_id' => $this->uuidOrNull('form_submissions', $row['form_submission_id']),
                'user_id' => $this->uuidOrNull('users', $row['user_id']),
                'assigned_by' => $this->mapUserId($row['assigned_by']),
                'assigned_at' => $row['assigned_at'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);
    }

    private function mapEvaluationSections(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'id' => $this->legacyUuid('evaluation_sections', (int) $row['id']),
                'evaluation_id' => $this->uuidOrNull('evaluations', $row['evaluation_id']),
                'name' => $row['name'],
                'description' => $row['description'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);
    }

    private function mapEvaluationCriteria(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'id' => $this->legacyUuid('evaluation_criteria', (int) $row['id']),
                'evaluation_section_id' => $this->uuidOrNull('evaluation_sections', $row['evaluation_section_id']),
                'name' => $row['name'],
                'description' => $row['description'],
                'max_score' => $row['max_score'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);
    }

    private function mapEvaluationCriteriaScores(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'id' => $this->legacyUuid('evaluation_criteria_scores', (int) $row['id']),
                'submission_id' => $this->uuidOrNull('evaluation_submissions', $row['submission_id']),
                'evaluation_criteria_id' => $this->uuidOrNull('evaluation_criteria', $row['evaluation_criteria_id']),
                'score' => $row['score'],
                'decision' => isset($row['decision']) ? (int) $row['decision'] : null,
                'comment' => $row['comment'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);
    }

    private function upsertRows(string $table, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $columns = array_keys($rows[0]);
        $updateColumns = array_values(array_diff($columns, ['id']));

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->upsert($chunk, ['id'], $updateColumns);
        }
    }

    private function uuidOrNull(string $scope, $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return $this->legacyUuid($scope, (int) $value);
        }
        return (string) $value;
    }

    private function mapUserId($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return $this->legacyUuid('users', (int) $value);
        }
        return (string) $value;
    }

    private function legacyUuid(string $scope, int $legacyId): string
    {
        $hash = md5($scope . ':' . $legacyId);
        $timeHi = hexdec(substr($hash, 12, 4));
        $timeHi = ($timeHi & 0x0fff) | 0x5000;
        $clockSeq = hexdec(substr($hash, 16, 4));
        $clockSeq = ($clockSeq & 0x3fff) | 0x8000;

        return sprintf(
            '%s-%s-%04x-%04x-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            $timeHi,
            $clockSeq,
            substr($hash, 20, 12)
        );
    }
}
