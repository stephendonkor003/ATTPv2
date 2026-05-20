<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyThinkTankProcurementLifecycleSeeder extends Seeder
{
    private const PROCUREMENT_ID = '3ed5b12a-8895-4cd3-a2fa-4e74bb147141';
    private const APPLICATION_FORM_ID = '1b0e7d45-a01c-4a4f-9a50-4f3d7c2cb7b1';
    private const EVALUATION_ID = '8b1722e4-b420-4df8-bd83-25bd6f9400b0';
    private const PRESCREENING_TEMPLATE_ID = '7f4a954b-c3ab-45b9-9c3c-34fbd6522d57';

    private array $columns = [];

    private array $counts = [];

    private ?string $adminUserId = null;

    private array $prescreeningByApplicant = [];

    private array $evaluationCountsByApplicant = [];

    private array $siteVisitApplicantIds = [];

    private array $submitterByApplicant = [];

    private array $formSubmissionByApplicant = [];

    public function run(): void
    {
        if (! Schema::hasTable('applicants') || ! Schema::hasTable('form_submissions')) {
            $this->command?->warn('Legacy procurement lifecycle import skipped because required tables are missing.');
            return;
        }

        $applicants = DB::table('applicants')
            ->whereNotNull('code')
            ->where('code', 'like', 'AUC-TK-2-%')
            ->orderBy('created_at')
            ->get();

        if ($applicants->isEmpty()) {
            $this->command?->warn('Legacy procurement lifecycle import skipped because no imported olddata applicants were found.');
            return;
        }

        $this->adminUserId = $this->resolveAdminUserId();
        $this->primeLookups();

        DB::transaction(function () use ($applicants): void {
            $this->seedProcurement($applicants);
            $this->seedApplicationForm();
            $this->seedPrescreeningTemplate();
            $this->seedEvaluationTemplate();
            $this->seedSubmissions($applicants);
            $this->seedPrescreeningLifecycle($applicants);
            $this->seedEvaluationLifecycle();
            $this->seedLifecycleAuditLog();
        });

        $rankedApplicants = DB::table('evaluation_submissions')
            ->where('procurement_id', self::PROCUREMENT_ID)
            ->whereNotNull('submitted_at')
            ->distinct('form_submission_id')
            ->count('form_submission_id');

        $this->command?->info("Legacy think tank procurement lifecycle completed with {$rankedApplicants} ranked applicant(s).");

        foreach ($this->counts as $table => $count) {
            $this->command?->line(" - {$table}: {$count}");
        }
    }

    private function primeLookups(): void
    {
        $this->prescreeningByApplicant = DB::table('prescreening_criteria')
            ->whereNotNull('applicant_id')
            ->get()
            ->keyBy('applicant_id')
            ->all();

        $this->evaluationCountsByApplicant = DB::table('evaluations')
            ->where('type', 'legacy_application_evaluation')
            ->whereNotNull('applicant_id')
            ->where('status', 'submitted')
            ->select('applicant_id', DB::raw('count(*) as total'))
            ->groupBy('applicant_id')
            ->pluck('total', 'applicant_id')
            ->all();

        $this->siteVisitApplicantIds = DB::table('site_visit_evaluations')
            ->whereNotNull('consortium_id')
            ->pluck('consortium_id')
            ->unique()
            ->flip()
            ->all();
    }

    private function seedProcurement($applicants): void
    {
        $resourceId = DB::table('myb_resources')
            ->whereRaw('lower(name) = ?', ['funding to think tanks'])
            ->value('id');

        $startDate = $this->dateFrom($applicants->min('created_at')) ?? '2025-08-19';
        $endDate = $this->dateFrom($applicants->max('created_at')) ?? '2025-09-24';
        $duration = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

        $this->upsert('procurements', ['id' => self::PROCUREMENT_ID], [
            'id' => self::PROCUREMENT_ID,
            'resource_id' => $resourceId,
            'title' => 'ATTP Think Tank Call for Applications 2025',
            'slug' => 'attp-think-tank-call-for-applications-2025',
            'reference_no' => 'AUC-TK-2-CALL-2025',
            'description' => implode("\n\n", [
                'Historical procurement record reconstructed from the first ATTP think tank call for applications.',
                'The lifecycle contains application intake, prescreening, technical evaluation, financial review, site visit evidence and objective ranking from the legacy scores in olddata.sql.',
            ]),
            'fiscal_year' => 2025,
            'application_start_date' => $startDate,
            'application_end_date' => $endDate,
            'application_duration_days' => $duration,
            'estimated_budget' => 24500000,
            'status' => 'closed',
            'visibility_type' => 'public',
            'vendor_categories' => null,
            'created_by' => $this->adminUserId,
            'created_at' => $this->timestampFrom($applicants->min('created_at')) ?? now(),
            'updated_at' => now(),
        ]);
    }

    private function seedApplicationForm(): void
    {
        $this->upsert('dynamic_forms', ['id' => self::APPLICATION_FORM_ID], [
            'id' => self::APPLICATION_FORM_ID,
            'resource_id' => DB::table('myb_resources')->whereRaw('lower(name) = ?', ['funding to think tanks'])->value('id'),
            'name' => 'ATTP Think Tank Call Application Form',
            'applies_to' => 'application',
            'status' => 'approved',
            'is_active' => true,
            'created_by' => $this->adminUserId,
            'procurement_id' => self::PROCUREMENT_ID,
            'submitted_at' => '2025-08-19 00:00:00',
            'approved_at' => '2025-08-19 00:00:00',
            'approved_by' => $this->adminUserId,
            'rejection_reason' => null,
            'created_at' => '2025-08-19 00:00:00',
            'updated_at' => now(),
        ]);

        foreach ($this->applicationFields() as $index => $field) {
            $this->upsert('dynamic_form_fields', ['id' => $this->uuid('application-field', $field['field_key'])], [
                'id' => $this->uuid('application-field', $field['field_key']),
                'form_id' => self::APPLICATION_FORM_ID,
                'label' => $field['label'],
                'field_key' => $field['field_key'],
                'field_type' => $field['field_type'],
                'is_required' => $field['required'] ?? false,
                'options' => $field['options'] ?? null,
                'sort_order' => (string) $index,
                'created_by' => $this->adminUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedPrescreeningTemplate(): void
    {
        $this->upsert('prescreening_templates', ['id' => self::PRESCREENING_TEMPLATE_ID], [
            'id' => self::PRESCREENING_TEMPLATE_ID,
            'name' => 'Legacy Think Tank Eligibility and Selection Prescreening',
            'description' => 'Prescreening criteria reconstructed from the old think tank call for applications.',
            'is_active' => true,
            'created_by' => $this->adminUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->upsert('prescreening_template_procurements', [
            'id' => $this->uuid('prescreening-template-procurement', self::PROCUREMENT_ID),
        ], [
            'id' => $this->uuid('prescreening-template-procurement', self::PROCUREMENT_ID),
            'procurement_id' => self::PROCUREMENT_ID,
            'prescreening_template_id' => self::PRESCREENING_TEMPLATE_ID,
            'assigned_by' => $this->adminUserId,
            'assigned_at' => now(),
        ]);

        foreach ($this->prescreeningChecks() as $index => $check) {
            $this->upsert('prescreening_criteria', ['id' => $this->uuid('prescreening-criterion', $check['field'])], [
                'id' => $this->uuid('prescreening-criterion', $check['field']),
                'prescreening_template_id' => self::PRESCREENING_TEMPLATE_ID,
                'name' => $check['label'],
                'description' => $check['description'],
                'field_key' => $check['field'],
                'evaluation_type' => 'yes_no',
                'min_value' => null,
                'is_mandatory' => true,
                'sort_order' => (string) ($index + 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedEvaluationTemplate(): void
    {
        $this->upsert('evaluations', ['id' => self::EVALUATION_ID], [
            'id' => self::EVALUATION_ID,
            'name' => 'ATTP Think Tank Technical Evaluation',
            'description' => 'Technical evaluation template reconstructed from the old think tank application scoring sheet.',
            'status' => 'active',
            'type' => 'services',
            'created_by' => $this->adminUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->evaluationSections() as $sectionIndex => $section) {
            $sectionId = $this->sectionId($section['key']);
            $this->upsert('evaluation_sections', ['id' => $sectionId], [
                'id' => $sectionId,
                'evaluation_id' => self::EVALUATION_ID,
                'name' => $section['name'],
                'description' => $section['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($section['criteria'] as $criteriaIndex => $criterion) {
                $this->upsert('evaluation_criteria', ['id' => $this->criteriaId($criterion['score'])], [
                    'id' => $this->criteriaId($criterion['score']),
                    'evaluation_section_id' => $sectionId,
                    'name' => $criterion['label'],
                    'description' => 'Legacy score column: ' . $criterion['score'],
                    'max_score' => $criterion['max'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedSubmissions($applicants): void
    {
        foreach ($applicants as $applicant) {
            $submissionId = $this->submissionId($applicant->id);
            $submitterId = $this->seedSubmitter($applicant);

            $this->submitterByApplicant[$applicant->id] = $submitterId;
            $this->formSubmissionByApplicant[$applicant->id] = $submissionId;

            $this->upsert('form_submissions', ['id' => $submissionId], [
                'id' => $submissionId,
                'procurement_id' => self::PROCUREMENT_ID,
                'procurement_submission_code' => $applicant->code,
                'form_id' => self::APPLICATION_FORM_ID,
                'submitted_by' => $submitterId,
                'status' => $this->submissionStatus($applicant),
                'submitted_at' => $this->timestampFrom($applicant->created_at) ?? now(),
                'created_at' => $this->timestampFrom($applicant->created_at) ?? now(),
                'updated_at' => $this->timestampFrom($applicant->updated_at) ?? now(),
            ]);

            foreach ($this->applicationFields() as $field) {
                $value = $this->applicationValue($applicant, $field['field_key']);

                $this->upsert('form_submission_values', [
                    'id' => $this->uuid('submission-value', $submissionId . '|' . $field['field_key']),
                ], [
                    'id' => $this->uuid('submission-value', $submissionId . '|' . $field['field_key']),
                    'submission_id' => $submissionId,
                    'field_key' => $field['field_key'],
                    'value' => $value,
                    'created_at' => $this->timestampFrom($applicant->created_at) ?? now(),
                    'updated_at' => $this->timestampFrom($applicant->updated_at) ?? now(),
                ]);
            }
        }
    }

    private function seedPrescreeningLifecycle($applicants): void
    {
        $applicantMap = $applicants->keyBy('id');

        foreach ($this->prescreeningByApplicant as $applicantId => $prescreening) {
            if (! isset($this->formSubmissionByApplicant[$applicantId])) {
                continue;
            }

            $submissionId = $this->formSubmissionByApplicant[$applicantId];
            $applicant = $applicantMap->get($applicantId);
            $passed = 0;
            $failed = 0;
            $failedNotes = [];

            foreach ($this->prescreeningChecks() as $check) {
                $value = $prescreening->{$check['field']} ?? null;
                $pass = strtolower((string) $value) === 'yes';
                $remark = $this->cleanText($prescreening->{$check['gap']} ?? null);

                $pass ? $passed++ : $failed++;
                if (! $pass) {
                    $failedNotes[] = trim($check['label'] . ($remark ? ': ' . $remark : ''));
                }

                $this->upsert('prescreening_evaluations', [
                    'id' => $this->uuid('prescreening-evaluation', $submissionId . '|' . $check['field']),
                ], [
                    'id' => $this->uuid('prescreening-evaluation', $submissionId . '|' . $check['field']),
                    'submission_id' => $submissionId,
                    'prescreening_template_id' => self::PRESCREENING_TEMPLATE_ID,
                    'criterion_id' => $this->uuid('prescreening-criterion', $check['field']),
                    'evaluator_id' => $prescreening->evaluator_id,
                    'evaluation_value' => $value,
                    'is_passed' => $pass,
                    'remarks' => $remark,
                    'evaluated_at' => $this->timestampFrom($prescreening->updated_at) ?? $this->timestampFrom($prescreening->created_at),
                ]);
            }

            $finalStatus = strtolower((string) $prescreening->eligible) === 'yes' && $failed === 0 ? 'passed' : 'failed';

            $this->upsert('prescreening_results', ['id' => $this->uuid('prescreening-result', $submissionId)], [
                'id' => $this->uuid('prescreening-result', $submissionId),
                'submission_id' => $submissionId,
                'prescreening_template_id' => self::PRESCREENING_TEMPLATE_ID,
                'total_criteria' => count($this->prescreeningChecks()),
                'passed_criteria' => (string) $passed,
                'failed_criteria' => (string) $failed,
                'final_status' => $finalStatus,
                'evaluated_by' => $prescreening->evaluator_id,
                'evaluated_at' => $this->timestampFrom($prescreening->updated_at) ?? $this->timestampFrom($prescreening->created_at),
                'is_locked' => true,
            ]);

            $this->upsert('procurement_submission_screenings', ['submission_id' => $submissionId], [
                'id' => $this->uuid('submission-screening', $submissionId),
                'submission_id' => $submissionId,
                'provider' => 'legacy_prescreening',
                'checked_by' => $prescreening->evaluator_id,
                'reviewed_by' => $prescreening->evaluator_id,
                'checked_via' => 'olddata.sql',
                'request_status' => 'success',
                'review_decision' => $finalStatus === 'passed' ? 'fit' : 'not_fit',
                'entity_name' => $applicant?->consortium_name ?: $applicant?->think_tank_name,
                'entity_country' => $applicant?->country,
                'risk_level' => $finalStatus === 'passed' ? 'clear' : 'high',
                'total_matches' => 0,
                'is_flagged' => false,
                'review_notes' => $failedNotes
                    ? 'Legacy prescreening failed checks: ' . implode('; ', array_slice($failedNotes, 0, 8))
                    : 'Legacy prescreening decision: eligible.',
                'last_checked_at' => $this->timestampFrom($prescreening->updated_at) ?? $this->timestampFrom($prescreening->created_at),
                'reviewed_at' => $this->timestampFrom($prescreening->updated_at) ?? $this->timestampFrom($prescreening->created_at),
                'response_payload' => json_encode([
                    'source' => 'database/seeders/olddata.sql',
                    'eligible' => $prescreening->eligible,
                    'failed_checks' => $failedNotes,
                ], JSON_UNESCAPED_SLASHES),
                'created_at' => $this->timestampFrom($prescreening->created_at) ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedEvaluationLifecycle(): void
    {
        $legacyAssignments = DB::table('assignments')
            ->whereNotNull('applicant_id')
            ->whereNotNull('evaluator_id')
            ->get();

        foreach ($legacyAssignments as $assignment) {
            if (! isset($this->formSubmissionByApplicant[$assignment->applicant_id])) {
                continue;
            }

            $this->upsertEvaluationAssignment(
                $assignment->applicant_id,
                $assignment->evaluator_id,
                $this->timestampFrom($assignment->created_at) ?? now(),
                'assigned'
            );
        }

        $legacyEvaluations = DB::table('evaluations')
            ->where('type', 'legacy_application_evaluation')
            ->whereNotNull('applicant_id')
            ->whereNotNull('evaluator_id')
            ->orderBy('created_at')
            ->get();

        foreach ($legacyEvaluations as $legacyEvaluation) {
            if (! isset($this->formSubmissionByApplicant[$legacyEvaluation->applicant_id])) {
                continue;
            }

            $submitted = $legacyEvaluation->status === 'submitted';
            $this->upsertEvaluationAssignment(
                $legacyEvaluation->applicant_id,
                $legacyEvaluation->evaluator_id,
                $this->timestampFrom($legacyEvaluation->created_at) ?? now(),
                $submitted ? 'submitted' : 'assigned'
            );

            $submissionId = $this->uuid('evaluation-submission', $legacyEvaluation->id);
            $formSubmissionId = $this->formSubmissionByApplicant[$legacyEvaluation->applicant_id];

            $this->upsert('evaluation_submissions', ['id' => $submissionId], [
                'id' => $submissionId,
                'evaluation_id' => self::EVALUATION_ID,
                'procurement_id' => self::PROCUREMENT_ID,
                'evaluator_id' => $legacyEvaluation->evaluator_id,
                'form_submission_id' => $formSubmissionId,
                'overall_score' => $legacyEvaluation->total_score,
                'comments' => $this->cleanText($legacyEvaluation->overall_comments),
                'video_path' => $legacyEvaluation->video_path,
                'video_duration' => null,
                'submitted_at' => $submitted
                    ? ($this->timestampFrom($legacyEvaluation->updated_at) ?? $this->timestampFrom($legacyEvaluation->created_at))
                    : null,
                'created_at' => $this->timestampFrom($legacyEvaluation->created_at) ?? now(),
                'updated_at' => $this->timestampFrom($legacyEvaluation->updated_at) ?? now(),
            ]);

            foreach ($this->evaluationSections() as $section) {
                $sectionScore = 0;
                $strengths = [];
                $weaknesses = [];

                foreach ($section['criteria'] as $criterion) {
                    $score = $legacyEvaluation->{$criterion['score']} ?? null;
                    if ($score !== null) {
                        $sectionScore += (float) $score;
                    }

                    $strength = $this->cleanText($legacyEvaluation->{$criterion['strength']} ?? null);
                    $gap = $this->cleanText($legacyEvaluation->{$criterion['gap']} ?? null);

                    if ($strength !== null && $strength !== '') {
                        $strengths[] = $criterion['label'] . ': ' . $strength;
                    }
                    if ($gap !== null && $gap !== '') {
                        $weaknesses[] = $criterion['label'] . ': ' . $gap;
                    }

                    $this->upsert('evaluation_criteria_scores', [
                        'submission_id' => $submissionId,
                        'evaluation_criteria_id' => $this->criteriaId($criterion['score']),
                    ], [
                        'id' => $this->uuid('criteria-score', $submissionId . '|' . $criterion['score']),
                        'submission_id' => $submissionId,
                        'evaluation_criteria_id' => $this->criteriaId($criterion['score']),
                        'score' => $score,
                        'decision' => null,
                        'comment' => $this->scoreComment($strength, $gap),
                        'created_at' => $this->timestampFrom($legacyEvaluation->created_at) ?? now(),
                        'updated_at' => $this->timestampFrom($legacyEvaluation->updated_at) ?? now(),
                    ]);
                }

                $this->upsert('evaluation_section_scores', [
                    'submission_id' => $submissionId,
                    'evaluation_section_id' => $this->sectionId($section['key']),
                ], [
                    'id' => $this->uuid('section-score', $submissionId . '|' . $section['key']),
                    'submission_id' => $submissionId,
                    'evaluation_section_id' => $this->sectionId($section['key']),
                    'section_score' => $sectionScore,
                    'strengths' => $strengths ? implode("\n\n", $strengths) : null,
                    'weaknesses' => $weaknesses ? implode("\n\n", $weaknesses) : null,
                    'created_at' => $this->timestampFrom($legacyEvaluation->created_at) ?? now(),
                    'updated_at' => $this->timestampFrom($legacyEvaluation->updated_at) ?? now(),
                ]);
            }
        }
    }

    private function upsertEvaluationAssignment(string $applicantId, string $evaluatorId, mixed $assignedAt, string $status): void
    {
        $submissionId = $this->formSubmissionByApplicant[$applicantId] ?? null;
        if (! $submissionId) {
            return;
        }

        $this->upsert('evaluation_assignments', [
            'evaluation_id' => self::EVALUATION_ID,
            'procurement_id' => self::PROCUREMENT_ID,
            'form_submission_id' => $submissionId,
            'user_id' => $evaluatorId,
        ], [
            'id' => $this->uuid('evaluation-assignment', $submissionId . '|' . $evaluatorId),
            'evaluation_id' => self::EVALUATION_ID,
            'procurement_id' => self::PROCUREMENT_ID,
            'form_submission_id' => $submissionId,
            'user_id' => $evaluatorId,
            'assigned_by' => $this->adminUserId,
            'assigned_at' => $assignedAt,
            'status' => $status,
            'created_at' => $assignedAt,
            'updated_at' => now(),
        ]);
    }

    private function seedLifecycleAuditLog(): void
    {
        $rankings = DB::table('evaluation_submissions')
            ->join('form_submissions', 'form_submissions.id', '=', 'evaluation_submissions.form_submission_id')
            ->where('evaluation_submissions.procurement_id', self::PROCUREMENT_ID)
            ->whereNotNull('evaluation_submissions.submitted_at')
            ->select(
                'form_submissions.id',
                'form_submissions.procurement_submission_code',
                DB::raw('avg(evaluation_submissions.overall_score) as average_score')
            )
            ->groupBy('form_submissions.id', 'form_submissions.procurement_submission_code')
            ->orderByDesc('average_score')
            ->limit(5)
            ->get();

        $this->upsert('procurement_audit_logs', ['id' => $this->uuid('lifecycle-audit', self::PROCUREMENT_ID)], [
            'id' => $this->uuid('lifecycle-audit', self::PROCUREMENT_ID),
            'user_id' => $this->adminUserId,
            'action' => 'Imported legacy think tank procurement lifecycle',
            'procurement_id' => self::PROCUREMENT_ID,
            'form_id' => self::APPLICATION_FORM_ID,
            'submission_id' => null,
            'metadata' => json_encode([
                'source' => 'database/seeders/olddata.sql',
                'submissions' => DB::table('form_submissions')->where('procurement_id', self::PROCUREMENT_ID)->count(),
                'submitted_evaluations' => DB::table('evaluation_submissions')
                    ->where('procurement_id', self::PROCUREMENT_ID)
                    ->whereNotNull('submitted_at')
                    ->count(),
                'top_ranked' => $rankings->toArray(),
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }

    private function seedSubmitter(object $applicant): string
    {
        $email = $applicant->email ?: strtolower($applicant->code) . '@legacy-attp.local';
        $existingId = DB::table('users')->whereRaw('lower(email) = ?', [strtolower($email)])->value('id');

        if ($existingId) {
            return $existingId;
        }

        $userId = $this->uuid('applicant-submitter', $applicant->id);

        $this->upsert('users', ['id' => $userId], [
            'id' => $userId,
            'name' => $applicant->consortium_name ?: $applicant->think_tank_name,
            'email' => $email,
            'password' => null,
            'user_type' => 'vendor',
            'vendor_category' => 'Think Tank / Consortium',
            'must_change_password' => false,
            'email_verified_at' => $this->timestampFrom($applicant->created_at),
            'is_disabled' => false,
            'is_blacklisted' => false,
            'created_at' => $this->timestampFrom($applicant->created_at) ?? now(),
            'updated_at' => $this->timestampFrom($applicant->updated_at) ?? now(),
        ]);

        return $userId;
    }

    private function submissionStatus(object $applicant): string
    {
        $prescreening = $this->prescreeningByApplicant[$applicant->id] ?? null;
        $eligible = $prescreening ? strtolower((string) $prescreening->eligible) === 'yes' : null;

        if ($eligible === false) {
            return 'prescreen_failed';
        }

        if (isset($this->siteVisitApplicantIds[$applicant->id])) {
            return 'site_visit_completed';
        }

        if (($this->evaluationCountsByApplicant[$applicant->id] ?? 0) > 0) {
            return 'evaluated';
        }

        if ($eligible === true) {
            return 'prescreen_passed';
        }

        return 'submitted';
    }

    private function applicationFields(): array
    {
        return [
            ['field_key' => 'official_name', 'label' => 'Official Name', 'field_type' => 'text', 'required' => true],
            ['field_key' => 'official_email', 'label' => 'Official Email', 'field_type' => 'email', 'required' => true],
            ['field_key' => 'application_code', 'label' => 'Application Code', 'field_type' => 'text', 'required' => true],
            ['field_key' => 'think_tank_name', 'label' => 'Think Tank Name', 'field_type' => 'text', 'required' => true],
            ['field_key' => 'country', 'label' => 'Country', 'field_type' => 'text', 'required' => true],
            ['field_key' => 'sub_region', 'label' => 'Sub Region(s)', 'field_type' => 'checkbox'],
            ['field_key' => 'focus_areas', 'label' => 'Priority Focus Areas', 'field_type' => 'checkbox'],
            ['field_key' => 'is_partnership', 'label' => 'Partnership Application', 'field_type' => 'text'],
            ['field_key' => 'consortium_name', 'label' => 'Consortium Name', 'field_type' => 'textarea'],
            ['field_key' => 'members_names', 'label' => 'Consortium Members', 'field_type' => 'textarea'],
            ['field_key' => 'lead_think_tank_name', 'label' => 'Lead Think Tank Name', 'field_type' => 'text'],
            ['field_key' => 'lead_think_tank_country', 'label' => 'Lead Think Tank Country', 'field_type' => 'text'],
            ['field_key' => 'consortium_region', 'label' => 'Consortium Region', 'field_type' => 'text'],
            ['field_key' => 'covered_countries', 'label' => 'Covered Countries', 'field_type' => 'checkbox'],
            ['field_key' => 'application_form', 'label' => 'Application Form', 'field_type' => 'file'],
            ['field_key' => 'legal_registration', 'label' => 'Legal Registration', 'field_type' => 'file'],
            ['field_key' => 'trustees_formation', 'label' => 'Trustees / Board Formation', 'field_type' => 'file'],
            ['field_key' => 'audited_reports', 'label' => 'Audited Reports', 'field_type' => 'file'],
            ['field_key' => 'commitment_letter', 'label' => 'Commitment Letter', 'field_type' => 'file'],
            ['field_key' => 'work_plan_budget', 'label' => 'Work Plan and Budget', 'field_type' => 'file'],
            ['field_key' => 'cv_coordinator', 'label' => 'Coordinator CV', 'field_type' => 'file'],
            ['field_key' => 'cv_deputy', 'label' => 'Deputy Coordinator CV', 'field_type' => 'file'],
            ['field_key' => 'cv_team_members', 'label' => 'Team Member CVs', 'field_type' => 'file'],
            ['field_key' => 'past_research', 'label' => 'Past Research Evidence', 'field_type' => 'file'],
        ];
    }

    private function applicationValue(object $applicant, string $fieldKey): ?string
    {
        return match ($fieldKey) {
            'official_name' => $applicant->consortium_name ?: $applicant->think_tank_name,
            'official_email' => $applicant->email,
            'application_code' => $applicant->code,
            'is_partnership' => $applicant->is_partnership ? 'Yes' : 'No',
            default => isset($applicant->{$fieldKey}) ? (string) $applicant->{$fieldKey} : null,
        };
    }

    private function prescreeningChecks(): array
    {
        return [
            ['field' => 'eligibility_lead_thinktank', 'gap' => 'gap_lead_thinktank', 'label' => 'Lead think tank eligibility', 'description' => 'Lead applicant is an eligible African think tank.'],
            ['field' => 'eligibility_sub_regions', 'gap' => 'gap_sub_regions', 'label' => 'Sub-region eligibility', 'description' => 'Application covers the required African sub-regional spread.'],
            ['field' => 'eligibility_priority_themes', 'gap' => 'gap_priority_themes', 'label' => 'Priority themes eligibility', 'description' => 'Application aligns with ATTP priority research themes.'],
            ['field' => 'selection_application_form', 'gap' => 'gap_application_form', 'label' => 'Application form submitted', 'description' => 'Required application form was submitted.'],
            ['field' => 'selection_workplan_budget', 'gap' => 'gap_workplan_budget', 'label' => 'Work plan and budget submitted', 'description' => 'Work plan and budget attachment was submitted.'],
            ['field' => 'selection_coordinator_cv', 'gap' => 'gap_coordinator_cv', 'label' => 'Coordinator CV submitted', 'description' => 'Coordinator CV was submitted.'],
            ['field' => 'selection_deputy_cv', 'gap' => 'gap_deputy_cv', 'label' => 'Deputy coordinator CV submitted', 'description' => 'Deputy coordinator CV was submitted.'],
            ['field' => 'selection_team_cvs', 'gap' => 'gap_team_cvs', 'label' => 'Team CVs submitted', 'description' => 'Team member CVs were submitted.'],
            ['field' => 'selection_experience', 'gap' => 'gap_experience', 'label' => 'Experience evidence submitted', 'description' => 'Past experience or research evidence was submitted.'],
            ['field' => 'selection_commitment_letter', 'gap' => 'gap_commitment_letter', 'label' => 'Commitment letter submitted', 'description' => 'Commitment letter was submitted.'],
            ['field' => 'selection_registration_copy', 'gap' => 'gap_registration_copy', 'label' => 'Registration copy submitted', 'description' => 'Legal registration copy was submitted.'],
            ['field' => 'selection_board_structure', 'gap' => 'gap_board_structure', 'label' => 'Board structure submitted', 'description' => 'Trustees or board structure documentation was submitted.'],
            ['field' => 'selection_audited_reports', 'gap' => 'gap_audited_reports', 'label' => 'Audited reports submitted', 'description' => 'Audited financial reports were submitted.'],
        ];
    }

    private function evaluationSections(): array
    {
        return [
            [
                'key' => 'research_relevance',
                'name' => 'Research Relevance and Policy Value',
                'description' => 'Alignment, cross-border relevance, originality and policy impact.',
                'criteria' => [
                    $this->criterion('relevance_score', 'Relevance to ATTP priorities', 10, 'strength_relevance', 'gap_relevance'),
                    $this->criterion('cross_border_score', 'Cross-border relevance', 10, 'strength_cross_border', 'gap_cross_border'),
                    $this->criterion('quality_originality_score', 'Research quality and originality', 5, 'strength_quality_originality', 'gap_quality_originality'),
                    $this->criterion('policy_impact_score', 'Policy impact and uptake potential', 5, 'strength_policy_impact', 'gap_policy_impact'),
                ],
            ],
            [
                'key' => 'capacity_gender_consortium',
                'name' => 'Capacity, Gender and Consortium Strength',
                'description' => 'Institutional capacity building, gender representation and consortium quality.',
                'criteria' => [
                    $this->criterion('capacity_building_sustainability_score', 'Capacity building and sustainability', 5, 'strength_capacity_building_sustainability', 'gap_capacity_building_sustainability'),
                    $this->criterion('female_representation_score', 'Female representation', 5, 'strength_female_representation', 'gap_female_representation'),
                    $this->criterion('consortia_quality_score', 'Consortium quality', 5, 'strength_consortia_quality', 'gap_consortia_quality'),
                ],
            ],
            [
                'key' => 'leadership_personnel',
                'name' => 'Leadership and Key Personnel',
                'description' => 'Coordinator, deputy, key personnel and technical capacity.',
                'criteria' => [
                    $this->criterion('coordinator_score', 'Coordinator and deputy coordinator', 10, 'strength_coordinator', 'gap_coordinator'),
                    $this->criterion('key_personnel_score', 'Key personnel', 5, 'strength_key_personnel', 'gap_key_personnel'),
                    $this->criterion('technical_capacity_score', 'Technical capacity', 5, 'strength_technical_capacity', 'gap_technical_capacity'),
                ],
            ],
            [
                'key' => 'fiduciary_systems',
                'name' => 'Fiduciary and Implementation Systems',
                'description' => 'Monitoring, financial management and procurement knowledge.',
                'criteria' => [
                    $this->criterion('monitoring_evaluation_score', 'Monitoring and evaluation systems', 5, 'strength_monitoring_evaluation', 'gap_monitoring_evaluation'),
                    $this->criterion('financial_management_score', 'Financial management systems', 5, 'strength_financial_management', 'gap_financial_management'),
                    $this->criterion('procurement_knowledge_score', 'Procurement knowledge and systems', 5, 'strength_procurement_knowledge', 'gap_procurement_knowledge'),
                ],
            ],
            [
                'key' => 'budget_safeguards',
                'name' => 'Budget, Thresholds and Safeguards',
                'description' => 'Budget feasibility, budget limits and environmental/social safeguards.',
                'criteria' => [
                    $this->criterion('budget_feasibility_score', 'Budget feasibility', 10, 'strength_budget_feasibility', 'gap_budget_feasibility'),
                    $this->criterion('budget_limits_score', 'Budget limits and 30 percent rule', 10, 'strength_budget_limits', 'gap_budget_limits'),
                    $this->criterion('safeguards_score', 'Environmental and social safeguards', 5, 'strength_safeguards', 'gap_safeguards'),
                ],
            ],
        ];
    }

    private function criterion(string $score, string $label, int $max, string $strength, string $gap): array
    {
        return compact('score', 'label', 'max', 'strength', 'gap');
    }

    private function scoreComment(?string $strength, ?string $gap): ?string
    {
        $parts = [];
        if ($strength) {
            $parts[] = 'Strength: ' . $strength;
        }
        if ($gap) {
            $parts[] = 'Gap: ' . $gap;
        }

        return $parts ? implode("\n\n", $parts) : null;
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = (string) $value;
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\s*\/p\s*>/i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text) ?: null;
    }

    private function resolveAdminUserId(): ?string
    {
        return DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.name', 'System Admin')
            ->value('users.id')
            ?: DB::table('users')->orderBy('created_at')->value('id');
    }

    private function sectionId(string $key): string
    {
        return $this->uuid('evaluation-section', $key);
    }

    private function criteriaId(string $scoreColumn): string
    {
        return $this->uuid('evaluation-criterion', $scoreColumn);
    }

    private function submissionId(string $applicantId): string
    {
        return $this->uuid('form-submission', $applicantId);
    }

    private function dateFrom(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function timestampFrom(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
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
        $hash = md5('attp-legacy-procurement|' . $scope . '|' . $key);

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
