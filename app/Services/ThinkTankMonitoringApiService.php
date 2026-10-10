<?php

namespace App\Services;

use App\Exceptions\ThinkTankApiException;
use App\Models\MeDataEntryFormField;
use App\Models\MeDataSubmission;
use App\Models\MePerformanceReport;
use App\Notifications\MeReportingNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class ThinkTankMonitoringApiService
{
    /** @return array<string, mixed> */
    public function assignmentRegister(array $overview, string $state = 'all', string $query = ''): array
    {
        $groups = collect(['open', 'upcoming', 'submitted', 'closed'])
            ->mapWithKeys(function (string $group) use ($overview, $state, $query): array {
                $cards = collect($overview['groups']->get($group, collect()))
                    ->map(fn (array $card): array => $this->assignmentCard($card))
                    ->filter(fn (array $card): bool => $this->assignmentMatches($card, $state, $query))
                    ->values()
                    ->all();

                return [$group => $cards];
            })
            ->all();

        return [
            'summary' => $this->assignmentSummary($overview['summary']),
            'groups' => $groups,
            'assignments' => collect($groups)->flatten(1)->values()->all(),
            'filters' => ['state' => $state, 'query' => $query],
        ];
    }

    /** @return array<string, int> */
    public function assignmentSummary(array $summary): array
    {
        return [
            'total' => (int) ($summary['total'] ?? 0),
            'open' => (int) ($summary['open'] ?? 0),
            'upcoming' => (int) ($summary['upcoming'] ?? 0),
            'submitted' => (int) ($summary['submitted'] ?? 0),
            'closed' => (int) ($summary['closed'] ?? 0),
            'actionRequired' => (int) ($summary['action_required'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public function assignmentCard(array $card): array
    {
        $assignment = $card['assignment'];
        $form = $assignment->collection?->form;

        return [
            'id' => (string) $assignment->id,
            'state' => (string) $card['state'],
            'formTitle' => (string) $card['form_title'],
            'formCode' => (string) $card['form_code'],
            'description' => $card['description'] ?: null,
            'instructions' => $assignment->collection?->instructions ?: null,
            'indicatorId' => $card['indicator_id'] ? (string) $card['indicator_id'] : null,
            'indicatorCode' => $card['indicator_code'] ?: null,
            'indicatorName' => $card['indicator_name'] ?: null,
            'indicatorUnit' => $card['indicator_unit'] ?: null,
            'periodLabel' => (string) $card['period_label'],
            'periodStart' => $this->date($card['period_start']),
            'periodEnd' => $this->date($card['period_end']),
            'opensAt' => $this->dateTime($card['opens_at']),
            'dueAt' => $this->dateTime($card['due_at']),
            'closesAt' => $this->dateTime($card['closes_at']),
            'assignedAt' => $this->dateTime($assignment->assigned_at),
            'isOverdue' => (bool) $card['is_overdue'],
            'submissionStatus' => $card['submission_status'] ?: null,
            'submissionStatusLabel' => (string) $card['submission_status_label'],
            'progress' => [
                'answered' => (int) data_get($card, 'progress.answered', 0),
                'total' => (int) data_get($card, 'progress.total', 0),
                'percent' => (int) data_get($card, 'progress.percent', 0),
            ],
            'canEdit' => (bool) $card['can_edit'],
            'actionLabel' => (string) $card['action_label'],
        ];
    }

    /** @return array<string, mixed> */
    public function assignmentDetail(array $data): array
    {
        $assignment = $data['assignment'];
        $collection = $data['collection'];
        $form = $data['form'];
        $period = $data['period'];
        $submission = $data['submission'];
        $fieldOptions = collect($data['fieldOptions']);
        $uploadSettings = collect($data['fieldUploadSettings']);
        $attachments = collect($data['attachments']);

        $card = [
            'assignment' => $assignment,
            'state' => $data['assignmentState'],
            'form_title' => $form->title,
            'form_code' => $form->code,
            'description' => $form->description,
            'indicator_id' => $form->indicator?->id,
            'indicator_code' => $form->indicator?->indicator_code,
            'indicator_name' => $form->indicator?->name,
            'indicator_unit' => $form->indicator?->unit?->symbol ?: $form->indicator?->unit?->name,
            'period_label' => $period?->label ?: 'Reporting period',
            'period_start' => $period?->period_start,
            'period_end' => $period?->period_end,
            'opens_at' => $collection->opens_at,
            'due_at' => $collection->due_at,
            'closes_at' => $collection->closes_at,
            'is_overdue' => $collection->isPastDue() && $data['assignmentState'] === 'open',
            'submission_status' => $submission?->effectiveStatus(),
            'submission_status_label' => $this->submissionStatusLabel($submission?->effectiveStatus()),
            'progress' => $data['progress'],
            'can_edit' => (bool) $data['editable'],
            'action_label' => $data['editable'] ? 'Continue update' : 'View record',
        ];

        if ($submission) {
            $submission->loadMissing(['reviews.reviewer:id,name', 'dataQualityFindings']);
        }

        return [
            'assignment' => $this->assignmentCard($card),
            'collection' => [
                'instructions' => $collection->instructions ?: null,
                'status' => (string) $collection->status,
            ],
            'form' => [
                'id' => (string) $form->id,
                'code' => (string) $form->code,
                'title' => (string) $form->title,
                'description' => $form->description ?: null,
                'version' => (int) $form->version,
                'indicator' => $form->indicator ? [
                    'id' => (string) $form->indicator->id,
                    'code' => (string) $form->indicator->indicator_code,
                    'name' => (string) $form->indicator->name,
                    'definition' => $form->indicator->definitions ?: null,
                    'unit' => $form->indicator->unit?->symbol ?: $form->indicator->unit?->name,
                ] : null,
            ],
            'period' => [
                'id' => $period?->id ? (string) $period->id : null,
                'code' => $period?->code ?: null,
                'label' => $period?->label ?: 'Reporting period',
                'type' => $period?->period_type ?: null,
                'start' => $this->date($period?->period_start),
                'end' => $this->date($period?->period_end),
                'submissionDeadline' => $this->dateTime($period?->submission_deadline),
            ],
            'sections' => collect($data['formSections'])->map(function (array $section) use ($fieldOptions, $uploadSettings): array {
                return [
                    'id' => $section['id'] ? (string) $section['id'] : null,
                    'key' => (string) $section['key'],
                    'name' => (string) $section['name'],
                    'description' => $section['description'] !== '' ? $section['description'] : null,
                    'guidance' => (string) $section['guidance'],
                    'sortOrder' => (int) $section['sort_order'],
                    'palette' => $section['palette'],
                    'fields' => collect($section['fields'])->map(function (MeDataEntryFormField $field) use ($fieldOptions, $uploadSettings): array {
                        $settings = $uploadSettings->get((string) $field->id);

                        return [
                            'id' => (string) $field->id,
                            'key' => (string) $field->field_key,
                            'label' => (string) $field->label,
                            'helpText' => $field->help_text ?: null,
                            'type' => (string) $field->field_type,
                            'unitLabel' => $field->unit_label ?: null,
                            'required' => (bool) $field->is_required,
                            'sortOrder' => (int) $field->sort_order,
                            'options' => collect($fieldOptions->get((string) $field->id, []))->values()->all(),
                            'validation' => is_array($field->validation) ? $field->validation : [],
                            'upload' => is_array($settings) ? [
                                'allowedExtensions' => array_values($settings['allowed_extensions']),
                                'maxFileSizeMb' => (int) $settings['max_file_size_mb'],
                                'multiple' => (bool) $settings['multiple'],
                                'maxFiles' => (int) $settings['max_files'],
                            ] : null,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'answers' => collect($data['answerValues'])->mapWithKeys(
                fn (mixed $value, mixed $key): array => [(string) $key => $value]
            )->all(),
            'attachments' => $attachments->mapWithKeys(function ($files, $fieldId) use ($assignment): array {
                return [(string) $fieldId => collect($files)->map(fn (array $file): array => [
                    'id' => $this->attachmentId($file, (string) $assignment->id, (string) $fieldId),
                    'name' => (string) $file['original_name'],
                    'size' => isset($file['size']) ? (int) $file['size'] : null,
                    'mimeType' => $file['mime_type'] ?: null,
                    'downloadUrl' => route('api.v1.think-tank.monitoring.assignments.attachments.show', [
                        'assignment' => $assignment->id,
                        'attachment' => $this->attachmentId($file, (string) $assignment->id, (string) $fieldId),
                    ], false),
                ])->values()->all()];
            })->all(),
            'submission' => $submission ? [
                'id' => (string) $submission->id,
                'status' => $submission->effectiveStatus(),
                'statusLabel' => $this->submissionStatusLabel($submission->effectiveStatus()),
                'revision' => (int) $submission->revision,
                'currentVersion' => (int) ($submission->current_version ?: $submission->revision),
                'notes' => $submission->notes ?: null,
                'reviewNotes' => $submission->review_notes ?: null,
                'submittedAt' => $this->dateTime($submission->submitted_at),
                'reviewedAt' => $this->dateTime($submission->reviewed_at),
                'approvedAt' => $this->dateTime($submission->approved_at),
                'updatedAt' => $this->dateTime($submission->updated_at),
                'lockToken' => $this->lockToken($submission),
                'history' => $submission->reviews->map(fn ($review): array => [
                    'id' => (string) $review->id,
                    'version' => (int) $review->submission_version,
                    'fromStatus' => $review->from_status ?: null,
                    'toStatus' => (string) $review->to_status,
                    'action' => (string) $review->action,
                    'comments' => $review->comments ?: null,
                    'actor' => $review->reviewer?->name ?: null,
                    'occurredAt' => $this->dateTime($review->reviewed_at),
                ])->values()->all(),
                'qualityFindings' => $submission->dataQualityFindings->map(fn ($finding): array => [
                    'id' => (string) $finding->id,
                    'severity' => (string) $finding->severity,
                    'fieldKey' => $finding->field_key ?: null,
                    'message' => (string) $finding->message,
                    'status' => (string) $finding->status,
                ])->values()->all(),
            ] : null,
            'editable' => (bool) $data['editable'],
            'progress' => [
                'answered' => (int) $data['progress']['answered'],
                'total' => (int) $data['progress']['total'],
                'percent' => (int) $data['progress']['percent'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function resultsPayload(array $data, array $filters, Collection $periods, string $memberName): array
    {
        $summary = $data['summary'];
        $analytics = $data['analytics'];

        return [
            'framework' => $data['framework'] ? [
                'id' => (string) $data['framework']->id,
                'name' => (string) $data['framework']->title,
                'code' => $data['framework']->code ?: null,
            ] : null,
            'scopeLabel' => $this->resultsScopeLabel($data, $memberName),
            'summary' => [
                'indicatorCount' => (int) $summary['indicator_count'],
                'pdoCount' => (int) $summary['pdo_count'],
                'approvedResultCount' => (int) $summary['approved_result_count'],
                'evidenceCount' => (int) $summary['evidence_count'],
                'verifiedEvidenceCount' => (int) $summary['verified_evidence_count'],
                'averageAchievement' => $this->nullableFloat($summary['average_achievement']),
                'onTrackCount' => (int) $summary['on_track_count'],
                'attentionCount' => (int) $summary['attention_count'],
                'reportedIndicatorCount' => (int) $summary['reported_indicator_count'],
                'notReportedCount' => (int) $summary['not_reported_count'],
                'onTrackRate' => $this->nullableFloat($summary['on_track_rate']),
                'averageCompleteness' => (float) $summary['average_completeness'],
                'evidenceVerificationRate' => $this->nullableFloat($summary['evidence_verification_rate']),
            ],
            'analytics' => [
                'performance' => array_values($analytics['performance']),
                'components' => collect($analytics['components'])->map(fn (array $item): array => [
                    'key' => (string) $item['key'],
                    'label' => (string) $item['label'],
                    'shortLabel' => (string) $item['short_label'],
                    'indicatorCount' => (int) $item['indicator_count'],
                    'reportedCount' => (int) $item['reported_count'],
                    'averageAchievement' => $this->nullableFloat($item['average_achievement']),
                    'averageCompleteness' => (float) $item['average_completeness'],
                ])->values()->all(),
                'attainment' => array_values($analytics['attainment']),
                'gender' => $analytics['gender'],
                'trends' => $analytics['trends'],
                'attention' => array_values($analytics['attention']),
                'quality' => [
                    'reportingCompleteness' => (float) $analytics['quality']['reporting_completeness'],
                    'evidenceVerification' => $this->nullableFloat($analytics['quality']['evidence_verification']),
                ],
            ],
            'rows' => collect($data['rows'])->map(function (array $row): array {
                $indicator = $row['indicator'];

                return [
                    'id' => (string) $indicator->id,
                    'code' => (string) $indicator->indicator_code,
                    'name' => (string) $indicator->name,
                    'definition' => $indicator->definitions ?: null,
                    'resultsLevel' => $indicator->results_level ?: null,
                    'unit' => $indicator->unit?->symbol ?: $indicator->unit?->name,
                    'target' => $row['target_value'] ?? $row['target_text'] ?? null,
                    'periodActual' => $row['period_actual'],
                    'cumulativeActual' => $row['cumulative_actual'],
                    'achievementPercent' => $this->nullableFloat($row['achievement_percent']),
                    'variance' => $this->nullableFloat($row['variance']),
                    'trend' => $row['trend'],
                    'classification' => $row['classification'],
                    'evidenceCount' => (int) $row['evidence_count'],
                    'verifiedEvidenceCount' => (int) $row['verified_evidence_count'],
                    'reportingCompleteness' => (float) $row['reporting_completeness'],
                    'latestApprovedAt' => $this->dateTime($row['latest_approved_at']),
                ];
            })->values()->all(),
            'filters' => [
                'projectYear' => (int) $filters['project_year'],
                'reportingYear' => $filters['reporting_year'] ? (int) $filters['reporting_year'] : null,
                'reportingPeriodId' => $filters['reporting_period_id'] ?: null,
                'includeArchived' => (bool) ($filters['include_archived'] ?? false),
            ],
            'options' => [
                'projectYears' => collect($filters['project_year_options'] ?? [1, 2, 3, 4])
                    ->map(fn ($year): int => (int) $year)->unique()->sort()->values()->all(),
                'periods' => $periods->map(fn ($period): array => [
                    'id' => (string) $period->id,
                    'label' => (string) $period->label,
                    'reportingYear' => $period->reporting_year ? (int) $period->reporting_year : null,
                    'status' => $period->isActive() ? 'active' : 'historical',
                ])->values()->all(),
                'reportingYears' => $periods->pluck('reporting_year')->filter()->map(fn ($year): int => (int) $year)
                    ->unique()->sortDesc()->values()->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function reportRegister(array $viewData): array
    {
        /** @var LengthAwarePaginator $reports */
        $reports = $viewData['reports'];

        return [
            'summary' => collect($viewData['summary'])->map(fn ($count): int => (int) $count)->all(),
            'reports' => collect($reports->items())->map(fn (MePerformanceReport $report): array => $this->reportListItem($report))->all(),
            'availableAssignments' => ((bool) $viewData['canAuthor'] ? collect($viewData['assignments']) : collect())->map(function ($assignment): array {
                $form = $assignment->collection->form;
                $period = $assignment->collection->reportingPeriod;

                return [
                    'id' => (string) $assignment->id,
                    'formCode' => (string) $form->code,
                    'formTitle' => (string) $form->title,
                    'periodLabel' => (string) ($period?->label ?: 'Reporting period'),
                    'component' => $form->projectComponent?->name ?: null,
                ];
            })->values()->all(),
            'canAuthor' => (bool) $viewData['canAuthor'],
            'pagination' => [
                'page' => $reports->currentPage(),
                'lastPage' => $reports->lastPage(),
                'perPage' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function reportListItem(MePerformanceReport $report): array
    {
        return [
            'id' => (string) $report->id,
            'formCode' => (string) ($report->form?->code ?: ''),
            'formTitle' => (string) ($report->form?->title ?: 'Performance report'),
            'periodLabel' => $report->periodLabel(),
            'reportingYear' => (int) $report->reporting_year,
            'component' => $report->projectComponent?->name ?: null,
            'directorate' => $report->responsibleDirectorate?->name ?: null,
            'status' => (string) $report->status,
            'statusLabel' => $report->lifecycleLabel(),
            'indicatorCount' => isset($report->indicator_results_count)
                ? (int) $report->indicator_results_count
                : $report->indicatorResults->count(),
            'documentCount' => isset($report->documents_count)
                ? (int) $report->documents_count
                : $report->documents->count(),
            'editable' => $report->isEditable(),
            'updatedAt' => $this->dateTime($report->updated_at),
        ];
    }

    /** @return array<string, mixed> */
    public function reportDetail(array $viewData): array
    {
        /** @var MePerformanceReport $report */
        $report = $viewData['report'];
        $report->loadMissing(['indicatorResults.achievements.documentLinks.repositoryItem', 'documents', 'transitions.actor']);
        $listItem = $this->reportListItem($report);
        $narrativeKeys = [
            'key_achievements', 'variance_explanation', 'means_of_verification_notes',
            'overall_assessment', 'performance_rating', 'conclusion', 'challenges_faced',
            'mitigation_strategies', 'lessons_learned', 'adaptive_management_actions',
            'next_period_priorities',
        ];

        return [
            'report' => $listItem + [
                'reviewNotes' => $report->review_notes ?: null,
                'submittedAt' => $this->dateTime($report->submitted_at),
                'approvedAt' => $this->dateTime($report->approved_at),
                'lockToken' => $this->lockToken($report),
            ],
            'narratives' => collect($narrativeKeys)->mapWithKeys(
                fn (string $key): array => [$key => $report->{$key} ?: null]
            )->all(),
            'indicators' => $report->indicatorResults->map(function ($result) use ($report): array {
                $indicator = $result->indicator;

                return [
                    'id' => (string) $result->id,
                    'indicatorId' => (string) $result->indicator_id,
                    'code' => (string) ($indicator?->indicator_code ?: ''),
                    'name' => (string) ($indicator?->name ?: 'Indicator'),
                    'definition' => $indicator?->definitions ?: null,
                    'valueType' => (string) ($indicator?->value_type ?: 'number'),
                    'unit' => $indicator?->unit?->symbol ?: $indicator?->unit?->name,
                    'target' => $this->nullableFloat($result->target_value),
                    'actualValue' => $this->nullableFloat($result->actual_value),
                    'actualText' => $result->actual_text ?: null,
                    'rollupNumerator' => $this->nullableFloat($result->rollup_numerator),
                    'rollupDenominator' => $this->nullableFloat($result->rollup_denominator),
                    'progressPercent' => $this->nullableFloat($result->progress_percent),
                    'requiresEvidence' => (bool) ($indicator?->requires_evidence),
                    'achievements' => $result->achievements->map(function ($achievement) use ($report): array {
                        return [
                            'id' => (string) $achievement->id,
                            'code' => (string) $achievement->achievement_code,
                            'title' => (string) $achievement->title,
                            'description' => (string) $achievement->description,
                            'achievedOn' => $this->date($achievement->achieved_on),
                            'geographicScope' => (string) $achievement->geographic_scope,
                            'country' => $achievement->country ?: null,
                            'rec' => $achievement->rec ?: null,
                            'location' => $achievement->location ?: null,
                            'collaboratingInstitutions' => array_values($achievement->collaborating_institutions ?: []),
                            'priorityThemes' => array_values($achievement->priority_themes ?: []),
                            'totalBeneficiaries' => (int) $achievement->total_beneficiaries,
                            'verificationStatus' => (string) $achievement->verification_status,
                            'breakdowns' => $achievement->breakdowns->map(fn ($breakdown): array => [
                                'id' => (string) $breakdown->id,
                                'geographicScope' => $breakdown->geographic_scope ?: null,
                                'country' => $breakdown->country ?: null,
                                'rec' => $breakdown->rec ?: null,
                                'implementingInstitutionType' => $breakdown->implementing_institution_type ?: null,
                                'implementingInstitution' => $breakdown->implementing_institution ?: null,
                                'priorityTheme' => $breakdown->priority_theme ?: null,
                                'gender' => $breakdown->gender ?: null,
                                'ageGroup' => $breakdown->age_group ?: null,
                                'stakeholderCategory' => $breakdown->stakeholder_category ?: null,
                                'beneficiaryCount' => (int) $breakdown->beneficiary_count,
                            ])->values()->all(),
                            'evidence' => $achievement->documentLinks->map(function ($link) use ($report, $achievement): array {
                                $item = $link->repositoryItem;

                                return [
                                    'id' => (string) $item->id,
                                    'title' => (string) $item->title,
                                    'filename' => $item->original_filename ?: null,
                                    'size' => $item->file_size ? (int) $item->file_size : null,
                                    'status' => (string) ($item->validation_status ?: 'pending'),
                                    'downloadUrl' => route('api.v1.think-tank.monitoring.reports.achievement-evidence.show', [
                                        'report' => $report->id,
                                        'achievement' => $achievement->id,
                                        'evidence' => $item->id,
                                    ], false),
                                ];
                            })->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'documents' => $report->documents->map(fn ($document): array => [
                'id' => (string) $document->id,
                'name' => (string) $document->document_name,
                'filename' => (string) $document->original_filename,
                'size' => $document->file_size ? (int) $document->file_size : null,
                'validationStatus' => (string) ($document->validation_status ?: 'pending'),
                'downloadUrl' => route('api.v1.think-tank.monitoring.reports.documents.show', [
                    'report' => $report->id,
                    'document' => $document->id,
                ], false),
            ])->values()->all(),
            'sectionCompletion' => collect($viewData['sectionCompletion'])->map(fn (array $section): array => [
                'number' => (int) $section['number'],
                'title' => (string) $section['title'],
                'status' => (string) $section['status'],
                'statusLabel' => (string) $section['status_label'],
                'completed' => (int) $section['completed'],
                'total' => (int) $section['total'],
                'missing' => array_values($section['missing']),
            ])->all(),
            'submissionReady' => (bool) $viewData['submissionReady'],
            'canManage' => (bool) $viewData['canManage'],
            'canSubmit' => (bool) ($viewData['canSubmit'] ?? false),
            'taxonomies' => $viewData['achievementTaxonomy'],
            'performanceRatings' => $viewData['performanceRatings'],
            'history' => $report->transitions->map(fn ($transition): array => [
                'id' => (string) $transition->id,
                'action' => (string) $transition->action,
                'fromStatus' => $transition->from_status ?: null,
                'toStatus' => (string) $transition->to_status,
                'notes' => $transition->notes ?: null,
                'actor' => $transition->actor?->name ?: null,
                'occurredAt' => $this->dateTime($transition->created_at),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function notificationsPayload(object $user, array $filters): array
    {
        $base = $user->notifications()->where('type', MeReportingNotification::class);
        $query = clone $base;
        $state = (string) ($filters['state'] ?? 'all');
        $category = (string) ($filters['category'] ?? '');
        $severity = (string) ($filters['severity'] ?? '');
        $search = trim((string) ($filters['q'] ?? ''));

        $query->when($state === 'unread', fn ($builder) => $builder->whereNull('read_at'))
            ->when($state === 'read', fn ($builder) => $builder->whereNotNull('read_at'))
            ->when($category !== '', fn ($builder) => $builder->where('data', 'like', '%"category":"'.$category.'"%'))
            ->when($severity !== '', fn ($builder) => $builder->where('data', 'like', '%"severity":"'.$severity.'"%'))
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->whereRaw('LOWER(data) LIKE ?', ['%'.addcslashes(Str::lower($search), '%_\\').'%']);
            });

        $paginator = $query->latest()->paginate(20);

        return [
            'summary' => [
                'total' => (clone $base)->count(),
                'unread' => (clone $base)->whereNull('read_at')->count(),
                'urgent' => (clone $base)->whereNull('read_at')->where('data', 'like', '%"severity":"danger"%')->count(),
                'today' => (clone $base)->whereDate('created_at', today())->count(),
            ],
            'notifications' => collect($paginator->items())->map(
                fn (DatabaseNotification $notification): array => $this->notificationItem($notification)
            )->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function notificationItem(DatabaseNotification $notification): array
    {
        return [
            'id' => (string) $notification->id,
            'title' => (string) data_get($notification->data, 'title', 'M&E notification'),
            'message' => (string) data_get($notification->data, 'message', ''),
            'category' => (string) data_get($notification->data, 'category', 'me_submission'),
            'severity' => (string) data_get($notification->data, 'severity', 'info'),
            'readAt' => $this->dateTime($notification->read_at),
            'createdAt' => $this->dateTime($notification->created_at),
            'destination' => $this->notificationDestination(data_get($notification->data, 'url')),
        ];
    }

    public function notificationDestination(mixed $candidate): ?string
    {
        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $path = parse_url(trim($candidate), PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (preg_match('~/me-data/([0-9a-f-]{36})~i', $path, $matches) === 1 && Str::isUuid($matches[1])) {
            return '/monitoring/assignments/'.$matches[1];
        }
        if (preg_match('~/performance-reports/([0-9a-f-]{36})~i', $path, $matches) === 1 && Str::isUuid($matches[1])) {
            return '/monitoring/reports/'.$matches[1];
        }
        if (str_contains($path, '/me-data')) {
            return '/monitoring/assignments';
        }
        if (str_contains($path, '/performance-reports')) {
            return '/monitoring/reports';
        }

        return '/monitoring/notifications';
    }

    public function lockToken(Model $model): string
    {
        $version = max(1, (int) ($model->getAttribute('portal_lock_version') ?: 1));
        $key = (string) config('app.key');

        return hash_hmac('sha256', $model::class.'|'.$model->getKey().'|'.$version, $key);
    }

    public function incrementLockVersion(Model $model): void
    {
        $model->increment('portal_lock_version');
    }

    public function assertLockToken(Model $model, mixed $provided): void
    {
        if (! is_string($provided) || ! hash_equals($this->lockToken($model), trim($provided))) {
            throw new ThinkTankApiException(
                'STALE_WRITE',
                'This record changed after it was opened. Reload the latest version before saving.',
                409,
            );
        }
    }

    /** @param array<string, mixed> $file */
    public function attachmentId(array $file, string $assignmentId, string $fieldId): string
    {
        $storedId = trim((string) ($file['id'] ?? ''));
        if (Str::isUuid($storedId)) {
            return $storedId;
        }

        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'attp-me-attachment|'.$assignmentId.'|'.$fieldId.'|'.(string) ($file['path'] ?? '')
        )->toString();
    }

    private function assignmentMatches(array $card, string $state, string $query): bool
    {
        if ($state !== 'all' && $card['state'] !== $state) {
            return false;
        }
        if ($query === '') {
            return true;
        }

        $haystack = Str::lower(implode(' ', array_filter([
            $card['formTitle'], $card['formCode'], $card['indicatorCode'], $card['indicatorName'], $card['periodLabel'],
        ])));

        return str_contains($haystack, Str::lower($query));
    }

    private function submissionStatusLabel(?string $status): string
    {
        return match ($status) {
            MeDataSubmission::STATUS_DRAFT => 'Draft saved',
            MeDataSubmission::STATUS_SUBMITTED => 'Submitted for review',
            MeDataSubmission::STATUS_RETURNED => 'Returned for correction',
            MeDataSubmission::STATUS_RESUBMITTED => 'Resubmitted for review',
            MeDataSubmission::STATUS_UNDER_REVIEW => 'Under Secretariat review',
            MeDataSubmission::STATUS_VALIDATED => 'Validated',
            MeDataSubmission::STATUS_VERIFIED => 'Verified',
            MeDataSubmission::STATUS_APPROVED => 'Approved',
            MeDataSubmission::STATUS_REJECTED => 'Rejected',
            default => 'Not started',
        };
    }

    private function resultsScopeLabel(array $data, string $memberName): string
    {
        if ($data['period'] ?? null) {
            return $memberName.' - '.$data['period']->label;
        }
        if ($data['reportingYear'] ?? null) {
            return $memberName.' - reporting year '.$data['reportingYear'];
        }

        return $memberName.' - all approved reporting periods';
    }

    private function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    private function dateTime(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
