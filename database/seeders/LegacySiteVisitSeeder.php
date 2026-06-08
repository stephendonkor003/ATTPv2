<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacySiteVisitSeeder extends Seeder
{
    private const PROCUREMENT_ID = '3ed5b12a-8895-4cd3-a2fa-4e74bb147141';

    private array $columns = [];

    private array $counts = [];

    private ?string $adminUserId = null;

    public function run(): void
    {
        if (! $this->hasRequiredTables()) {
            $this->command?->warn('Legacy site visit import skipped because required tables are missing.');
            return;
        }

        $legacyEvaluations = DB::table('site_visit_evaluations')
            ->whereNotNull('consortium_id')
            ->orderBy('evaluation_date')
            ->orderBy('created_at')
            ->get();

        if ($legacyEvaluations->isEmpty()) {
            $this->command?->warn('Legacy site visit import skipped because no site_visit_evaluations rows were found.');
            return;
        }

        $this->adminUserId = $this->resolveAdminUserId();

        foreach ($legacyEvaluations as $evaluation) {
            $submission = $this->submissionForEvaluation($evaluation);
            if (! $submission) {
                continue;
            }

            DB::transaction(function () use ($evaluation, $submission): void {
                $siteVisitId = $this->uuid('site-visit', $evaluation->id);

                $this->upsert('site_visits', ['id' => $siteVisitId], [
                    'id' => $siteVisitId,
                    'procurement_id' => self::PROCUREMENT_ID,
                    'form_submission_id' => $submission->id,
                    'assignment_type' => $evaluation->team_id ? 'group' : 'individual',
                    'visit_type' => 'legacy_site_visit',
                    'visit_date' => $evaluation->evaluation_date,
                    'status' => 'approved',
                    'created_by' => $evaluation->leader_id ?: $evaluation->evaluator_id ?: $this->adminUserId,
                    'assigned_by' => $evaluation->leader_id ?: $evaluation->evaluator_id ?: $this->adminUserId,
                    'created_at' => $evaluation->created_at,
                    'updated_at' => $evaluation->updated_at,
                ]);

                if ($evaluation->team_id) {
                    $this->seedGroupAssignment($siteVisitId, $evaluation);
                } else {
                    $this->seedIndividualAssignment($siteVisitId, $evaluation);
                }

                $this->seedObservations($siteVisitId, $evaluation);
                $this->seedApproval($siteVisitId, $evaluation);
            });
        }

        $total = DB::table('site_visits')
            ->where('procurement_id', self::PROCUREMENT_ID)
            ->where('visit_type', 'legacy_site_visit')
            ->count();

        $this->command?->info("Legacy site visit import completed with {$total} site visit(s).");

        foreach ($this->counts as $table => $count) {
            $this->command?->line(" - {$table}: {$count}");
        }
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('site_visit_evaluations')
            && Schema::hasTable('site_visits')
            && Schema::hasTable('site_visit_observations')
            && Schema::hasTable('form_submissions');
    }

    private function submissionForEvaluation(object $evaluation): ?object
    {
        $applicant = DB::table('applicants')->where('id', $evaluation->consortium_id)->first(['id', 'code']);
        if (! $applicant?->code) {
            return null;
        }

        return DB::table('form_submissions')
            ->where('procurement_id', self::PROCUREMENT_ID)
            ->where('procurement_submission_code', $applicant->code)
            ->first(['id', 'procurement_submission_code']);
    }

    private function seedIndividualAssignment(string $siteVisitId, object $evaluation): void
    {
        $userId = $evaluation->evaluator_id ?: $evaluation->leader_id;
        if (! $userId) {
            return;
        }

        $this->upsert('site_visit_assignments', ['id' => $this->uuid('assignment', $siteVisitId)], [
            'id' => $this->uuid('assignment', $siteVisitId),
            'site_visit_id' => $siteVisitId,
            'user_id' => $userId,
        ]);
    }

    private function seedGroupAssignment(string $siteVisitId, object $evaluation): void
    {
        $team = DB::table('evaluator_teams')->where('id', $evaluation->team_id)->first();
        $groupId = $this->uuid('group', $siteVisitId);
        $leaderId = $evaluation->leader_id ?: $team?->leader_id ?: $evaluation->evaluator_id;

        $this->upsert('site_visit_groups', ['id' => $groupId], [
            'id' => $groupId,
            'site_visit_id' => $siteVisitId,
            'group_name' => $team?->name ?: 'Legacy Site Visit Team',
            'leader_id' => $leaderId,
        ]);

        $members = DB::table('team_members')
            ->where('team_id', $evaluation->team_id)
            ->get(['user_id', 'role']);

        if ($members->isEmpty() && $leaderId) {
            $members = collect([(object) ['user_id' => $leaderId, 'role' => 'leader']]);
        }

        foreach ($members as $member) {
            if (! $member->user_id) {
                continue;
            }

            $this->upsert('site_visit_group_members', [
                'id' => $this->uuid('group-member', $groupId . '|' . $member->user_id),
            ], [
                'id' => $this->uuid('group-member', $groupId . '|' . $member->user_id),
                'group_id' => $groupId,
                'user_id' => $member->user_id,
                'role' => $member->user_id === $leaderId ? 'leader' : ($member->role ?: 'member'),
            ]);
        }
    }

    private function seedObservations(string $siteVisitId, object $evaluation): void
    {
        foreach ($this->observationSections() as $section) {
            $description = $this->sectionDescription($evaluation, $section);
            if (! $description) {
                continue;
            }

            $this->upsert('site_visit_observations', [
                'id' => $this->uuid('observation', $siteVisitId . '|' . $section['key']),
            ], [
                'id' => $this->uuid('observation', $siteVisitId . '|' . $section['key']),
                'site_visit_id' => $siteVisitId,
                'category' => $section['label'],
                'description' => $description,
                'severity' => $this->sectionSeverity($evaluation, $section),
                'action_required' => $this->sectionNeedsAction($evaluation, $section) ? '1' : '0',
            ]);
        }
    }

    private function seedApproval(string $siteVisitId, object $evaluation): void
    {
        $reviewerId = $this->validUserId($evaluation->rework_requested_by)
            ?: $evaluation->leader_id
            ?: $evaluation->evaluator_id
            ?: $this->adminUserId;

        $remarks = trim(implode("\n\n", array_filter([
            $evaluation->total_score !== null ? 'Total score: ' . $evaluation->total_score : null,
            $this->cleanText($evaluation->general_observations),
            $this->cleanText($evaluation->rework_comment),
        ])));

        $this->upsert('site_visit_approvals', ['id' => $this->uuid('approval', $siteVisitId)], [
            'id' => $this->uuid('approval', $siteVisitId),
            'site_visit_id' => $siteVisitId,
            'reviewer_id' => $reviewerId,
            'status' => 'approved',
            'remarks' => $remarks ?: 'Site visit evaluation approved.',
        ]);
    }

    private function observationSections(): array
    {
        return [
            [
                'key' => 'organizational_capacity',
                'label' => 'Organizational Capacity',
                'score_fields' => ['s1_1_score', 's1_2_score', 's1_3_score', 's1_4_score'],
                'fields' => ['s1_1_strength', 's1_1_weakness', 's1_2_strength', 's1_2_weakness', 's1_3_strength', 's1_3_weakness', 's1_4_strength', 's1_4_weakness', 's1_comments'],
            ],
            [
                'key' => 'technical_capability',
                'label' => 'Technical Capability',
                'score_fields' => ['s2_1_score', 's2_2_score', 's2_3_score'],
                'fields' => ['s2_1_strength', 's2_1_weakness', 's2_2_strength', 's2_2_weakness', 's2_3_strength', 's2_3_weakness', 's2_comments'],
            ],
            [
                'key' => 'partnerships_collaboration',
                'label' => 'Partnerships and Collaboration',
                'score_fields' => ['s3_1_score', 's3_2_score', 's3_3_score'],
                'fields' => ['s3_1_strength', 's3_1_weakness', 's3_2_strength', 's3_2_weakness', 's3_3_strength', 's3_3_weakness', 's3_comments'],
            ],
            [
                'key' => 'innovation_impact',
                'label' => 'Innovation and Impact',
                'score_fields' => ['s4_1_score', 's4_2_score', 's4_3_score'],
                'fields' => ['s4_1_strength', 's4_1_weakness', 's4_2_strength', 's4_2_weakness', 's4_3_strength', 's4_3_weakness', 's4_comments'],
            ],
            [
                'key' => 'sustainability',
                'label' => 'Sustainability',
                'score_fields' => ['s5_1_score', 's5_2_score', 's5_3_score'],
                'fields' => ['s5_1_strength', 's5_1_weakness', 's5_2_strength', 's5_2_weakness', 's5_3_strength', 's5_3_weakness', 's5_comments'],
            ],
            [
                'key' => 'facility_resource_adequacy',
                'label' => 'Facility and Resource Adequacy',
                'score_fields' => ['s6_1_score', 's6_2_score', 's6_3_score'],
                'fields' => ['s6_1_strength', 's6_1_weakness', 's6_2_strength', 's6_2_weakness', 's6_3_strength', 's6_3_weakness', 's6_comments'],
            ],
            [
                'key' => 'overall_assessment',
                'label' => 'Overall Assessment',
                'score_fields' => [],
                'fields' => ['general_observations', 'overall_strength', 'overall_weakness', 'additional_comments'],
            ],
        ];
    }

    private function sectionDescription(object $evaluation, array $section): ?string
    {
        $parts = [];
        $score = $this->scoreSummary($evaluation, $section['score_fields']);

        if ($score) {
            $parts[] = $score;
        }

        foreach ($section['fields'] as $field) {
            $value = $this->cleanText($evaluation->{$field} ?? null);
            if ($value) {
                $parts[] = $this->readableField($field) . ":\n" . $value;
            }
        }

        return $parts ? implode("\n\n", $parts) : null;
    }

    private function scoreSummary(object $evaluation, array $fields): ?string
    {
        if (! $fields) {
            return $evaluation->total_score !== null ? 'Total score: ' . $evaluation->total_score : null;
        }

        $scores = [];
        foreach ($fields as $field) {
            if ($evaluation->{$field} !== null) {
                $scores[] = $this->readableField($field) . ': ' . $evaluation->{$field};
            }
        }

        return $scores ? 'Scores: ' . implode('; ', $scores) : null;
    }

    private function sectionSeverity(object $evaluation, array $section): string
    {
        if ($this->sectionNeedsAction($evaluation, $section)) {
            return 'high';
        }

        $scores = array_filter(
            array_map(fn (string $field): ?float => is_numeric($evaluation->{$field} ?? null) ? (float) $evaluation->{$field} : null, $section['score_fields']),
            fn (?float $score): bool => $score !== null
        );

        if ($scores && min($scores) < 1) {
            return 'medium';
        }

        return 'low';
    }

    private function sectionNeedsAction(object $evaluation, array $section): bool
    {
        foreach ($section['fields'] as $field) {
            if (! str_contains($field, 'weakness') && $field !== 'overall_weakness') {
                continue;
            }

            $value = $this->cleanText($evaluation->{$field} ?? null);
            if ($value && ! in_array(strtolower($value), ['na', 'n/a', 'none', 'null'], true)) {
                return true;
            }
        }

        return false;
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text) ?: null;
    }

    private function readableField(string $field): string
    {
        $field = preg_replace('/^s(\d+)_(\d+)_/', 'S$1.$2 ', $field);
        $field = str_replace('_', ' ', (string) $field);

        return ucfirst($field);
    }

    private function validUserId(mixed $userId): ?string
    {
        if (! is_string($userId) || ! preg_match('/^[0-9a-fA-F-]{36}$/', $userId)) {
            return null;
        }

        return DB::table('users')->where('id', strtolower($userId))->exists() ? strtolower($userId) : null;
    }

    private function resolveAdminUserId(): ?string
    {
        return DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.name', 'System Admin')
            ->value('users.id')
            ?: DB::table('users')->orderBy('created_at')->value('id');
    }

    private function upsert(string $table, array $keys, array $row): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = $this->columns[$table] ??= array_flip(Schema::getColumnListing($table));
        $row = array_intersect_key($row, $columns);
        $keys = array_intersect_key($keys, $columns);

        if (! $row || ! $keys) {
            return;
        }

        DB::table($table)->updateOrInsert($keys, $row);
        $this->counts[$table] = ($this->counts[$table] ?? 0) + 1;
    }

    private function uuid(string $scope, mixed $key): string
    {
        $hash = md5('attp-legacy-site-visit|' . $scope . '|' . $key);

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
