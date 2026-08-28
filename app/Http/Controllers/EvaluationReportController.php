<?php

namespace App\Http\Controllers;

use App\Exports\EvaluationProcurementWorkbookExport;
use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\Procurement;
use App\Models\ProcurementPlan;
use App\Services\EoiQualificationService;
use App\Services\EoiReportCommunicationService;
use App\Support\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class EvaluationReportController extends Controller
{
    use ScopesAssignedPortfolios;

    public function index()
    {
        $procurementQuery = Procurement::query()
            ->with([
                'thinkTankPlanningItem:id,procurement_id,procurement_category,procurement_method',
                'evaluationAssignments:id,evaluation_id,procurement_id,form_submission_id,user_id,status,assigned_at',
                'evaluationAssignments.evaluation:id,name,type,evaluation_phase,procurement_id',
                'evaluations:id,name,type,evaluation_phase,procurement_id',
            ])
            ->withCount('submissions')
            ->orderBy('title');
        $this->applyEvaluationReportProcurementScope($procurementQuery);
        $procurements = $procurementQuery->get();
        $directEvaluationsByProcurement = Evaluation::query()
            ->whereIn('procurement_id', $procurements->pluck('id'))
            ->get(['id', 'name', 'type', 'evaluation_phase', 'procurement_id'])
            ->groupBy(fn (Evaluation $evaluation): string => (string) $evaluation->procurement_id);
        $procurementPlansByReference = ProcurementPlan::query()
            ->with('methodPlanned:id,method_name')
            ->whereIn('procurement_code', $procurements->pluck('reference_no')->filter())
            ->get(['id', 'procurement_code', 'method_planned_id'])
            ->keyBy(fn (ProcurementPlan $plan): string => (string) $plan->procurement_code);

        $submissionQuery = EvaluationSubmission::with([
            'evaluation:id,name,type,evaluation_phase,procurement_id',
        ])
            ->whereNotNull('submitted_at')
            ->whereIn('procurement_id', $procurements->pluck('id'))
            ->whereHas('procurement', fn ($query) => $query->whereNull('procurements.deleted_at'))
            ->orderByDesc('submitted_at');
        $this->applyEvaluationReportSubmissionScope($submissionQuery);
        $submissions = $this->activeReportSubmissions($submissionQuery->get());

        $eoiProcurementQuery = Procurement::query()
            ->where(function ($query): void {
                $query->whereHas('evaluationAssignments.evaluation', function ($evaluation): void {
                    $evaluation->where('type', Evaluation::TYPE_EOI);
                })->orWhereHas('evaluations', function ($evaluation): void {
                    $evaluation->where('type', Evaluation::TYPE_EOI);
                })->orWhereHas('directEvaluations', function ($evaluation): void {
                    $evaluation->where('type', Evaluation::TYPE_EOI);
                });
            })
            ->withCount('submissions')
            ->with([
                'evaluationAssignments' => function ($assignments): void {
                    $assignments
                        ->whereHas('evaluation', fn ($evaluation) => $evaluation
                            ->where('type', Evaluation::TYPE_EOI))
                        ->with(['evaluation:id,name,type', 'evaluator:id,name,email']);
                },
                'directEvaluations' => fn ($evaluations) => $evaluations
                    ->where('type', Evaluation::TYPE_EOI)
                    ->select(['id', 'name', 'type', 'procurement_id']),
            ])
            ->orderBy('title');
        $this->applyEvaluationReportProcurementScope($eoiProcurementQuery);
        $eoiProcurements = $eoiProcurementQuery->get();

        $eoiSubmissionGroups = $submissions
            ->filter(fn (EvaluationSubmission $submission): bool => $submission->evaluation?->isEoi() ?? false)
            ->groupBy(fn (EvaluationSubmission $submission): string => (string) $submission->procurement_id);
        $eoiProcurementStats = $eoiProcurements->mapWithKeys(function (Procurement $procurement) use ($eoiSubmissionGroups): array {
            $completed = $eoiSubmissionGroups
                ->get((string) $procurement->getKey(), collect())
                ->filter(fn (EvaluationSubmission $submission): bool => $this->eoiSubmissionHasActiveAssignment(
                    $submission,
                    $procurement->evaluationAssignments
                ));
            $panelMemberIds = $procurement->evaluationAssignments
                ->pluck('user_id')->filter()->unique();

            return [(string) $procurement->getKey() => [
                'applicants' => (int) $procurement->submissions_count,
                'evaluated_applicants' => $completed
                    ->pluck('form_submission_id')
                    ->filter()
                    ->unique()
                    ->count(),
                'completed_reports' => $completed->count(),
                'panel_members' => $panelMemberIds->count(),
                'templates' => $procurement->evaluationAssignments
                    ->pluck('evaluation.name')
                    ->merge($procurement->directEvaluations->pluck('name'))
                    ->filter()
                    ->unique()
                    ->values(),
            ]];
        });

        $configurationTypes = Evaluation::configurationTypes();
        $submissionsByProcurement = $submissions
            ->groupBy(fn (EvaluationSubmission $submission): string => (string) $submission->procurement_id);
        $eoiProcurementsById = $eoiProcurements
            ->keyBy(fn (Procurement $procurement): string => (string) $procurement->getKey());
        $methodOrder = [
            Evaluation::TYPE_EOI => 0,
            Evaluation::TYPE_SERVICES => 1,
            Evaluation::TYPE_GOODS => 2,
        ];

        $procurementReportGroups = $procurements
            ->map(function (Procurement $procurement) use (
                $configurationTypes,
                $submissionsByProcurement,
                $eoiProcurementsById,
                $eoiProcurementStats,
                $directEvaluationsByProcurement,
                $procurementPlansByReference,
                $methodOrder
            ): array {
                $procurementId = (string) $procurement->getKey();
                $procurementSubmissions = $submissionsByProcurement
                    ->get($procurementId, collect())
                    ->filter(fn (EvaluationSubmission $submission): bool => ! ($submission->evaluation?->isEoi() ?? false)
                        || $this->eoiSubmissionHasActiveAssignment(
                            $submission,
                            $procurement->evaluationAssignments
                        ))
                    ->values();
                $eoiStats = $eoiProcurementStats->get($procurementId);
                $procurementPlan = $procurementPlansByReference->get((string) $procurement->reference_no);
                $configuredEvaluations = collect()
                    ->merge($procurement->evaluationAssignments->pluck('evaluation'))
                    ->merge($procurement->evaluations)
                    ->merge($directEvaluationsByProcurement->get($procurementId, collect()))
                    ->merge($procurementSubmissions->pluck('evaluation'))
                    ->filter(fn ($evaluation): bool => $evaluation instanceof Evaluation && filled($evaluation->type))
                    ->unique(fn (Evaluation $evaluation): string => (string) $evaluation->getKey())
                    ->values();

                $methods = $procurementSubmissions
                    ->groupBy(fn (EvaluationSubmission $submission): string => (string) ($submission->evaluation?->type ?: 'unclassified'))
                    ->map(function ($methodSubmissions, string $type) use ($configurationTypes): array {
                        $definition = $configurationTypes[$type] ?? [
                            'label' => str($type)->headline()->toString(),
                            'mode' => 'Evaluation record',
                            'description' => 'Submitted evaluation reports.',
                            'color' => 'secondary',
                        ];
                        $latestSubmission = $methodSubmissions
                            ->sortByDesc(fn (EvaluationSubmission $submission): int => $submission->submitted_at?->getTimestamp() ?? 0)
                            ->first();

                        return [
                            'type' => $type,
                            'label' => $definition['label'],
                            'mode' => $definition['mode'],
                            'description' => $definition['description'],
                            'color' => $definition['color'],
                            'submissions' => $methodSubmissions->values(),
                            'templates' => $methodSubmissions
                                ->pluck('evaluation.name')
                                ->filter()
                                ->unique()
                                ->sort()
                                ->values(),
                            'phases' => $methodSubmissions
                                ->pluck('evaluation.evaluation_phase')
                                ->filter()
                                ->map(fn ($phase): string => str((string) $phase)->headline()->toString())
                                ->unique()
                                ->sort()
                                ->values(),
                            'report_count' => $methodSubmissions->count(),
                            'applicant_count' => $methodSubmissions
                                ->pluck('form_submission_id')
                                ->filter()
                                ->unique()
                                ->count(),
                            'evaluator_count' => $methodSubmissions
                                ->pluck('evaluator_id')
                                ->filter()
                                ->unique()
                                ->count(),
                            'assignment_count' => 0,
                            'completed_assignment_count' => 0,
                            'status' => $methodSubmissions->isNotEmpty() ? 'ready' : 'awaiting',
                            'latest_at' => $latestSubmission?->submitted_at,
                            'is_eoi' => $type === Evaluation::TYPE_EOI,
                            'eoi_stats' => null,
                        ];
                    });

                foreach ($configuredEvaluations->groupBy('type') as $type => $evaluations) {
                    $definition = $configurationTypes[$type] ?? [
                        'label' => str((string) $type)->headline()->toString(),
                        'mode' => 'Evaluation record',
                        'description' => 'Configured evaluation method.',
                        'color' => 'secondary',
                    ];
                    $existingMethod = $methods->get($type, [
                        'type' => (string) $type,
                        'label' => $definition['label'],
                        'mode' => $definition['mode'],
                        'description' => $definition['description'],
                        'color' => $definition['color'],
                        'submissions' => collect(),
                        'templates' => collect(),
                        'phases' => collect(),
                        'report_count' => 0,
                        'applicant_count' => 0,
                        'evaluator_count' => 0,
                        'assignment_count' => 0,
                        'completed_assignment_count' => 0,
                        'status' => 'awaiting',
                        'latest_at' => null,
                        'is_eoi' => $type === Evaluation::TYPE_EOI,
                        'eoi_stats' => null,
                    ]);
                    $existingMethod['templates'] = collect($existingMethod['templates'])
                        ->merge($evaluations->pluck('name'))
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();
                    $existingMethod['phases'] = collect($existingMethod['phases'])
                        ->merge($evaluations->pluck('evaluation_phase')
                            ->filter()
                            ->map(fn ($phase): string => str((string) $phase)->headline()->toString()))
                        ->unique()
                        ->sort()
                        ->values();
                    $existingMethod['evaluator_count'] = collect($existingMethod['submissions'])
                        ->pluck('evaluator_id')
                        ->merge($procurement->evaluationAssignments
                            ->filter(fn ($assignment): bool => (string) $assignment->evaluation?->type === (string) $type)
                            ->pluck('user_id'))
                        ->filter()
                        ->unique()
                        ->count();
                    $methods->put((string) $type, $existingMethod);
                }

                if ($eoiProcurementsById->has($procurementId)) {
                    $eoiDefinition = $configurationTypes[Evaluation::TYPE_EOI];
                    $existingEoi = $methods->get(Evaluation::TYPE_EOI, [
                        'type' => Evaluation::TYPE_EOI,
                        'label' => $eoiDefinition['label'],
                        'mode' => $eoiDefinition['mode'],
                        'description' => $eoiDefinition['description'],
                        'color' => $eoiDefinition['color'],
                        'submissions' => collect(),
                        'templates' => collect(),
                        'phases' => collect(),
                        'report_count' => 0,
                        'applicant_count' => 0,
                        'evaluator_count' => 0,
                        'assignment_count' => 0,
                        'completed_assignment_count' => 0,
                        'status' => 'awaiting',
                        'latest_at' => null,
                        'is_eoi' => true,
                        'eoi_stats' => null,
                    ]);

                    $existingEoi['templates'] = collect($existingEoi['templates'])
                        ->merge($eoiStats['templates'] ?? [])
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();
                    $existingEoi['report_count'] = max(
                        (int) $existingEoi['report_count'],
                        (int) ($eoiStats['completed_reports'] ?? 0)
                    );
                    $existingEoi['applicant_count'] = max(
                        (int) $existingEoi['applicant_count'],
                        (int) ($eoiStats['evaluated_applicants'] ?? 0)
                    );
                    $existingEoi['evaluator_count'] = max(
                        (int) $existingEoi['evaluator_count'],
                        (int) ($eoiStats['panel_members'] ?? 0)
                    );
                    $existingEoi['eoi_stats'] = $eoiStats;
                    $methods->put(Evaluation::TYPE_EOI, $existingEoi);
                }

                $methods = $methods->map(function (array $method) use ($procurement): array {
                    $methodAssignments = $procurement->evaluationAssignments
                        ->filter(fn ($assignment): bool => (string) $assignment->evaluation?->type === (string) $method['type']);
                    $method['assignment_count'] = $methodAssignments->count();
                    $method['completed_assignment_count'] = $methodAssignments
                        ->where('status', 'submitted')
                        ->count();
                    $method['status'] = match (true) {
                        $method['report_count'] === 0 => 'awaiting',
                        $methodAssignments->isEmpty(),
                        $method['completed_assignment_count'] === $method['assignment_count'] => 'ready',
                        default => 'in_progress',
                    };

                    return $method;
                });

                $methods = $methods
                    ->sortBy(fn (array $method): string => str_pad(
                        (string) ($methodOrder[$method['type']] ?? 99),
                        2,
                        '0',
                        STR_PAD_LEFT
                    ).strtolower($method['label']))
                    ->values();
                $latestSubmission = $procurementSubmissions
                    ->sortByDesc(fn (EvaluationSubmission $submission): int => $submission->submitted_at?->getTimestamp() ?? 0)
                    ->first();
                $reportedApplicantCount = $procurementSubmissions
                    ->pluck('form_submission_id')
                    ->filter()
                    ->unique()
                    ->count();
                $reportedEvaluatorCount = $procurementSubmissions
                    ->pluck('evaluator_id')
                    ->merge($procurement->evaluationAssignments->pluck('user_id'))
                    ->filter()
                    ->unique()
                    ->count();

                return [
                    'procurement' => $procurement,
                    'procurement_method' => $procurement->thinkTankPlanningItem?->procurement_method
                        ?: $procurementPlan?->methodPlanned?->method_name,
                    'procurement_category' => $procurement->thinkTankPlanningItem?->procurement_category,
                    'methods' => $methods,
                    'report_count' => max(
                        $procurementSubmissions->count(),
                        (int) $methods->sum('report_count')
                    ),
                    'applicant_count' => max(
                        $reportedApplicantCount,
                        (int) ($eoiStats['evaluated_applicants'] ?? 0)
                    ),
                    'total_applicants' => (int) ($procurement->submissions_count ?? 0),
                    'evaluator_count' => max(
                        $reportedEvaluatorCount,
                        (int) ($eoiStats['panel_members'] ?? 0)
                    ),
                    'latest_at' => $latestSubmission?->submitted_at,
                ];
            })
            ->sortByDesc(fn (array $group): int => $group['latest_at']?->getTimestamp() ?? 0)
            ->values();

        $methodReportStats = $procurementReportGroups
            ->flatMap(fn (array $group) => $group['methods'])
            ->groupBy('type')
            ->map(function ($methods, string $type) use ($configurationTypes): array {
                return [
                    'type' => $type,
                    'label' => $configurationTypes[$type]['label'] ?? str($type)->headline()->toString(),
                    'procurements' => $methods->count(),
                    'reports' => $methods->sum('report_count'),
                ];
            })
            ->sortBy(fn (array $method): string => $method['label'])
            ->values();

        return view('reports.evaluations.index', compact(
            'procurements',
            'submissions',
            'eoiProcurements',
            'eoiProcurementStats',
            'procurementReportGroups',
            'methodReportStats'
        ));
    }

    public function methodIndex(string $method)
    {
        $method = $this->normaliseMethod($method);
        $methodDefinition = $this->methodDefinition($method);
        $procurementQuery = $this->methodProcurementQuery($method)
            ->withCount('submissions')
            ->with([
                'thinkTankPlanningItem:id,procurement_id,procurement_category,procurement_method',
                'evaluationAssignments' => fn ($assignments) => $assignments
                    ->whereHas('evaluation', fn ($evaluation) => $evaluation->where('type', $method))
                    ->with([
                        'evaluation:id,name,type,evaluation_phase,procurement_id',
                        'evaluator:id,name,email',
                    ]),
                'evaluations' => fn ($evaluations) => $evaluations->where('type', $method),
                'directEvaluations' => fn ($evaluations) => $evaluations->where('type', $method),
            ])
            ->orderBy('title');
        $procurements = $procurementQuery->get();

        $procurementPlansByReference = ProcurementPlan::query()
            ->with('methodPlanned:id,method_name')
            ->whereIn('procurement_code', $procurements->pluck('reference_no')->filter())
            ->get(['id', 'procurement_code', 'method_planned_id'])
            ->keyBy(fn (ProcurementPlan $plan): string => (string) $plan->procurement_code);

        $submissionRelations = ['evaluation.sections.criteria'];

        if ($method !== Evaluation::TYPE_SERVICES) {
            $submissionRelations[] = 'criteriaScores:id,submission_id,decision';
        }

        $submissionQuery = EvaluationSubmission::query()
            ->with($submissionRelations)
            ->whereIn('procurement_id', $procurements->pluck('id'))
            ->whereNotNull('submitted_at')
            ->whereHas('evaluation', fn ($evaluation) => $evaluation->where('type', $method))
            ->orderByDesc('submitted_at');
        $this->applyEvaluationReportSubmissionScope($submissionQuery);
        $submissionsByProcurement = $this->activeReportSubmissions(
            $submissionQuery->get([
                'id',
                'evaluation_id',
                'procurement_id',
                'evaluator_id',
                'form_submission_id',
                'overall_score',
                'submitted_at',
            ])
        )
            ->groupBy(fn (EvaluationSubmission $submission): string => (string) $submission->procurement_id);

        $procurementRows = $procurements
            ->map(function (Procurement $procurement) use (
                $method,
                $submissionsByProcurement,
                $procurementPlansByReference
            ): array {
                $reports = $submissionsByProcurement
                    ->get((string) $procurement->getKey(), collect())
                    ->when(
                        $method === Evaluation::TYPE_EOI,
                        fn ($reports) => $reports->filter(
                            fn (EvaluationSubmission $submission): bool => $this->eoiSubmissionHasActiveAssignment(
                                $submission,
                                $procurement->evaluationAssignments
                            )
                        )
                    )
                    ->values();
                $assignments = $procurement->evaluationAssignments;
                $completedAssignments = $assignments->where('status', 'submitted')->count();
                $templates = collect()
                    ->merge($assignments->pluck('evaluation.name'))
                    ->merge($procurement->evaluations->pluck('name'))
                    ->merge($procurement->directEvaluations->pluck('name'))
                    ->merge($reports->pluck('evaluation.name'))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();
                $latestAt = $reports->max('submitted_at');
                $status = match (true) {
                    $reports->isEmpty() => 'awaiting',
                    $assignments->isNotEmpty() && $completedAssignments < $assignments->count() => 'in_progress',
                    default => 'ready',
                };
                $procurementPlan = $procurementPlansByReference->get((string) $procurement->reference_no);

                return [
                    'procurement' => $procurement,
                    'procurement_method' => $procurement->thinkTankPlanningItem?->procurement_method
                        ?: $procurementPlan?->methodPlanned?->method_name,
                    'procurement_category' => $procurement->thinkTankPlanningItem?->procurement_category,
                    'templates' => $templates,
                    'phases' => $reports->pluck('evaluation.evaluation_phase')
                        ->filter()
                        ->map(fn ($phase): string => Str::headline((string) $phase))
                        ->unique()
                        ->sort()
                        ->values(),
                    'report_count' => $reports->count(),
                    'applicant_count' => $reports->pluck('form_submission_id')->filter()->unique()->count(),
                    'total_applicants' => (int) $procurement->submissions_count,
                    'evaluator_count' => $reports->pluck('evaluator_id')
                        ->merge($assignments->pluck('user_id'))
                        ->filter()
                        ->unique()
                        ->count(),
                    'assignment_count' => $assignments->count(),
                    'completed_assignment_count' => $completedAssignments,
                    'status' => $status,
                    'latest_at' => $latestAt,
                    'result_summary' => $this->methodResultSummary($reports, $method),
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['latest_at']?->getTimestamp() ?? 0)
            ->values();
        $methodEvaluatorIds = $procurements
            ->flatMap(fn (Procurement $procurement) => $procurement->evaluationAssignments->pluck('user_id'));

        if ($method !== Evaluation::TYPE_EOI) {
            $methodEvaluatorIds = $methodEvaluatorIds
                ->merge($submissionsByProcurement->flatten(1)->pluck('evaluator_id'));
        }

        $methodEvaluatorCount = $methodEvaluatorIds->filter()->unique()->count();

        $summary = [
            'procurements' => $procurementRows->count(),
            'reports' => $procurementRows->sum('report_count'),
            'applicants' => $procurementRows->sum('applicant_count'),
            'evaluators' => $methodEvaluatorCount,
            'ready' => $procurementRows->where('status', 'ready')->count(),
        ];

        return view('reports.evaluations.method', compact(
            'method',
            'methodDefinition',
            'procurementRows',
            'summary'
        ));
    }

    public function methodProcurement(string $method, Procurement $procurement)
    {
        $method = $this->normaliseMethod($method);

        if ($method === Evaluation::TYPE_EOI) {
            $this->assertProcurementUsesMethod($procurement, $method);

            return redirect()->route('reports.evaluations.eoi.procurement', $procurement);
        }

        return view(
            'reports.evaluations.method-procurement',
            $this->buildMethodProcurementReport($method, $procurement)
        );
    }

    public function methodProcurementExcel(string $method, Procurement $procurement)
    {
        $method = $this->normaliseMethod($method);

        if ($method === Evaluation::TYPE_EOI) {
            $this->assertProcurementUsesMethod($procurement, $method);

            return redirect()->route('reports.evaluations.eoi.procurement.excel', $procurement);
        }

        $report = $this->buildMethodProcurementReport($method, $procurement);
        $filename = $this->methodReportFilename($method, $procurement).'.xlsx';

        return $this->downloadWorkbook(
            new EvaluationProcurementWorkbookExport($this->workbookRows($report)),
            $filename
        );
    }

    public function methodProcurementCsv(string $method, Procurement $procurement)
    {
        $method = $this->normaliseMethod($method);

        if ($method === Evaluation::TYPE_EOI) {
            $this->assertProcurementUsesMethod($procurement, $method);

            return redirect()->route('reports.evaluations.eoi.procurement.csv', $procurement);
        }

        $report = $this->buildMethodProcurementReport($method, $procurement);
        [$headings, $rows] = $this->csvRows($report);
        $filename = $this->methodReportFilename($method, $procurement).'.csv';

        return $this->streamCsv($filename, $headings, $rows);
    }

    public function methodProcurementPdf(string $method, Procurement $procurement)
    {
        $method = $this->normaliseMethod($method);

        if ($method === Evaluation::TYPE_EOI) {
            $this->assertProcurementUsesMethod($procurement, $method);

            return redirect()->route('reports.evaluations.eoi.procurement.pdf', $procurement);
        }

        $report = $this->buildMethodProcurementReport($method, $procurement);
        $pdf = Pdf::loadView(
            'reports.evaluations.pdf.method-procurement',
            array_merge($report, PdfBranding::viewData())
        )->setPaper('a4', 'landscape');

        return $pdf->download($this->methodReportFilename($method, $procurement).'.pdf');
    }

    public function submission(EvaluationSubmission $submission)
    {
        $this->assertEvaluationSubmissionScope($submission);
        $submission->load([
            'procurement',
            'applicant.submitter',
            'applicant.values',
            'evaluation.sections.criteria',
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
        ]);

        $overallMax = $this->overallMax($submission);

        return view('reports.evaluations.submission', compact('submission', 'overallMax'));
    }

    public function submissionPdf(EvaluationSubmission $submission)
    {
        $this->assertEvaluationSubmissionScope($submission);

        return $this->downloadSubmissionReport($submission);
    }

    public function submissionAnonymisedPdf(EvaluationSubmission $submission)
    {
        $this->assertEvaluationSubmissionScope($submission);

        return $this->downloadSubmissionReport($submission, true);
    }

    public function eoiProcurement(
        Procurement $procurement,
        EoiQualificationService $qualificationService,
        EoiReportCommunicationService $communicationService
    ) {
        $this->assertEvaluationProcurementScope($procurement);
        $report = $qualificationService->buildProcurementReport($procurement);

        abort_if(
            $report['evaluations']->isEmpty(),
            404,
            'An Expression of Interest evaluation was not found for this procurement.'
        );

        $communications = EoiReportCommunication::query()
            ->where('procurement_id', $procurement->getKey())
            ->with([
                'creator:id,name',
                'attachments:id,communication_id,original_filename,mime_type,file_size',
                'recipients' => fn ($query) => $query
                    ->orderBy('recipient_name')
                    ->with('proposalDocuments:id,recipient_id,original_filename,mime_type,file_size,created_at'),
            ])
            ->withCount([
                'recipients',
                'recipients as sent_recipients_count' => fn ($query) => $query
                    ->where('delivery_status', EoiReportCommunicationRecipient::STATUS_SENT),
                'recipients as skipped_recipients_count' => fn ($query) => $query
                    ->where('delivery_status', EoiReportCommunicationRecipient::STATUS_SKIPPED),
                'recipients as failed_recipients_count' => fn ($query) => $query
                    ->where('delivery_status', EoiReportCommunicationRecipient::STATUS_FAILED),
            ])
            ->latest()
            ->limit(8)
            ->get();
        $communicationPreview = $communicationService->recipientPreview($report);

        return view('reports.evaluations.eoi-procurement', compact(
            'report',
            'communications',
            'communicationPreview'
        ));
    }

    public function eoiProcurementPdf(
        Procurement $procurement,
        EoiQualificationService $qualificationService
    ) {
        $this->assertEvaluationProcurementScope($procurement);
        $report = $qualificationService->buildProcurementReport($procurement);

        abort_if(
            $report['evaluations']->isEmpty(),
            404,
            'An Expression of Interest evaluation was not found for this procurement.'
        );

        $pdf = Pdf::loadView(
            'reports.evaluations.pdf.eoi-procurement',
            array_merge(['report' => $report], PdfBranding::viewData())
        )->setPaper('a4', 'landscape');

        $name = Str::slug($procurement->reference_no ?: $procurement->title ?: $procurement->getKey());

        return $pdf->download('eoi-qualification-'.$name.'.pdf');
    }

    public function eoiProcurementExcel(
        Procurement $procurement,
        EoiQualificationService $qualificationService
    ) {
        $this->assertEvaluationProcurementScope($procurement);
        $report = $qualificationService->buildProcurementReport($procurement);

        abort_if(
            $report['evaluations']->isEmpty(),
            404,
            'An Expression of Interest evaluation was not found for this procurement.'
        );

        return $this->downloadWorkbook(
            new EvaluationProcurementWorkbookExport($this->eoiWorkbookRows($report)),
            $this->methodReportFilename(Evaluation::TYPE_EOI, $procurement).'.xlsx'
        );
    }

    public function eoiProcurementCsv(
        Procurement $procurement,
        EoiQualificationService $qualificationService
    ) {
        $this->assertEvaluationProcurementScope($procurement);
        $report = $qualificationService->buildProcurementReport($procurement);

        abort_if(
            $report['evaluations']->isEmpty(),
            404,
            'An Expression of Interest evaluation was not found for this procurement.'
        );

        [$headings, $rows] = $this->eoiCsvRows($report);

        return $this->streamCsv(
            $this->methodReportFilename(Evaluation::TYPE_EOI, $procurement).'.csv',
            $headings,
            $rows
        );
    }

    private function downloadSubmissionReport(EvaluationSubmission $submission, bool $anonymised = false)
    {
        $submission->load([
            'procurement',
            'applicant.submitter',
            'applicant.values',
            'evaluation.sections.criteria',
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
        ]);

        $overallMax = $this->overallMax($submission);

        $name = $anonymised
            ? 'applicant'
            : Str::slug($submission->applicant?->display_name ?: 'submission');
        $code = $anonymised
            ? Str::slug((string) $submission->id)
            : Str::slug($submission->applicant?->procurement_submission_code ?: $submission->id);
        $prefix = $anonymised ? 'evaluation-submission-anonymised-' : 'evaluation-submission-';

        $pdf = Pdf::loadView('reports.evaluations.pdf.submission', array_merge([
            'submission' => $submission,
            'overallMax' => $overallMax,
            'anonymised' => $anonymised,
        ], PdfBranding::viewData()))->setPaper('a4', 'portrait');

        return $pdf->download($prefix.trim($name.'-'.$code, '-').'.pdf');
    }

    public function procurement(Procurement $procurement)
    {
        $this->assertEvaluationProcurementScope($procurement);

        $submissions = $this->activeReportSubmissions(EvaluationSubmission::with([
            'procurement',
            'applicant.submitter',
            'evaluation.sections.criteria',
            'criteriaScores',
            'sectionScores',
            'evaluator',
        ])
            ->where('procurement_id', $procurement->id)
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get());

        $summary = $this->buildSummary($submissions);
        $rankings = $this->buildApplicantRankings($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);
        $evaluationStats = $this->buildEvaluationStats($submissions);

        return view('reports.evaluations.procurement', compact(
            'procurement',
            'submissions',
            'summary',
            'rankings',
            'evaluatorBreakdown',
            'evaluationStats'
        ));
    }

    public function procurementPdf(Procurement $procurement)
    {
        $this->assertEvaluationProcurementScope($procurement);

        $submissions = $this->activeReportSubmissions(EvaluationSubmission::with([
            'procurement',
            'applicant.submitter',
            'evaluation.sections.criteria',
            'criteriaScores',
            'sectionScores',
            'evaluator',
        ])
            ->where('procurement_id', $procurement->id)
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get());

        $summary = $this->buildSummary($submissions);
        $rankings = $this->buildApplicantRankings($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);
        $evaluationStats = $this->buildEvaluationStats($submissions);

        $pdf = Pdf::loadView('reports.evaluations.pdf.procurement', compact(
            'procurement',
            'submissions',
            'summary',
            'rankings',
            'evaluatorBreakdown',
            'evaluationStats'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('evaluation-procurement-'.$procurement->id.'.pdf');
    }

    public function consolidated()
    {
        $submissionQuery = EvaluationSubmission::with([
            'procurement',
            'applicant.submitter',
            'evaluation',
            'criteriaScores',
            'evaluator',
        ])
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at');
        $this->applyEvaluationReportSubmissionScope($submissionQuery);
        $submissions = $this->activeReportSubmissions($submissionQuery->get());

        $summary = $this->buildSummary($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);
        $procurementStats = $this->buildProcurementStats($submissions);

        return view('reports.evaluations.consolidated', compact(
            'submissions',
            'summary',
            'evaluatorBreakdown',
            'procurementStats'
        ));
    }

    public function consolidatedPdf()
    {
        $submissionQuery = EvaluationSubmission::with([
            'procurement',
            'applicant.submitter',
            'evaluation',
            'criteriaScores',
            'evaluator',
        ])
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at');
        $this->applyEvaluationReportSubmissionScope($submissionQuery);
        $submissions = $this->activeReportSubmissions($submissionQuery->get());

        $summary = $this->buildSummary($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);
        $procurementStats = $this->buildProcurementStats($submissions);

        $pdf = Pdf::loadView('reports.evaluations.pdf.consolidated', compact(
            'submissions',
            'summary',
            'evaluatorBreakdown',
            'procurementStats'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('evaluation-consolidated.pdf');
    }

    private function normaliseMethod(string $method): string
    {
        $method = Str::lower(trim($method));

        abort_unless(
            in_array($method, Evaluation::MANAGED_TYPES, true),
            404,
            'The requested evaluation method is not available.'
        );

        return $method;
    }

    private function methodDefinition(string $method): array
    {
        $definition = Evaluation::configurationTypes()[$method];

        return array_merge($definition, match ($method) {
            Evaluation::TYPE_SERVICES => [
                'icon' => 'feather-bar-chart-2',
                'tone' => 'teal',
                'result_label' => 'Average panel score',
            ],
            Evaluation::TYPE_GOODS => [
                'icon' => 'feather-package',
                'tone' => 'amber',
                'result_label' => 'Compliance decisions',
            ],
            Evaluation::TYPE_EOI => [
                'icon' => 'feather-user-check',
                'tone' => 'violet',
                'result_label' => 'Qualification outcomes',
            ],
        });
    }

    private function methodProcurementQuery(string $method, bool $withTrashed = false)
    {
        $query = ($withTrashed ? Procurement::withTrashed() : Procurement::query())
            ->where(function ($procurements) use ($method): void {
                $procurements
                    ->whereHas('evaluationAssignments.evaluation', fn ($evaluation) => $evaluation
                        ->where('type', $method))
                    ->orWhereHas('evaluations', fn ($evaluation) => $evaluation
                        ->where('type', $method))
                    ->orWhereHas('directEvaluations', fn ($evaluation) => $evaluation
                        ->where('type', $method));

                if ($method !== Evaluation::TYPE_EOI) {
                    $procurements
                        ->orWhereHas('submissions.evaluationSubmissions.evaluation', fn ($evaluation) => $evaluation
                            ->where('type', $method))
                        ->orWhereHas('evaluationSubmissions.evaluation', fn ($evaluation) => $evaluation
                            ->where('type', $method));
                }
            });

        $this->applyEvaluationReportProcurementScope($query);

        return $query;
    }

    private function eoiSubmissionHasActiveAssignment(EvaluationSubmission $submission, iterable $assignments): bool
    {
        return collect($assignments)->contains(
            fn (EvaluationAssignment $assignment): bool => (string) $assignment->evaluation_id === (string) $submission->evaluation_id
                && (string) $assignment->user_id === (string) $submission->evaluator_id
                && (blank($assignment->form_submission_id)
                    || (string) $assignment->form_submission_id === (string) $submission->form_submission_id)
        );
    }

    private function activeReportSubmissions(iterable $submissions): Collection
    {
        $submissions = collect($submissions);
        $eoiSubmissions = $submissions
            ->filter(fn (EvaluationSubmission $submission): bool => $submission->evaluation?->isEoi() ?? false);

        if ($eoiSubmissions->isEmpty()) {
            return $submissions->values();
        }

        $assignmentsByProcurement = EvaluationAssignment::query()
            ->whereIn('procurement_id', $eoiSubmissions->pluck('procurement_id')->filter()->unique())
            ->whereIn('evaluation_id', $eoiSubmissions->pluck('evaluation_id')->filter()->unique())
            ->whereIn('user_id', $eoiSubmissions->pluck('evaluator_id')->filter()->unique())
            ->get([
                'id',
                'evaluation_id',
                'procurement_id',
                'form_submission_id',
                'user_id',
            ])
            ->groupBy(fn (EvaluationAssignment $assignment): string => (string) $assignment->procurement_id);

        return $submissions
            ->filter(fn (EvaluationSubmission $submission): bool => ! ($submission->evaluation?->isEoi() ?? false)
                || $this->eoiSubmissionHasActiveAssignment(
                    $submission,
                    $assignmentsByProcurement->get((string) $submission->procurement_id, collect())
                ))
            ->values();
    }

    private function buildMethodProcurementReport(string $method, Procurement $procurement): array
    {
        $this->assertProcurementUsesMethod($procurement, $method);

        $assignments = EvaluationAssignment::query()
            ->with('evaluation:id,name,type,evaluation_phase')
            ->where('procurement_id', $procurement->getKey())
            ->whereHas('evaluation', fn ($evaluation) => $evaluation->where('type', $method))
            ->get([
                'id',
                'evaluation_id',
                'procurement_id',
                'form_submission_id',
                'user_id',
                'status',
            ]);

        $submissions = EvaluationSubmission::query()
            ->with([
                'applicant' => fn ($applicants) => $applicants
                    ->select(['id', 'procurement_submission_code', 'submitted_by']),
                'applicant.submitter:id,name',
                'applicant.values' => fn ($values) => $values
                    ->whereIn('field_key', ['official_name', 'consortium_name', 'think_tank_name'])
                    ->select(['id', 'submission_id', 'field_key', 'value']),
                'evaluation.sections.criteria',
                'criteriaScores.criteria.section',
                'evaluator:id,name,email',
            ])
            ->where('procurement_id', $procurement->getKey())
            ->whereNotNull('submitted_at')
            ->whereHas('evaluation', fn ($evaluation) => $evaluation->where('type', $method))
            ->orderByDesc('submitted_at')
            ->get();

        $summary = $this->buildSummary($submissions);
        $summary['applicants'] = $submissions->pluck('form_submission_id')->filter()->unique()->count();
        $summary['templates'] = $submissions->pluck('evaluation_id')->filter()->unique()->count();
        $summary['latest_at'] = $submissions->max('submitted_at');
        $summary['configuration_warnings'] = $method === Evaluation::TYPE_SERVICES
            ? $submissions->filter(fn (EvaluationSubmission $submission): bool => $submission->overall_score !== null && $this->overallMax($submission) <= 0
            )->count()
            : 0;

        $serviceRankingGroups = $method === Evaluation::TYPE_SERVICES
            ? $this->buildServiceRankingGroups($submissions, $assignments)
            : collect();
        $applicantSummaries = $method === Evaluation::TYPE_SERVICES
            ? collect()
            : $this->buildMethodApplicantSummaries($submissions, $method, $assignments);

        return [
            'method' => $method,
            'methodDefinition' => $this->methodDefinition($method),
            'procurement' => $procurement,
            'submissions' => $submissions,
            'submissionRows' => $this->buildSubmissionRows($submissions, $method),
            'summary' => $summary,
            'resultSummary' => $this->methodResultSummary($submissions, $method),
            'applicantSummaries' => $applicantSummaries,
            'serviceRankingGroups' => $serviceRankingGroups,
            'evaluatorBreakdown' => $this->buildEvaluatorBreakdown($submissions),
            'evaluationStats' => $this->buildEvaluationStats($submissions),
        ];
    }

    private function assertProcurementUsesMethod(Procurement $procurement, string $method): void
    {
        $this->assertEvaluationProcurementScope($procurement);

        abort_unless(
            $this->methodProcurementQuery($method, true)
                ->whereKey($procurement->getKey())
                ->exists(),
            404,
            'This procurement does not use the requested evaluation method.'
        );
    }

    private function buildServiceRankingGroups($submissions, $assignments)
    {
        return $submissions
            ->filter(fn (EvaluationSubmission $submission): bool => $submission->evaluation !== null)
            ->groupBy(fn (EvaluationSubmission $submission): string => (string) $submission->evaluation_id)
            ->map(function ($evaluationSubmissions) use ($assignments): array {
                $evaluation = $evaluationSubmissions->first()->evaluation;
                $evaluationAssignments = $assignments->where('evaluation_id', $evaluation->getKey());
                $rankings = $this->buildMethodApplicantSummaries(
                    $evaluationSubmissions,
                    Evaluation::TYPE_SERVICES,
                    $evaluationAssignments
                );

                return [
                    'evaluation' => $evaluation,
                    'phase' => filled($evaluation->evaluation_phase)
                        ? Str::headline((string) $evaluation->evaluation_phase)
                        : 'Evaluation',
                    'rankings' => $rankings,
                    'ranked_applicants' => $rankings->whereNotNull('rank')->count(),
                    'incomplete_applicants' => $rankings->where('panel_complete', false)->count(),
                ];
            })
            ->sortBy(fn (array $group): string => Str::lower(
                $group['phase'].' '.$group['evaluation']->name
            ))
            ->values();
    }

    private function buildMethodApplicantSummaries($submissions, string $method, $assignments = null)
    {
        $rows = $submissions
            ->groupBy(fn (EvaluationSubmission $submission): string => (string) (
                $submission->form_submission_id ?: 'missing-'.$submission->getKey()
            ))
            ->map(function ($group) use ($method, $assignments): array {
                $taskSubmissions = $group
                    ->sortByDesc(fn (EvaluationSubmission $submission): int => $submission->submitted_at?->getTimestamp() ?? 0
                    )
                    ->unique(fn (EvaluationSubmission $submission): string => $this->submissionTaskKey($submission)
                    )
                    ->values();
                $first = $taskSubmissions->first();
                $scores = $taskSubmissions
                    ->flatMap(fn (EvaluationSubmission $submission) => $submission->criteriaScores);
                $panelProgress = $this->applicantPanelProgress($taskSubmissions, $assignments);
                $base = [
                    'submission' => $first?->applicant,
                    'evaluators' => $taskSubmissions->pluck('evaluator_id')->filter()->unique()->count(),
                    'evaluations' => $taskSubmissions->count(),
                    'rank' => null,
                    'metric' => null,
                    'metric_label' => null,
                    'outcome' => 'No completed decision',
                    'outcome_tone' => 'neutral',
                    'counts' => [],
                    'raw_average' => null,
                    'highest' => null,
                    'lowest' => null,
                    'spread' => null,
                    'expected_tasks' => $panelProgress['expected_tasks'],
                    'completed_tasks' => $panelProgress['completed_tasks'],
                    'panel_complete' => $panelProgress['panel_complete'],
                    'panel_status' => $panelProgress['panel_status'],
                ];

                if ($method === Evaluation::TYPE_SERVICES) {
                    $normalisedScores = $taskSubmissions
                        ->map(fn (EvaluationSubmission $submission) => $this->normalisedServiceScore($submission))
                        ->filter(fn ($score) => $score !== null)
                        ->values();
                    $rawScores = $taskSubmissions->pluck('overall_score')->filter(fn ($score) => $score !== null);

                    if ($normalisedScores->isEmpty()) {
                        return array_merge($base, [
                            'outcome' => 'Score unavailable',
                            'outcome_tone' => 'attention',
                        ]);
                    }

                    $highest = round((float) $normalisedScores->max(), 2);
                    $lowest = round((float) $normalisedScores->min(), 2);

                    return array_merge($base, [
                        'metric' => round((float) $normalisedScores->avg(), 2),
                        'metric_label' => 'Average panel score',
                        'outcome' => $panelProgress['panel_status'],
                        'outcome_tone' => $panelProgress['panel_complete'] ? 'positive' : 'attention',
                        'raw_average' => $rawScores->isNotEmpty()
                            ? round((float) $rawScores->avg(), 2)
                            : null,
                        'highest' => $highest,
                        'lowest' => $lowest,
                        'spread' => round($highest - $lowest, 2),
                    ]);
                }

                if ($method === Evaluation::TYPE_GOODS) {
                    $yes = $scores->where('decision', 1)->count();
                    $no = $scores->where('decision', 0)->count();
                    $total = $yes + $no;

                    return array_merge($base, [
                        'metric_label' => 'Compliance decisions',
                        'outcome' => match (true) {
                            $total === 0 => 'No decisions recorded',
                            $no === 0 && $panelProgress['panel_complete'] => 'No exceptions in completed panel',
                            $no === 0 => 'No exceptions in submitted reports',
                            default => 'Exceptions recorded',
                        },
                        'outcome_tone' => match (true) {
                            $total === 0 => 'neutral',
                            $no === 0 => 'positive',
                            default => 'attention',
                        },
                        'counts' => ['yes' => $yes, 'no' => $no, 'total' => $total],
                    ]);
                }

                $qualified = $scores->where('decision', 2)->count();
                $average = $scores->where('decision', 1)->count();
                $notQualified = $scores->where('decision', 0)->count();
                $total = $qualified + $average + $notQualified;

                return array_merge($base, [
                    'metric_label' => 'Qualification decisions',
                    'outcome' => match (true) {
                        $total === 0 => 'No decisions recorded',
                        $notQualified > 0 => 'Not Qualified recorded',
                        $average > 0 => 'Average Qualified recorded',
                        default => 'Qualified recorded',
                    },
                    'outcome_tone' => match (true) {
                        $total === 0 => 'neutral',
                        $notQualified > 0 => 'attention',
                        default => 'positive',
                    },
                    'counts' => [
                        'qualified' => $qualified,
                        'average_qualified' => $average,
                        'not_qualified' => $notQualified,
                        'total' => $total,
                    ],
                ]);
            });

        if ($method !== Evaluation::TYPE_SERVICES) {
            return $rows
                ->sortBy(fn (array $row): string => Str::lower((string) (
                    $row['submission']?->display_name
                    ?: $row['submission']?->procurement_submission_code
                    ?: ''
                )))
                ->values();
        }

        $position = 0;
        $currentRank = 0;
        $previousMetric = null;

        return $rows
            ->sort(function (array $left, array $right): int {
                $leftEligible = $left['panel_complete'] && $left['metric'] !== null;
                $rightEligible = $right['panel_complete'] && $right['metric'] !== null;

                if ($leftEligible !== $rightEligible) {
                    return $leftEligible ? -1 : 1;
                }

                $scoreOrder = ($right['metric'] ?? -1) <=> ($left['metric'] ?? -1);

                return $scoreOrder !== 0 ? $scoreOrder : strcmp(
                    Str::lower((string) ($left['submission']?->display_name ?? '')),
                    Str::lower((string) ($right['submission']?->display_name ?? ''))
                );
            })
            ->values()
            ->map(function (array $row) use (&$position, &$currentRank, &$previousMetric): array {
                if (! $row['panel_complete'] || $row['metric'] === null) {
                    return $row;
                }

                $position++;
                if ($previousMetric === null || abs($previousMetric - $row['metric']) >= 0.005) {
                    $currentRank = $position;
                }

                $row['rank'] = $currentRank;
                $previousMetric = $row['metric'];

                return $row;
            });
    }

    private function applicantPanelProgress($submissions, $assignments): array
    {
        $completedKeys = $submissions
            ->map(fn (EvaluationSubmission $submission): string => $this->submissionTaskKey($submission))
            ->unique()
            ->values();

        if ($assignments === null) {
            return [
                'expected_tasks' => $completedKeys->count(),
                'completed_tasks' => $completedKeys->count(),
                'panel_complete' => $completedKeys->isNotEmpty(),
                'panel_status' => $completedKeys->isNotEmpty() ? 'Panel complete' : 'No panel activity',
            ];
        }

        $applicantId = $submissions->first()?->form_submission_id;
        $expectedKeys = collect($assignments)
            ->filter(fn (EvaluationAssignment $assignment): bool => blank($assignment->form_submission_id)
                || (string) $assignment->form_submission_id === (string) $applicantId
            )
            ->map(fn (EvaluationAssignment $assignment): string => (string) $assignment->evaluation_id.'|'.(string) $assignment->user_id
            )
            ->filter(fn (string $key): bool => ! str_ends_with($key, '|'))
            ->unique()
            ->values();
        $completedExpected = $completedKeys->intersect($expectedKeys)->count();

        if ($expectedKeys->isEmpty()) {
            return [
                'expected_tasks' => null,
                'completed_tasks' => $completedKeys->count(),
                'panel_complete' => false,
                'panel_status' => 'Assignment baseline unavailable',
            ];
        }

        $panelComplete = $completedExpected === $expectedKeys->count();

        return [
            'expected_tasks' => $expectedKeys->count(),
            'completed_tasks' => $completedExpected,
            'panel_complete' => $panelComplete,
            'panel_status' => $panelComplete ? 'Panel complete' : 'Panel incomplete',
        ];
    }

    private function submissionTaskKey(EvaluationSubmission $submission): string
    {
        if (filled($submission->evaluation_id) && filled($submission->evaluator_id)) {
            return (string) $submission->evaluation_id.'|'.(string) $submission->evaluator_id;
        }

        return 'submission|'.(string) $submission->getKey();
    }

    private function normalisedServiceScore(EvaluationSubmission $submission): ?float
    {
        if ($submission->overall_score === null) {
            return null;
        }

        $maximum = $this->overallMax($submission);

        return $maximum && $maximum > 0
            ? round(((float) $submission->overall_score / $maximum) * 100, 2)
            : null;
    }

    private function methodResultSummary($submissions, string $method): array
    {
        if ($method === Evaluation::TYPE_SERVICES) {
            $scores = $submissions
                ->map(fn (EvaluationSubmission $submission) => $this->normalisedServiceScore($submission))
                ->filter(fn ($score) => $score !== null);

            return [
                'value' => $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : null,
                'suffix' => '%',
                'label' => 'Average submitted score',
                'detail' => $scores->count().' normalised report(s); rankings stay separate by evaluation',
            ];
        }

        $scores = $submissions->flatMap(fn (EvaluationSubmission $submission) => $submission->criteriaScores);

        if ($method === Evaluation::TYPE_GOODS) {
            $yes = $scores->where('decision', 1)->count();
            $no = $scores->where('decision', 0)->count();

            return [
                'value' => $yes,
                'suffix' => '',
                'label' => 'Yes decisions',
                'detail' => $no.' No decision(s)',
            ];
        }

        $qualified = $scores->where('decision', 2)->count();
        $average = $scores->where('decision', 1)->count();
        $notQualified = $scores->where('decision', 0)->count();

        return [
            'value' => $qualified,
            'suffix' => '',
            'label' => 'Qualified decisions',
            'detail' => $average.' Average / '.$notQualified.' Not Qualified',
        ];
    }

    private function buildSubmissionRows($submissions, string $method)
    {
        return $submissions->map(function (EvaluationSubmission $submission) use ($method): array {
            $scores = $submission->criteriaScores;

            if ($method === Evaluation::TYPE_SERVICES) {
                $maximum = $this->overallMax($submission);
                $percentage = $this->normalisedServiceScore($submission);
                $result = $submission->overall_score !== null
                    ? number_format((float) $submission->overall_score, 2)
                        .($maximum ? ' / '.number_format($maximum, 2) : '')
                        .($percentage !== null ? ' ('.number_format($percentage, 1).'%)' : '')
                    : 'Not scored';
            } elseif ($method === Evaluation::TYPE_GOODS) {
                $yes = $scores->where('decision', 1)->count();
                $no = $scores->where('decision', 0)->count();
                $result = $yes.' Yes / '.$no.' No';
            } else {
                $qualified = $scores->where('decision', 2)->count();
                $average = $scores->where('decision', 1)->count();
                $notQualified = $scores->where('decision', 0)->count();
                $result = $qualified.' Qualified / '.$average.' Average / '.$notQualified.' Not Qualified';
            }

            return [
                'submission' => $submission,
                'applicant' => $submission->applicant?->display_name ?: 'Applicant not available',
                'code' => $submission->applicant?->procurement_submission_code ?: 'N/A',
                'evaluation' => $submission->evaluation?->name ?: 'Evaluation not available',
                'phase' => filled($submission->evaluation?->evaluation_phase)
                    ? Str::headline((string) $submission->evaluation->evaluation_phase)
                    : 'Not specified',
                'evaluator' => $submission->evaluator?->name ?: 'Unassigned',
                'evaluator_email' => $submission->evaluator?->email,
                'result' => $result,
                'submitted_at' => $submission->submitted_at,
            ];
        })->values();
    }

    private function workbookRows(array $report): array
    {
        $procurement = $report['procurement'];
        $definition = $report['methodDefinition'];
        $summary = $report['summary'];
        $result = $report['resultSummary'];
        $overview = [
            ['Report field', 'Value'],
            ['Procurement', $this->safeSpreadsheetValue($procurement->title)],
            ['Reference', $this->safeSpreadsheetValue($procurement->reference_no ?: 'N/A')],
            ['Evaluation method', $definition['label']],
            ['Completed reports', $summary['total']],
            ['Applicants evaluated', $summary['applicants']],
            ['Evaluators', $summary['evaluators']],
            [$result['label'], $result['value'] !== null ? $result['value'].$result['suffix'] : 'N/A'],
            ['Generated at', now()->format('Y-m-d H:i:s')],
        ];

        if ($report['method'] === Evaluation::TYPE_SERVICES) {
            $applicants = [[
                'Evaluation', 'Phase', 'Rank', 'Submission code', 'Applicant', 'Panel average',
                'Panel status', 'Completed tasks', 'Expected tasks', 'Evaluators',
            ]];

            foreach ($report['serviceRankingGroups'] as $rankingGroup) {
                foreach ($rankingGroup['rankings'] as $row) {
                    $applicants[] = [
                        $this->safeSpreadsheetValue($rankingGroup['evaluation']->name),
                        $this->safeSpreadsheetValue($rankingGroup['phase']),
                        $row['rank'] ?: '',
                        $this->safeSpreadsheetValue($row['submission']?->procurement_submission_code ?: 'N/A'),
                        $this->safeSpreadsheetValue($row['submission']?->display_name ?: 'Applicant not available'),
                        $row['metric'] !== null ? $row['metric'].'%' : 'N/A',
                        $row['outcome'],
                        $row['completed_tasks'],
                        $row['expected_tasks'] ?? 'Not available',
                        $row['evaluators'],
                    ];
                }
            }
        } else {
            $applicants = [[
                'Submission code', 'Applicant', 'Submitted evidence', 'Decision counts',
                'Panel status', 'Completed tasks', 'Expected tasks', 'Evaluators', 'Reports',
            ]];

            foreach ($report['applicantSummaries'] as $row) {
                $counts = collect($row['counts'])->map(fn ($value, $key): string => Str::headline((string) $key).': '.$value)->implode(' / ');
                $applicants[] = [
                    $this->safeSpreadsheetValue($row['submission']?->procurement_submission_code ?: 'N/A'),
                    $this->safeSpreadsheetValue($row['submission']?->display_name ?: 'Applicant not available'),
                    $row['outcome'],
                    $counts ?: 'No decisions',
                    $row['panel_status'],
                    $row['completed_tasks'],
                    $row['expected_tasks'] ?? 'Not available',
                    $row['evaluators'],
                    $row['evaluations'],
                ];
            }
        }

        $criteria = [['Evaluation', 'Criterion', 'Maximum', 'Average / Yes', 'No / Average qualified', 'Not qualified', 'Samples']];
        foreach ($report['evaluationStats'] as $stat) {
            foreach ($stat['criteria_stats'] as $criterion) {
                if ($stat['evaluation']->isServices()) {
                    $values = [$criterion['max'], $criterion['avg'], '', '', $criterion['total']];
                } elseif ($stat['evaluation']->isGoods()) {
                    $values = ['', $criterion['yes'], $criterion['no'], '', $criterion['total']];
                } else {
                    $values = ['', $criterion['qualified'], $criterion['average_qualified'], $criterion['not_qualified'], $criterion['total']];
                }

                $criteria[] = [
                    $this->safeSpreadsheetValue($stat['evaluation']->name),
                    $this->safeSpreadsheetValue($criterion['name']),
                    ...$values,
                ];
            }
        }

        $audit = [['Submission code', 'Applicant', 'Evaluation', 'Phase', 'Evaluator', 'Result', 'Submitted at']];
        foreach ($report['submissionRows'] as $row) {
            $audit[] = [
                $this->safeSpreadsheetValue($row['code']),
                $this->safeSpreadsheetValue($row['applicant']),
                $this->safeSpreadsheetValue($row['evaluation']),
                $this->safeSpreadsheetValue($row['phase']),
                $this->safeSpreadsheetValue($row['evaluator']),
                $this->safeSpreadsheetValue($row['result']),
                $row['submitted_at']?->format('Y-m-d H:i:s') ?: '',
            ];
        }

        return [
            'Overview' => $overview,
            $report['method'] === Evaluation::TYPE_SERVICES ? 'Rankings by Evaluation' : 'Applicant Outcomes' => $applicants,
            'Criteria Analysis' => $criteria,
            'Evaluation Audit' => $audit,
        ];
    }

    private function csvRows(array $report): array
    {
        $headings = [
            'Procurement reference',
            'Evaluation method',
            'Submission code',
            'Applicant',
            'Evaluator',
            'Evaluation',
            'Phase',
            'Section',
            'Criterion',
            'Score',
            'Decision',
            'Comment',
            'Overall result',
            'Submitted at',
        ];
        $rows = (function () use ($report): \Generator {
            foreach ($report['submissionRows'] as $submissionRow) {
                $submission = $submissionRow['submission'];
                $criterionScores = $submission->criteriaScores;

                if ($criterionScores->isEmpty()) {
                    $criterionScores = collect([null]);
                }

                foreach ($criterionScores as $criterionScore) {
                    $decision = $criterionScore
                        ? $submission->evaluation?->decisionLabel($criterionScore->decision)
                        : null;

                    yield array_map(
                        fn ($value) => $this->safeSpreadsheetValue($value),
                        [
                            $report['procurement']->reference_no ?: 'N/A',
                            $report['methodDefinition']['label'],
                            $submissionRow['code'],
                            $submissionRow['applicant'],
                            $submissionRow['evaluator'],
                            $submissionRow['evaluation'],
                            $submissionRow['phase'],
                            $criterionScore?->criteria?->section?->name ?: '',
                            $criterionScore?->criteria?->name ?: '',
                            $criterionScore?->score,
                            $decision ?: '',
                            $criterionScore?->comment ?: '',
                            $submissionRow['result'],
                            $submissionRow['submitted_at']?->format('Y-m-d H:i:s') ?: '',
                        ]
                    );
                }
            }
        })();

        return [$headings, $rows];
    }

    private function eoiWorkbookRows(array $report): array
    {
        $stats = $report['stats'];
        $overview = [
            ['Report field', 'Value'],
            ['Procurement', $this->safeSpreadsheetValue($report['procurement']->title)],
            ['Reference', $this->safeSpreadsheetValue($report['procurement']->reference_no ?: 'N/A')],
            ['Evaluation method', 'Expression of Interest'],
            ['Total applicants', $stats['total_applicants']],
            ['Fully qualified', $stats['fully_qualified']],
            ['Average qualified', $stats['average_qualified']],
            ['Not qualified (final)', $stats['final_not_qualified'] ?? $stats['not_qualified']],
            ['Panel incomplete', $stats['panel_incomplete'] ?? $stats['pending']],
            ['Advancing', $stats['advance']],
            ['Panel members', $stats['panel_members']],
            ['Submitted evaluations', $stats['submitted_evaluations']],
            ['Generated at', $report['generated_at']->format('Y-m-d H:i:s')],
        ];
        $outcomes = [[
            'Submission code',
            'Applicant',
            'Outcome',
            'Panel status',
            'Panel tasks',
            'Qualified',
            'Average qualified',
            'Not qualified',
            'Can advance',
            'Next stage',
        ]];

        foreach ($report['applicants'] as $row) {
            $outcomes[] = [
                $this->safeSpreadsheetValue($row['applicant']->procurement_submission_code ?: 'N/A'),
                $this->safeSpreadsheetValue($row['applicant']->display_name),
                $row['outcome']['label'],
                $row['panel_complete'] ? 'Complete' : 'In progress',
                $row['completed_tasks'].' / '.$row['expected_tasks'],
                $row['counts']['qualified'],
                $row['counts']['average_qualified'],
                $row['counts']['not_qualified'],
                $row['can_advance'] ? 'Yes' : 'No',
                $row['next_stage'],
            ];
        }

        [$decisionHeadings, $decisionRows] = $this->eoiCsvRows($report);

        return [
            'Overview' => $overview,
            'Applicant Outcomes' => $outcomes,
            'Decision Audit' => array_merge([$decisionHeadings], $decisionRows),
        ];
    }

    private function eoiCsvRows(array $report): array
    {
        $headings = [
            'Procurement reference',
            'Submission code',
            'Applicant',
            'Panel outcome / signal',
            'Panel complete',
            'Can advance',
            'Evaluation',
            'Section',
            'Criterion',
            'Evaluator',
            'Decision',
            'Comment',
        ];
        $rows = [];

        foreach ($report['applicants'] as $applicantRow) {
            $hasAssessment = false;

            foreach ($applicantRow['evaluation_reports'] as $evaluationReport) {
                foreach ($evaluationReport['criteria'] as $criterionRow) {
                    foreach ($criterionRow['assessments'] as $assessment) {
                        $hasAssessment = true;
                        $rows[] = array_map(
                            fn ($value) => $this->safeSpreadsheetValue($value),
                            [
                                $report['procurement']->reference_no ?: 'N/A',
                                $applicantRow['applicant']->procurement_submission_code ?: 'N/A',
                                $applicantRow['applicant']->display_name,
                                $applicantRow['outcome']['label'],
                                $applicantRow['panel_complete'] ? 'Yes' : 'No',
                                $applicantRow['can_advance'] ? 'Yes' : 'No',
                                $evaluationReport['evaluation']->name,
                                $criterionRow['section']->name,
                                $criterionRow['criterion']->name,
                                $assessment['evaluator_name'],
                                $assessment['label'],
                                $assessment['comment'],
                            ]
                        );
                    }
                }
            }

            if (! $hasAssessment) {
                $rows[] = array_map(
                    fn ($value) => $this->safeSpreadsheetValue($value),
                    [
                        $report['procurement']->reference_no ?: 'N/A',
                        $applicantRow['applicant']->procurement_submission_code ?: 'N/A',
                        $applicantRow['applicant']->display_name,
                        $applicantRow['outcome']['label'],
                        $applicantRow['panel_complete'] ? 'Yes' : 'No',
                        $applicantRow['can_advance'] ? 'Yes' : 'No',
                        '', '', '', '', '', '',
                    ]
                );
            }
        }

        return [$headings, $rows];
    }

    private function safeSpreadsheetValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }

    private function streamCsv(string $filename, array $headings, iterable $rows)
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headings, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($output, $row, ',', '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function methodReportFilename(string $method, Procurement $procurement): string
    {
        $identity = $procurement->reference_no ?: $procurement->title ?: $procurement->getKey();

        return 'evaluation-'.$method.'-'.Str::slug((string) $identity);
    }

    private function downloadWorkbook(EvaluationProcurementWorkbookExport $export, string $filename)
    {
        $previousErrorReporting = error_reporting();

        try {
            error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);
            $response = Excel::download($export, $filename);
        } finally {
            error_reporting($previousErrorReporting);
        }

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        return $response;
    }

    private function applyEvaluationReportProcurementScope($query): void
    {
        $currentUser = request()->user();

        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $this->applyAssignedPortfolioScopeToProcurements($query, $currentUser);
        }
    }

    private function applyEvaluationReportSubmissionScope($query): void
    {
        $currentUser = request()->user();

        if (! $this->userHasAssignedPortfolioScope($currentUser)) {
            return;
        }

        $query->whereHas('procurement', function ($procurementQuery) use ($currentUser) {
            $this->applyAssignedPortfolioScopeToProcurements($procurementQuery, $currentUser);
        });
    }

    private function assertEvaluationProcurementScope(Procurement $procurement): void
    {
        $currentUser = request()->user();

        if (! $this->userHasAssignedPortfolioScope($currentUser)) {
            return;
        }

        abort_unless(
            $this->procurementIsInAssignedPortfolio($procurement, $currentUser),
            403,
            'This evaluation report is not assigned to your portfolio.'
        );
    }

    private function assertEvaluationSubmissionScope(EvaluationSubmission $submission): void
    {
        abort_unless(
            $submission->submitted_at,
            404,
            'A completed evaluation report was not found.'
        );

        $currentUser = request()->user();

        if (! $this->userHasAssignedPortfolioScope($currentUser)) {
            return;
        }

        $submission->loadMissing('procurement');

        abort_unless(
            $submission->procurement && $this->procurementIsInAssignedPortfolio($submission->procurement, $currentUser),
            403,
            'This evaluation report is not assigned to your portfolio.'
        );
    }

    private function overallMax(EvaluationSubmission $submission): ?float
    {
        $evaluation = $submission->evaluation;

        if (! $evaluation?->usesNumericScoring()) {
            return null;
        }

        return round(
            (float) $evaluation->sections->sum(
                fn ($section) => (float) $section->criteria->sum('max_score')
            ),
            2
        );
    }

    private function buildSummary($submissions): array
    {
        $total = $submissions->count();
        $procurements = $submissions->pluck('procurement_id')->filter()->unique()->count();
        $evaluators = $submissions->pluck('evaluator_id')->filter()->unique()->count();
        $avgOverall = $this->numericOverallScores($submissions)->avg();

        return [
            'total' => $total,
            'procurements' => $procurements,
            'evaluators' => $evaluators,
            'avg_overall' => $avgOverall !== null ? round($avgOverall, 2) : null,
        ];
    }

    private function buildEvaluatorBreakdown($submissions)
    {
        return $submissions
            ->groupBy(fn ($submission) => $submission->evaluator_id ?: 'unassigned')
            ->map(function ($group) {
                $avg = $this->numericOverallScores($group)->avg();
                $evaluator = $group->first()?->evaluator;

                return [
                    'name' => $evaluator?->name ?? 'Unassigned',
                    'email' => $evaluator?->email,
                    'total' => $group->count(),
                    'avg_overall' => $avg !== null ? round($avg, 2) : null,
                ];
            })
            ->values();
    }

    private function buildApplicantRankings($submissions)
    {
        $rank = 0;

        return $submissions
            ->groupBy('form_submission_id')
            ->map(function ($group) {
                $scores = $this->numericOverallScores($group);
                $average = $scores->isNotEmpty() ? round($scores->avg(), 2) : null;
                $highest = $scores->isNotEmpty() ? round($scores->max(), 2) : null;
                $lowest = $scores->isNotEmpty() ? round($scores->min(), 2) : null;

                return [
                    'submission' => $group->first()->applicant,
                    'average' => $average,
                    'highest' => $highest,
                    'lowest' => $lowest,
                    'spread' => $highest !== null && $lowest !== null
                        ? round($highest - $lowest, 2)
                        : null,
                    'evaluators' => $group->pluck('evaluator_id')->filter()->unique()->count(),
                    'evaluations' => $group->count(),
                ];
            })
            ->sortByDesc(fn ($row) => $row['average'] ?? -1)
            ->values()
            ->map(function ($row) use (&$rank) {
                $row['rank'] = $row['average'] !== null ? ++$rank : null;

                return $row;
            });
    }

    private function buildProcurementStats($submissions)
    {
        return $submissions
            ->groupBy('procurement_id')
            ->map(function ($group) {
                $procurement = $group->first()->procurement;
                $avg = $this->numericOverallScores($group)->avg();

                return [
                    'procurement' => $procurement,
                    'total' => $group->count(),
                    'evaluators' => $group->pluck('evaluator_id')->filter()->unique()->count(),
                    'avg_overall' => $avg !== null ? round($avg, 2) : null,
                ];
            })
            ->values();
    }

    private function buildEvaluationStats($submissions)
    {
        return $submissions
            ->filter(fn ($submission) => $submission->evaluation !== null)
            ->groupBy('evaluation_id')
            ->map(function ($group) {
                $evaluation = $group->first()->evaluation;
                $evaluation->loadMissing('sections.criteria');

                $criteriaStats = $evaluation->sections
                    ->flatMap(fn ($section) => $section->criteria)
                    ->map(function ($criterion) use ($group, $evaluation) {
                        $scores = $group->flatMap(function ($submission) use ($criterion) {
                            return $submission->criteriaScores
                                ->where('evaluation_criteria_id', $criterion->id);
                        });

                        if ($evaluation->usesCategoricalDecisions()) {
                            $decisions = collect($evaluation->decisionOptions())
                                ->map(function (string $label, int $decision) use ($scores) {
                                    return [
                                        'value' => $decision,
                                        'label' => $label,
                                        'count' => $scores->where('decision', $decision)->count(),
                                    ];
                                })
                                ->values();
                            $total = $decisions->sum('count');

                            if ($evaluation->isGoods()) {
                                $yes = (int) data_get($decisions->firstWhere('value', 1), 'count', 0);
                                $no = (int) data_get($decisions->firstWhere('value', 0), 'count', 0);
                                $rate = $total > 0 ? round(($yes / $total) * 100, 1) : 0;

                                return [
                                    'name' => $criterion->name,
                                    'total' => $total,
                                    'decisions' => $decisions->all(),
                                    'yes' => $yes,
                                    'no' => $no,
                                    'rate' => $rate,
                                ];
                            }

                            return [
                                'name' => $criterion->name,
                                'total' => $total,
                                'decisions' => $decisions->all(),
                                'qualified' => (int) data_get($decisions->firstWhere('value', 2), 'count', 0),
                                'average_qualified' => (int) data_get($decisions->firstWhere('value', 1), 'count', 0),
                                'not_qualified' => (int) data_get($decisions->firstWhere('value', 0), 'count', 0),
                            ];
                        }

                        $avg = $scores->count() ? round($scores->avg('score'), 2) : 0;

                        return [
                            'name' => $criterion->name,
                            'max' => $criterion->max_score,
                            'avg' => $avg,
                            'total' => $scores->count(),
                        ];
                    });

                $avgOverall = $evaluation->usesNumericScoring()
                    ? $this->numericOverallScores($group)->avg()
                    : null;

                return [
                    'evaluation' => $evaluation,
                    'type' => $evaluation->type,
                    'total' => $group->count(),
                    'avg_overall' => $avgOverall !== null ? round($avgOverall, 2) : null,
                    'criteria_stats' => $criteriaStats,
                ];
            })
            ->values();
    }

    private function numericOverallScores($submissions)
    {
        return $submissions
            ->filter(fn ($submission) => $submission->evaluation?->usesNumericScoring())
            ->pluck('overall_score')
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (float) $score)
            ->values();
    }
}
