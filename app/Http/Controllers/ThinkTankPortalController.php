<?php

namespace App\Http\Controllers;

use App\Mail\ThinkTankResearchQascSubmitted;
use App\Models\ConsortiumActivityReport;
use App\Models\ConsortiumDisbursementRequest;
use App\Models\ConsortiumExpenseReport;
use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumRiskFlag;
use App\Models\ConsortiumThinkTank;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\SystemAuditLog;
use App\Models\ThinkTankProcurementPlan;
use App\Models\ThinkTankProcurementReview;
use App\Models\ThinkTankResearchOutput;
use App\Support\IpGeo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ThinkTankPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        return view('think-tank.dashboard', $this->dashboardPayload($request));
    }

    public function downloadDashboardReport(Request $request)
    {
        $payload = $this->dashboardPayload($request);
        $filename = 'think-tank-dashboard-' . Str::slug($payload['member']->name) . '-' . now()->format('Ymd-His') . '.pdf';

        return Pdf::loadView('think-tank.dashboard-report-pdf', $payload)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function dashboardPayload(Request $request): array
    {
        $member = $this->member($request);
        $member->load(['consortium.funder', 'fundAllocations', 'disbursementRequests', 'reports', 'researchOutputs']);

        $today = CarbonImmutable::now()->startOfDay();
        $reportingPeriodStart = $today->startOfMonth();
        $reportingPeriodEnd = $today->endOfMonth();
        $monthlyReportDue = $reportingPeriodEnd->addDays(7)->startOfDay();
        $monthlyReportDaysLeft = $today->diffInDays($monthlyReportDue, false);
        $dashboardFilter = $this->dashboardFilter($request);
        $periodStart = $dashboardFilter['start'];
        $periodEnd = $dashboardFilter['end'];

        $applyPeriod = function ($query, string $column = 'created_at') use ($periodStart, $periodEnd) {
            return $query
                ->when($periodStart, fn ($periodQuery) => $periodQuery->whereDate($column, '>=', $periodStart))
                ->when($periodEnd, fn ($periodQuery) => $periodQuery->whereDate($column, '<=', $periodEnd));
        };

        $applyReportPeriod = function ($query) use ($periodStart, $periodEnd) {
            if (! $periodStart && ! $periodEnd) {
                return $query;
            }

            return $query->where(function ($periodQuery) use ($periodStart, $periodEnd) {
                if ($periodStart && $periodEnd) {
                    $periodQuery->whereBetween('reporting_period_start', [$periodStart, $periodEnd])
                        ->orWhereBetween('reporting_period_end', [$periodStart, $periodEnd])
                        ->orWhereBetween('submitted_at', [$periodStart, $periodEnd]);
                } elseif ($periodStart) {
                    $periodQuery->whereDate('reporting_period_start', '>=', $periodStart)
                        ->orWhereDate('reporting_period_end', '>=', $periodStart)
                        ->orWhereDate('submitted_at', '>=', $periodStart);
                } elseif ($periodEnd) {
                    $periodQuery->whereDate('reporting_period_start', '<=', $periodEnd)
                        ->orWhereDate('reporting_period_end', '<=', $periodEnd)
                        ->orWhereDate('submitted_at', '<=', $periodEnd);
                }
            });
        };

        $procurementsBase = Procurement::where('think_tank_member_id', $member->id);
        $filteredProcurementsBase = $applyPeriod(Procurement::where('think_tank_member_id', $member->id));
        $procurementIds = (clone $filteredProcurementsBase)->pluck('id');
        $purchaseOrderQuery = $applyPeriod(
            ProcurementPurchaseOrder::query()
                ->where('think_tank_member_id', $member->id)
                ->where('po_type', 'think_tank_transfer'),
            'issued_at'
        );
        $allocationQuery = $applyPeriod(ConsortiumFundAllocation::where('think_tank_member_id', $member->id));
        $disbursementQuery = $applyPeriod(ConsortiumDisbursementRequest::where('think_tank_member_id', $member->id));
        $actualPaymentQuery = $applyPeriod(
            ProcurementDisbursement::query()
                ->where('think_tank_member_id', $member->id)
                ->whereNotNull('paid_at')
                ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
                ->whereHas('purchaseOrder', fn ($query) => $query->where('po_type', 'think_tank_transfer')),
            'paid_at'
        );
        $expenseQuery = $applyPeriod(ConsortiumExpenseReport::where('think_tank_member_id', $member->id), 'expense_date');
        $reportQuery = $applyReportPeriod(ConsortiumActivityReport::where('think_tank_member_id', $member->id));
        $researchQuery = $applyPeriod(ThinkTankResearchOutput::where('think_tank_member_id', $member->id), 'submitted_at');
        $planQuery = $applyPeriod(ThinkTankProcurementPlan::where('think_tank_member_id', $member->id));

        $poAllocated = (float) (clone $purchaseOrderQuery)->sum('amount');
        $actualPaid = (float) (clone $actualPaymentQuery)->sum('amount');
        $poUnpaid = max($poAllocated - $actualPaid, 0);

        $metrics = [
            'allocated' => $poAllocated,
            'po_allocated' => $poAllocated,
            'disbursed' => $actualPaid,
            'po_unpaid' => $poUnpaid,
            'requested' => (clone $disbursementQuery)->sum('amount_requested'),
            'spent' => (clone $expenseQuery)->sum('amount'),
            'reports' => (clone $reportQuery)->count(),
            'research' => (clone $researchQuery)->count(),
            'procurement_plans' => (clone $planQuery)->count(),
            'opportunities' => $procurementIds->count(),
            'applications' => FormSubmission::whereIn('procurement_id', $procurementIds)->count(),
            'selected' => (clone $filteredProcurementsBase)->whereNotNull('awarded_submission_id')->count(),
            'open_risks' => ConsortiumRiskFlag::where('think_tank_member_id', $member->id)->where('status', 'open')->count(),
        ];

        $metrics['balance'] = max(0, (float) $metrics['disbursed'] - (float) $metrics['spent']);
        $metrics['utilization'] = (float) $metrics['disbursed'] > 0
            ? round(((float) $metrics['spent'] / (float) $metrics['disbursed']) * 100, 1)
            : 0;

        $transferSummaryQuery = $applyPeriod(
            ProcurementDisbursement::query()
                ->where('think_tank_member_id', $member->id)
                ->whereNotNull('paid_at')
                ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
                ->whereHas('purchaseOrder', fn ($query) => $query->where('po_type', 'think_tank_transfer')),
            'paid_at'
        );
        $receiptSent = (float) (clone $transferSummaryQuery)->sum('amount');
        $receiptConfirmed = (float) (clone $transferSummaryQuery)
            ->where('recipient_confirmation_status', 'confirmed')
            ->sum('amount');
        $receiptSummary = [
            'sent' => $receiptSent,
            'confirmed' => $receiptConfirmed,
            'pending' => max(0, $receiptSent - $receiptConfirmed),
            'transfer_count' => (clone $transferSummaryQuery)->count(),
            'confirmed_count' => (clone $transferSummaryQuery)->where('recipient_confirmation_status', 'confirmed')->count(),
            'rate' => $receiptSent > 0 ? round(($receiptConfirmed / $receiptSent) * 100, 1) : 0,
        ];

        $transferRecords = (clone $transferSummaryQuery)
            ->with(['purchaseOrder.purchaseRequest', 'purchaseOrder.budgetCommitment.purchaseRequest'])
            ->latest('paid_at')
            ->limit(8)
            ->get();

        $purchaseOrderRecords = (clone $purchaseOrderQuery)
            ->with([
                'purchaseRequest',
                'budgetCommitment.purchaseRequest',
                'disbursements' => fn ($query) => $query
                    ->whereNotNull('paid_at')
                    ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
                    ->latest('paid_at')
                    ->latest(),
            ])
            ->latest('issued_at')
            ->latest()
            ->limit(8)
            ->get();

        $recentProcurements = Procurement::withCount('submissions')
            ->where('think_tank_member_id', $member->id)
            ->when($periodStart, fn ($query) => $query->whereDate('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($query) => $query->whereDate('created_at', '<=', $periodEnd))
            ->latest()
            ->limit(6)
            ->get();

        $recentReports = (clone $reportQuery)->latest()->limit(5)->get();
        $recentResearch = (clone $researchQuery)->latest()->limit(5)->get();

        $fundedActivities = (clone $allocationQuery)
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (ConsortiumFundAllocation $allocation) {
                $disbursed = (float) $allocation->amount_disbursed;
                $spent = (float) $allocation->amount_spent;

                return [
                    'budget_line' => $allocation->budget_line,
                    'allocated' => (float) $allocation->amount_allocated,
                    'disbursed' => $disbursed,
                    'spent' => $spent,
                    'status' => $allocation->status,
                    'utilization' => $disbursed > 0 ? min(100, round(($spent / $disbursed) * 100, 1)) : 0,
                ];
            });

        $reportSubmittedThisPeriod = ConsortiumActivityReport::where('think_tank_member_id', $member->id)
            ->where(function ($query) use ($reportingPeriodStart, $reportingPeriodEnd) {
                $query->whereBetween('reporting_period_start', [$reportingPeriodStart, $reportingPeriodEnd])
                    ->orWhereBetween('reporting_period_end', [$reportingPeriodStart, $reportingPeriodEnd])
                    ->orWhereBetween('submitted_at', [$reportingPeriodStart, $reportingPeriodEnd]);
            })
            ->exists();

        $closingProcurements = Procurement::withCount('submissions')
            ->where('think_tank_member_id', $member->id)
            ->where('status', 'published')
            ->whereDate('application_end_date', '>=', $today)
            ->when($periodStart, fn ($query) => $query->whereDate('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($query) => $query->whereDate('created_at', '<=', $periodEnd))
            ->orderBy('application_end_date')
            ->limit(4)
            ->get();

        $pendingResearchCount = (clone $researchQuery)
            ->whereIn('status', ['submitted', 'pending', 'under_review'])
            ->count();

        $pendingReportsCount = (clone $reportQuery)
            ->whereIn('status', ['submitted', 'pending', 'under_review'])
            ->count();

        $upcomingActivities = collect([
            [
                'type' => $reportSubmittedThisPeriod ? 'complete' : ($monthlyReportDaysLeft <= 3 ? 'urgent' : 'due'),
                'title' => $reportSubmittedThisPeriod ? 'Monthly activity report submitted' : 'Submit this month\'s activity report',
                'meta' => $reportSubmittedThisPeriod
                    ? 'The Secretariat has a report for ' . $today->format('F Y') . '.'
                    : 'Due ' . $monthlyReportDue->format('M d, Y') . ' to the ATTP Secretariat.',
                'value' => $reportSubmittedThisPeriod ? 'Done' : ($monthlyReportDaysLeft >= 0 ? $monthlyReportDaysLeft . ' days left' : abs($monthlyReportDaysLeft) . ' days late'),
                'route' => route('think-tank.reports', $this->portalRouteParams($request, $member)),
            ],
            [
                'type' => $pendingReportsCount > 0 ? 'review' : 'complete',
                'title' => 'Secretariat report review',
                'meta' => $pendingReportsCount > 0 ? 'Submitted reports awaiting Secretariat action.' : 'No report is waiting for review.',
                'value' => number_format($pendingReportsCount),
                'route' => route('think-tank.reports', $this->portalRouteParams($request, $member)),
            ],
            [
                'type' => $pendingResearchCount > 0 ? 'review' : 'info',
                'title' => 'Research awaiting clearance',
                'meta' => 'Outputs submitted for Secretariat visibility and approval.',
                'value' => number_format($pendingResearchCount),
                'route' => route('think-tank.research', $this->portalRouteParams($request, $member)),
            ],
            [
                'type' => $metrics['open_risks'] > 0 ? 'urgent' : 'complete',
                'title' => 'Open oversight risks',
                'meta' => $metrics['open_risks'] > 0 ? 'Resolve or update mitigation notes.' : 'No open risk flags.',
                'value' => number_format($metrics['open_risks']),
                'route' => route('think-tank.dashboard', $this->portalRouteParams($request, $member)),
            ],
        ]);

        foreach ($closingProcurements as $procurement) {
            $daysLeft = $procurement->application_end_date
                ? $today->diffInDays($procurement->application_end_date->startOfDay(), false)
                : null;

            $upcomingActivities->push([
                'type' => $daysLeft !== null && $daysLeft <= 3 ? 'urgent' : 'procurement',
                'title' => $procurement->title,
                'meta' => 'Procurement closes ' . ($procurement->application_end_date?->format('M d, Y') ?? 'soon') . ' with ' . number_format($procurement->submissions_count) . ' applications.',
                'value' => $daysLeft === null ? 'Open' : ($daysLeft >= 0 ? $daysLeft . ' days left' : 'Closed'),
                'route' => route('think-tank.procurement.submissions', array_merge($this->portalRouteParams($request, $member), ['procurement' => $procurement])),
            ]);
        }

        $lastSixMonths = collect(range(5, 0))->map(fn ($monthsAgo) => $today->subMonths($monthsAgo));
        $allReports = (clone $reportQuery)->get();
        $allProcurements = (clone $filteredProcurementsBase)->get();
        $allResearch = (clone $researchQuery)->get();
        $reportStatusCounts = $allReports->groupBy(fn ($report) => $report->status ?: 'submitted')->map->count();
        $researchStatusCounts = $allResearch->groupBy(fn ($output) => $output->status ?: 'submitted')->map->count();
        $procurementStatusCounts = $allProcurements->groupBy(fn ($procurement) => $procurement->status ?: 'draft')->map->count();

        $chartData = [
            'finance' => [
                'labels' => ['PO Allocated', 'Paid from POs', 'PO Unpaid', 'Spent', 'Requested'],
                'values' => [
                    round((float) $metrics['po_allocated'], 2),
                    round((float) $metrics['disbursed'], 2),
                    round((float) $metrics['po_unpaid'], 2),
                    round((float) $metrics['spent'], 2),
                    round((float) $metrics['requested'], 2),
                ],
            ],
            'reports' => [
                'labels' => $lastSixMonths->map(fn ($date) => $date->format('M'))->values(),
                'values' => $lastSixMonths->map(function ($date) use ($allReports) {
                    return $allReports->filter(function ($report) use ($date) {
                        $reportDate = $report->submitted_at ?? $report->created_at;

                        return $reportDate && $reportDate->format('Y-m') === $date->format('Y-m');
                    })->count();
                })->values(),
            ],
            'procurements' => [
                'labels' => ['Draft', 'Published', 'Awarded', 'Closed'],
                'values' => collect(['draft', 'published', 'awarded', 'closed'])
                    ->map(fn ($status) => $allProcurements->where('status', $status)->count())
                    ->values(),
            ],
            'research' => [
                'labels' => $allResearch->groupBy(fn ($output) => str_replace('_', ' ', ucfirst($output->output_type ?? 'Research')))->keys()->values(),
                'values' => $allResearch->groupBy(fn ($output) => str_replace('_', ' ', ucfirst($output->output_type ?? 'Research')))->map->count()->values(),
            ],
            'delivery' => [
                'labels' => ['Reports', 'Research', 'Plans', 'Opportunities', 'Applications'],
                'values' => [
                    (int) $metrics['reports'],
                    (int) $metrics['research'],
                    (int) $metrics['procurement_plans'],
                    (int) $metrics['opportunities'],
                    (int) $metrics['applications'],
                ],
            ],
            'receipts' => [
                'labels' => ['Confirmed', 'Awaiting receipt'],
                'values' => [
                    round($receiptSummary['confirmed'], 2),
                    round($receiptSummary['pending'], 2),
                ],
            ],
        ];

        $membersForSearch = $request->user() && ($request->user()->isSuperAdmin() || $request->user()->isAdmin())
            ? ConsortiumThinkTank::with('consortium:id,name')
                ->orderBy('name')
                ->get(['id', 'consortium_id', 'name', 'country', 'role', 'status'])
            : collect([$member]);

        $portalRouteParams = $this->portalRouteParams($request, $member);
        $dashboardQueryParams = collect([
            'think_tank_member_id' => $portalRouteParams['think_tank_member_id'] ?? null,
            'filter_month' => $dashboardFilter['month'] ?? null,
            'filter_year' => $dashboardFilter['year'] ?? null,
            'date_from' => $dashboardFilter['date_from'] ?? null,
            'date_to' => $dashboardFilter['date_to'] ?? null,
        ])->filter(fn ($value) => filled($value))->all();

        return compact(
            'member',
            'metrics',
            'recentProcurements',
            'recentReports',
            'recentResearch',
            'purchaseOrderRecords',
            'transferRecords',
            'fundedActivities',
            'upcomingActivities',
            'monthlyReportDue',
            'monthlyReportDaysLeft',
            'reportSubmittedThisPeriod',
            'chartData',
            'dashboardFilter',
            'receiptSummary',
            'reportStatusCounts',
            'researchStatusCounts',
            'procurementStatusCounts',
            'membersForSearch',
            'portalRouteParams',
            'dashboardQueryParams'
        );
    }

    public function reports(Request $request)
    {
        return view('think-tank.reports', $this->reportsPayload($request));
    }

    public function uploadReportFinding(Request $request)
    {
        $payload = $this->reportsPayload($request);
        $researchPayload = $this->researchPayload($request);

        return view('think-tank.upload-report-finding', array_merge($payload, [
            'researchRecords' => $researchPayload['outputRecords'],
            'qascChecklist' => $researchPayload['qascChecklist'],
            'qascConsortiumOptions' => $researchPayload['qascConsortiumOptions'],
            'qascTrackOptions' => $researchPayload['qascTrackOptions'],
            'qascLanguageOptions' => $researchPayload['qascLanguageOptions'],
        ]));
    }

    public function downloadReports(Request $request)
    {
        $payload = $this->reportsPayload($request);
        $filename = 'think-tank-reports-' . Str::slug($payload['member']->name) . '-' . now()->format('Ymd-His') . '.pdf';

        $this->auditAction('think_tank.reports.downloaded', 'Think tank reports dashboard downloaded', [
            'think_tank_member_id' => $payload['member']->id,
            'think_tank' => $payload['member']->name,
            'filters' => $payload['reportsQueryParams'],
        ]);

        return Pdf::loadView('think-tank.reports-report-pdf', $payload)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function reportsPayload(Request $request): array
    {
        $member = $this->member($request);
        $today = CarbonImmutable::now()->startOfDay();
        $monthlyReportDue = $today->endOfMonth()->addDays(7)->startOfDay();
        $monthlyReportDaysLeft = $today->diffInDays($monthlyReportDue, false);
        $dashboardFilter = $this->dashboardFilter($request);
        $periodStart = $dashboardFilter['start'];
        $periodEnd = $dashboardFilter['end'];
        $statusFilter = trim((string) $request->input('status', ''));

        $applyReportPeriod = function ($query) use ($periodStart, $periodEnd) {
            if (! $periodStart && ! $periodEnd) {
                return $query;
            }

            return $query->where(function ($periodQuery) use ($periodStart, $periodEnd) {
                if ($periodStart && $periodEnd) {
                    $periodQuery->whereBetween('reporting_period_start', [$periodStart, $periodEnd])
                        ->orWhereBetween('reporting_period_end', [$periodStart, $periodEnd])
                        ->orWhereBetween('submitted_at', [$periodStart, $periodEnd]);
                } elseif ($periodStart) {
                    $periodQuery->whereDate('reporting_period_start', '>=', $periodStart)
                        ->orWhereDate('reporting_period_end', '>=', $periodStart)
                        ->orWhereDate('submitted_at', '>=', $periodStart);
                } elseif ($periodEnd) {
                    $periodQuery->whereDate('reporting_period_start', '<=', $periodEnd)
                        ->orWhereDate('reporting_period_end', '<=', $periodEnd)
                        ->orWhereDate('submitted_at', '<=', $periodEnd);
                }
            });
        };

        $reportsBase = $applyReportPeriod(ConsortiumActivityReport::where('think_tank_member_id', $member->id))
            ->with(['workplan', 'evidence'])
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter));

        $reportRecords = (clone $reportsBase)->latest()->get();
        $reports = (clone $reportsBase)->latest()->paginate(15)->withQueryString();

        $workplans = $member->consortium->workplans()->orderBy('title')->get();
        $statusCounts = $reportRecords->groupBy(fn ($report) => $report->status ?: 'submitted')->map->count();
        $evidenceCount = $reportRecords->sum(fn ($report) => $report->evidence->count());

        $reportStats = [
            'total' => $reportRecords->count(),
            'submitted' => (int) ($statusCounts->get('submitted') ?? 0),
            'approved' => (int) ($statusCounts->get('approved') ?? 0),
            'revisions' => (int) ($statusCounts->get('revisions_requested') ?? 0),
            'rejected' => (int) ($statusCounts->get('rejected') ?? 0),
            'average_progress' => round((float) $reportRecords->avg('progress_percent'), 1),
            'funds_spent' => (float) $reportRecords->sum('funds_spent'),
            'evidence_count' => $evidenceCount,
            'with_evidence' => $reportRecords->filter(fn ($report) => $report->evidence->isNotEmpty())->count(),
            'without_evidence' => $reportRecords->filter(fn ($report) => $report->evidence->isEmpty())->count(),
        ];

        $lastSixMonths = collect(range(5, 0))->map(fn ($monthsAgo) => $today->subMonths($monthsAgo));
        $monthlyCounts = $lastSixMonths->map(function ($date) use ($reportRecords) {
            return $reportRecords->filter(function ($report) use ($date) {
                $reportDate = $report->submitted_at ?? $report->created_at;

                return $reportDate && $reportDate->format('Y-m') === $date->format('Y-m');
            })->count();
        })->values();

        $monthlyFunds = $lastSixMonths->map(function ($date) use ($reportRecords) {
            return round((float) $reportRecords->filter(function ($report) use ($date) {
                $reportDate = $report->submitted_at ?? $report->created_at;

                return $reportDate && $reportDate->format('Y-m') === $date->format('Y-m');
            })->sum('funds_spent'), 2);
        })->values();

        $monthlyProgress = $lastSixMonths->map(function ($date) use ($reportRecords) {
            $reportsInMonth = $reportRecords->filter(function ($report) use ($date) {
                $reportDate = $report->submitted_at ?? $report->created_at;

                return $reportDate && $reportDate->format('Y-m') === $date->format('Y-m');
            });

            return round((float) $reportsInMonth->avg('progress_percent'), 1);
        })->values();

        $chartData = [
            'status' => [
                'labels' => $statusCounts->keys()->map(fn ($status) => ucfirst(str_replace('_', ' ', $status)))->values(),
                'values' => $statusCounts->values(),
            ],
            'timeline' => [
                'labels' => $lastSixMonths->map(fn ($date) => $date->format('M'))->values(),
                'counts' => $monthlyCounts,
                'progress' => $monthlyProgress,
            ],
            'funds' => [
                'labels' => $lastSixMonths->map(fn ($date) => $date->format('M'))->values(),
                'values' => $monthlyFunds,
            ],
            'evidence' => [
                'labels' => ['With evidence', 'Without evidence'],
                'values' => [$reportStats['with_evidence'], $reportStats['without_evidence']],
            ],
        ];

        if ($chartData['status']['labels']->isEmpty()) {
            $chartData['status']['labels'] = collect(['No reports']);
            $chartData['status']['values'] = collect([0]);
        }

        $membersForSearch = $request->user() && ($request->user()->isSuperAdmin() || $request->user()->isAdmin())
            ? ConsortiumThinkTank::with('consortium:id,name')
                ->orderBy('name')
                ->get(['id', 'consortium_id', 'name', 'country', 'role', 'status'])
            : collect([$member]);

        $portalRouteParams = $this->portalRouteParams($request, $member);
        $reportsQueryParams = collect([
            'think_tank_member_id' => $portalRouteParams['think_tank_member_id'] ?? null,
            'filter_month' => $dashboardFilter['month'] ?? null,
            'filter_year' => $dashboardFilter['year'] ?? null,
            'date_from' => $dashboardFilter['date_from'] ?? null,
            'date_to' => $dashboardFilter['date_to'] ?? null,
            'status' => $statusFilter ?: null,
        ])->filter(fn ($value) => filled($value))->all();

        return compact(
            'member',
            'reports',
            'reportRecords',
            'workplans',
            'reportStats',
            'monthlyReportDue',
            'monthlyReportDaysLeft',
            'dashboardFilter',
            'statusFilter',
            'statusCounts',
            'chartData',
            'membersForSearch',
            'portalRouteParams',
            'reportsQueryParams'
        );
    }

    public function storeReport(Request $request)
    {
        $member = $this->member($request);

        app(ConsortiumOperationsController::class)->storeReport($request->merge([
            'think_tank_member_id' => $member->id,
        ]), $member->consortium);

        return back()->with('success', 'Report submitted to the ATTP Secretariat.');
    }

    public function research(Request $request)
    {
        return view('think-tank.research', $this->researchPayload($request));
    }

    public function downloadResearch(Request $request)
    {
        $payload = $this->researchPayload($request);
        $filename = 'think-tank-research-' . Str::slug($payload['member']->name) . '-' . now()->format('Ymd-His') . '.pdf';

        $this->auditAction('think_tank.research.downloaded', 'Think tank research dashboard downloaded', [
            'think_tank_member_id' => $payload['member']->id,
            'think_tank' => $payload['member']->name,
            'filters' => $payload['researchQueryParams'],
        ]);

        return Pdf::loadView('think-tank.research-report-pdf', $payload)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function researchPayload(Request $request): array
    {
        $member = $this->member($request);
        $dashboardFilter = $this->dashboardFilter($request);
        $periodStart = $dashboardFilter['start'];
        $periodEnd = $dashboardFilter['end'];
        $statusFilter = trim((string) $request->input('status', ''));
        $typeFilter = trim((string) $request->input('output_type', ''));
        $keyword = trim((string) $request->input('q', ''));

        $applyPeriod = function ($query) use ($periodStart, $periodEnd) {
            if (! $periodStart && ! $periodEnd) {
                return $query;
            }

            return $query->where(function ($periodQuery) use ($periodStart, $periodEnd) {
                if ($periodStart && $periodEnd) {
                    $periodQuery->whereBetween('submitted_at', [$periodStart, $periodEnd])
                        ->orWhereBetween('published_on', [$periodStart, $periodEnd])
                        ->orWhereBetween('created_at', [$periodStart, $periodEnd]);
                } elseif ($periodStart) {
                    $periodQuery->whereDate('submitted_at', '>=', $periodStart)
                        ->orWhereDate('published_on', '>=', $periodStart)
                        ->orWhereDate('created_at', '>=', $periodStart);
                } elseif ($periodEnd) {
                    $periodQuery->whereDate('submitted_at', '<=', $periodEnd)
                        ->orWhereDate('published_on', '<=', $periodEnd)
                        ->orWhereDate('created_at', '<=', $periodEnd);
                }
            });
        };

        $outputsBase = $applyPeriod(ThinkTankResearchOutput::where('think_tank_member_id', $member->id))
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter))
            ->when($typeFilter !== '', fn ($query) => $query->where('output_type', $typeFilter))
            ->when($keyword !== '', function ($query) use ($keyword) {
                $search = '%' . $keyword . '%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', $search)
                        ->orWhere('abstract', 'like', $search)
                        ->orWhere('external_url', 'like', $search);
                });
            });

        $outputRecords = (clone $outputsBase)->latest()->get();
        $outputs = (clone $outputsBase)->latest()->paginate(15)->withQueryString();
        $statusCounts = $outputRecords->groupBy(fn ($output) => $output->status ?: 'submitted')->map->count();
        $typeCounts = $outputRecords->groupBy(fn ($output) => $output->output_type ?: 'research')->map->count();

        $researchStats = [
            'total' => $outputRecords->count(),
            'submitted' => (int) ($statusCounts->get('submitted') ?? 0),
            'approved' => (int) ($statusCounts->get('approved') ?? 0),
            'rejected' => (int) ($statusCounts->get('rejected') ?? 0),
            'revisions' => (int) ($statusCounts->get('revisions_requested') ?? 0),
            'with_files' => $outputRecords->whereNotNull('file_path')->count(),
            'with_links' => $outputRecords->whereNotNull('external_url')->count(),
            'published' => $outputRecords->filter(fn ($output) => filled($output->published_on))->count(),
            'draft_unpublished' => $outputRecords->filter(fn ($output) => blank($output->published_on))->count(),
        ];

        $outputTypes = $typeCounts
            ->map(fn ($total, $type) => (object) ['output_type' => $type, 'total' => $total])
            ->sortByDesc('total')
            ->values();

        $lastSixMonths = collect(range(5, 0))->map(fn ($monthsAgo) => CarbonImmutable::now()->startOfDay()->subMonths($monthsAgo));
        $monthlyCounts = $lastSixMonths->map(function ($date) use ($outputRecords) {
            return $outputRecords->filter(function ($output) use ($date) {
                $outputDate = $output->submitted_at ?? $output->created_at;

                return $outputDate && $outputDate->format('Y-m') === $date->format('Y-m');
            })->count();
        })->values();

        $chartData = [
            'types' => [
                'labels' => $typeCounts->keys()->map(fn ($type) => ucfirst(str_replace('_', ' ', $type)))->values(),
                'values' => $typeCounts->values(),
            ],
            'status' => [
                'labels' => $statusCounts->keys()->map(fn ($status) => ucfirst(str_replace('_', ' ', $status)))->values(),
                'values' => $statusCounts->values(),
            ],
            'timeline' => [
                'labels' => $lastSixMonths->map(fn ($date) => $date->format('M'))->values(),
                'values' => $monthlyCounts,
            ],
            'access' => [
                'labels' => ['Attached file', 'External link', 'No file/link'],
                'values' => [
                    $researchStats['with_files'],
                    $researchStats['with_links'],
                    max(0, $researchStats['total'] - $researchStats['with_files'] - $researchStats['with_links']),
                ],
            ],
            'publication' => [
                'labels' => ['Publication date set', 'Publication date missing'],
                'values' => [$researchStats['published'], $researchStats['draft_unpublished']],
            ],
        ];

        foreach (['types', 'status'] as $chartKey) {
            if ($chartData[$chartKey]['labels']->isEmpty()) {
                $chartData[$chartKey]['labels'] = collect(['No research']);
                $chartData[$chartKey]['values'] = collect([0]);
            }
        }

        $membersForSearch = $request->user() && ($request->user()->isSuperAdmin() || $request->user()->isAdmin())
            ? ConsortiumThinkTank::with('consortium:id,name')
                ->orderBy('name')
                ->get(['id', 'consortium_id', 'name', 'country', 'role', 'status'])
            : collect([$member]);

        $portalRouteParams = $this->portalRouteParams($request, $member);
        $qascChecklist = $this->qascChecklistDefinitions();
        $qascConsortiumOptions = $this->qascConsortiumOptions();
        $qascTrackOptions = $this->qascTrackOptions();
        $qascLanguageOptions = $this->qascLanguageOptions();
        $researchQueryParams = collect([
            'think_tank_member_id' => $portalRouteParams['think_tank_member_id'] ?? null,
            'filter_month' => $dashboardFilter['month'] ?? null,
            'filter_year' => $dashboardFilter['year'] ?? null,
            'date_from' => $dashboardFilter['date_from'] ?? null,
            'date_to' => $dashboardFilter['date_to'] ?? null,
            'status' => $statusFilter ?: null,
            'output_type' => $typeFilter ?: null,
            'q' => $keyword ?: null,
        ])->filter(fn ($value) => filled($value))->all();

        return compact(
            'member',
            'outputs',
            'outputRecords',
            'researchStats',
            'outputTypes',
            'statusCounts',
            'typeCounts',
            'dashboardFilter',
            'statusFilter',
            'typeFilter',
            'keyword',
            'chartData',
            'membersForSearch',
            'portalRouteParams',
            'researchQueryParams',
            'qascChecklist',
            'qascConsortiumOptions',
            'qascTrackOptions',
            'qascLanguageOptions'
        );
    }

    public function purchaseOrders(Request $request)
    {
        $member = $this->member($request);
        $member->loadMissing(['consortium', 'vendorUser', 'fundAllocations']);

        $purchaseOrders = ProcurementPurchaseOrder::with(['disbursements'])
            ->where('think_tank_member_id', $member->id)
            ->where('po_type', 'think_tank_transfer')
            ->orderByDesc('created_at')
            ->paginate(15);

        $allocations = ConsortiumFundAllocation::where('think_tank_member_id', $member->id)
            ->orderBy('budget_line')
            ->get();

        $allTransferOrders = ProcurementPurchaseOrder::where('think_tank_member_id', $member->id)
            ->where('po_type', 'think_tank_transfer')
            ->get();

        $stats = [
            'total' => $allTransferOrders->count(),
            'amount' => $allTransferOrders->sum('amount'),
            'paid' => $allTransferOrders->sum(fn (ProcurementPurchaseOrder $order) => $order->paidAmount()),
            'remaining' => $allTransferOrders->sum(fn (ProcurementPurchaseOrder $order) => $order->remainingAmount()),
        ];

        return view('think-tank.purchase-orders', compact('member', 'purchaseOrders', 'allocations', 'stats'));
    }

    public function storePurchaseOrder(Request $request)
    {
        $member = $this->member($request);
        $member->loadMissing(['consortium', 'vendorUser']);

        if (! $member->vendor_user_id) {
            return back()->with('error', 'This think tank is not linked to a vendor account yet.');
        }

        $data = $request->validate([
            'fund_allocation_id' => 'nullable|exists:attp_fund_allocations,id',
            'au_sap_vendor_number' => 'nullable|string|max:80',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'issued_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (! $member->au_sap_vendor_number && empty($data['au_sap_vendor_number'])) {
            return back()
                ->withErrors(['au_sap_vendor_number' => 'Please enter your AU SAP vendor number before creating a purchase order.'])
                ->withInput();
        }

        if (! empty($data['au_sap_vendor_number']) && $data['au_sap_vendor_number'] !== $member->au_sap_vendor_number) {
            $member->update(['au_sap_vendor_number' => $data['au_sap_vendor_number']]);
            $member->refresh();
        }

        $allocation = null;
        if (! empty($data['fund_allocation_id'])) {
            $allocation = ConsortiumFundAllocation::where('think_tank_member_id', $member->id)
                ->whereKey($data['fund_allocation_id'])
                ->firstOrFail();
        }

        $purchaseOrder = ProcurementPurchaseOrder::create([
            'po_type' => 'think_tank_transfer',
            'consortium_id' => $member->consortium_id,
            'think_tank_member_id' => $member->id,
            'vendor_id' => $member->vendor_user_id,
            'reference_no' => ProcurementPurchaseOrder::generateThinkTankTransferReference($member),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?: ($allocation?->currency ?? $member->consortium?->currency ?? 'USD'),
            'status' => 'issued',
            'created_by' => $request->user()?->id,
            'issued_at' => $data['issued_at'] ?? now(),
        ]);

        if (! empty($data['notes'])) {
            logger()->info('Think tank transfer PO notes', [
                'purchase_order_id' => $purchaseOrder->id,
                'think_tank_member_id' => $member->id,
                'notes' => $data['notes'],
            ]);
        }

        return redirect()
            ->route('think-tank.purchase-orders.show', array_merge($this->portalRouteParams($request, $member), ['purchaseOrder' => $purchaseOrder]))
            ->with('success', 'Purchase order created: ' . $purchaseOrder->reference_no);
    }

    public function showPurchaseOrder(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $member = $this->member($request);
        $this->assertMemberPurchaseOrder($member, $purchaseOrder);

        $purchaseOrder->load(['vendor', 'consortium', 'thinkTankMember', 'disbursements.recipientConfirmer']);

        return view('think-tank.purchase-orders-show', compact('member', 'purchaseOrder'));
    }

    public function confirmDisbursementReceipt(Request $request, ProcurementPurchaseOrder $purchaseOrder, ProcurementDisbursement $disbursement)
    {
        $member = $this->member($request);
        $this->assertMemberPurchaseOrder($member, $purchaseOrder);

        abort_unless(
            (string) $disbursement->purchase_order_id === (string) $purchaseOrder->id
            && (string) $disbursement->think_tank_member_id === (string) $member->id,
            403
        );

        $data = $request->validate([
            'recipient_confirmation_notes' => 'nullable|string|max:2000',
        ]);

        $disbursement->update([
            'recipient_confirmation_status' => 'confirmed',
            'recipient_confirmed_by' => $request->user()?->id,
            'recipient_confirmed_at' => now(),
            'recipient_confirmation_notes' => $data['recipient_confirmation_notes'] ?? null,
        ]);

        $purchaseOrder->load(['disbursements', 'invoice']);
        $hasPendingReceipt = $purchaseOrder->disbursements
            ->contains(fn (ProcurementDisbursement $linkedDisbursement) => $linkedDisbursement->recipient_confirmation_status !== 'confirmed');

        $purchaseOrder->update([
            'status' => $hasPendingReceipt ? 'pending' : 'fully_paid',
        ]);

        if ($purchaseOrder->invoice) {
            $purchaseOrder->invoice->update([
                'status' => 'paid',
                'approved_by' => $purchaseOrder->invoice->approved_by ?: $request->user()?->id,
                'approved_at' => $purchaseOrder->invoice->approved_at ?: now(),
            ]);
        } else {
            $invoice = ProcurementInvoice::create([
                'procurement_id' => $purchaseOrder->procurement_id,
                'vendor_id' => $purchaseOrder->vendor_id,
                'sub_activity_id' => $purchaseOrder->sub_activity_id,
                'governance_node_id' => $purchaseOrder->governance_node_id,
                'invoice_month' => ($disbursement->paid_at ?: now())->copy()->startOfMonth()->toDateString(),
                'reference_no' => ProcurementInvoice::generateReference(),
                'amount' => $purchaseOrder->amount,
                'currency' => $purchaseOrder->currency,
                'status' => 'paid',
                'created_by' => $purchaseOrder->created_by,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'notes' => 'Paid Funding to Think Tanks transfer for ' . $member->name,
            ]);

            $purchaseOrder->update(['invoice_id' => $invoice->id]);
        }

        $this->auditAction('think_tank.transfer.receipt_confirmed', 'Think tank funding transfer receipt confirmed', [
            'think_tank_member_id' => $member->id,
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_status' => $purchaseOrder->fresh()->status,
            'disbursement_id' => $disbursement->id,
            'amount' => (float) $disbursement->amount,
            'currency' => $disbursement->currency,
        ]);

        return back()->with('success', 'Receipt of payment confirmed. ATTP Secretariat can now see this transfer as received.');
    }

    public function purchaseOrderPdf(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $member = $this->member($request);
        $this->assertMemberPurchaseOrder($member, $purchaseOrder);

        $purchaseOrder->load(['vendor', 'consortium', 'thinkTankMember', 'disbursements']);

        return Pdf::loadView('think-tank.purchase-orders-pdf', compact('member', 'purchaseOrder'))
            ->stream('purchase-order-' . ($purchaseOrder->reference_no ?? 'po') . '.pdf');
    }

    public function downloadPurchaseOrder(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $member = $this->member($request);
        $this->assertMemberPurchaseOrder($member, $purchaseOrder);

        $purchaseOrder->load(['vendor', 'consortium', 'thinkTankMember', 'disbursements']);

        return Pdf::loadView('think-tank.purchase-orders-pdf', compact('member', 'purchaseOrder'))
            ->download('purchase-order-' . ($purchaseOrder->reference_no ?? 'po') . '.pdf');
    }

    public function storeResearch(Request $request)
    {
        $member = $this->member($request);
        $checklistRules = [];

        foreach (array_keys($this->qascChecklistDefinitions()) as $key) {
            $checklistRules["qasc.checklist.{$key}.status"] = ['required', Rule::in(['confirmed', 'not_applicable'])];
            $checklistRules["qasc.checklist.{$key}.signed_by"] = 'required|string|max:255';
            $checklistRules["qasc.checklist.{$key}.signed_date"] = 'required|date';
        }

        $data = $request->validate(array_merge([
            'title' => 'required|string|max:255',
            'output_type' => 'required|in:research,policy_brief,report,working_paper,dataset,publication',
            'published_on' => 'nullable|date',
            'abstract' => 'nullable|string',
            'external_url' => 'nullable|url|max:2000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip|max:20480',
            'qasc.lead_authors' => 'required|string|max:1000',
            'qasc.lead_think_tank' => 'required|string|max:255',
            'qasc.consortium' => ['required', Rule::in($this->qascConsortiumOptions())],
            'qasc.track_classification' => ['required', Rule::in($this->qascTrackOptions())],
            'qasc.original_language' => ['required', Rule::in($this->qascLanguageOptions())],
            'qasc.intended_publication_date' => 'required|date',
            'qasc.lead_author_name' => 'required|string|max:255',
            'qasc.lead_author_date' => 'required|date',
            'qasc.lead_think_tank_representative_name' => 'required|string|max:255',
            'qasc.lead_think_tank_date' => 'required|date',
            'qasc_author_signature' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'qasc_think_tank_signature' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], $checklistRules));

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store("think-tank-research/{$member->id}");
        }

        $authorSignaturePath = $request->file('qasc_author_signature')
            ->store("think-tank-research/{$member->id}/qasc-signatures");
        $thinkTankSignaturePath = $request->file('qasc_think_tank_signature')
            ->store("think-tank-research/{$member->id}/qasc-signatures");

        $qascData = $this->buildQascData($data, $member);

        $output = ThinkTankResearchOutput::create([
            'title' => $data['title'],
            'output_type' => $data['output_type'],
            'published_on' => $data['published_on'] ?? null,
            'abstract' => $data['abstract'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'qasc_data' => $qascData,
            'qasc_author_signature_path' => $authorSignaturePath,
            'qasc_think_tank_signature_path' => $thinkTankSignaturePath,
            'consortium_id' => $member->consortium_id,
            'think_tank_member_id' => $member->id,
            'status' => 'submitted',
            'submitted_by' => $request->user()?->id,
            'submitted_at' => now(),
        ]);

        $output->loadMissing(['member.consortium', 'consortium']);
        $pdfContent = $this->researchQascPdfContent($output);
        $pdfPath = "think-tank-research/{$member->id}/qasc-pdfs/qasc-{$output->id}.pdf";
        Storage::put($pdfPath, $pdfContent);
        $output->forceFill(['qasc_pdf_path' => $pdfPath])->save();

        $emailSent = $this->sendResearchQascEmail($output, $member, $pdfContent, $request);
        if ($emailSent) {
            $output->forceFill(['qasc_email_sent_at' => now()])->save();
        }

        $this->auditAction('think_tank.research.qasc_submitted', 'Think tank research output and QASC submitted', [
            'think_tank_member_id' => $member->id,
            'think_tank' => $member->name,
            'research_output_id' => $output->id,
            'email_sent' => $emailSent,
        ]);

        return redirect()
            ->route('think-tank.research.qasc.preview', array_merge(
                $this->portalRouteParams($request, $member),
                ['output' => $output]
            ))
            ->with('success', $emailSent
                ? 'Research output submitted. Annex B QASC PDF was emailed to the think tank.'
                : 'Research output submitted. Annex B QASC PDF is ready, but no think tank email recipient was available.');
    }

    public function previewResearchQasc(Request $request, ThinkTankResearchOutput $output)
    {
        $member = $this->member($request);
        $this->assertMemberResearchOutput($member, $output);

        $output->loadMissing(['member.consortium', 'consortium']);
        $pdfContent = $output->qasc_pdf_path && Storage::exists($output->qasc_pdf_path)
            ? Storage::get($output->qasc_pdf_path)
            : $this->researchQascPdfContent($output);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->researchQascFilename($output) . '"',
        ]);
    }

    public function procurement(Request $request)
    {
        return view('think-tank.procurement', $this->procurementPayload($request));
    }

    public function downloadProcurement(Request $request)
    {
        $payload = $this->procurementPayload($request);
        $filename = 'think-tank-procurement-' . Str::slug($payload['member']->name) . '-' . now()->format('Ymd-His') . '.pdf';

        $this->auditAction('think_tank.procurement.downloaded', 'Think tank procurement dashboard downloaded', [
            'think_tank_member_id' => $payload['member']->id,
            'think_tank' => $payload['member']->name,
            'filters' => $payload['procurementQueryParams'],
        ]);

        return Pdf::loadView('think-tank.procurement-report-pdf', $payload)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function procurementPayload(Request $request): array
    {
        $member = $this->member($request);
        $dashboardFilter = $this->dashboardFilter($request);
        $periodStart = $dashboardFilter['start'];
        $periodEnd = $dashboardFilter['end'];
        $statusFilter = trim((string) $request->input('status', ''));
        $planStatusFilter = trim((string) $request->input('plan_status', ''));
        $fiscalYearFilter = trim((string) $request->input('fiscal_year', ''));
        $keyword = trim((string) $request->input('q', ''));

        $applyPlanPeriod = function ($query) use ($periodStart, $periodEnd) {
            if (! $periodStart && ! $periodEnd) {
                return $query;
            }

            return $query->where(function ($periodQuery) use ($periodStart, $periodEnd) {
                if ($periodStart && $periodEnd) {
                    $periodQuery->whereBetween('planned_publish_date', [$periodStart, $periodEnd])
                        ->orWhereBetween('created_at', [$periodStart, $periodEnd]);
                } elseif ($periodStart) {
                    $periodQuery->whereDate('planned_publish_date', '>=', $periodStart)
                        ->orWhereDate('created_at', '>=', $periodStart);
                } elseif ($periodEnd) {
                    $periodQuery->whereDate('planned_publish_date', '<=', $periodEnd)
                        ->orWhereDate('created_at', '<=', $periodEnd);
                }
            });
        };

        $applyOpportunityPeriod = function ($query) use ($periodStart, $periodEnd) {
            if (! $periodStart && ! $periodEnd) {
                return $query;
            }

            return $query->where(function ($periodQuery) use ($periodStart, $periodEnd) {
                if ($periodStart && $periodEnd) {
                    $periodQuery->whereBetween('application_start_date', [$periodStart, $periodEnd])
                        ->orWhereBetween('application_end_date', [$periodStart, $periodEnd])
                        ->orWhereBetween('created_at', [$periodStart, $periodEnd]);
                } elseif ($periodStart) {
                    $periodQuery->whereDate('application_start_date', '>=', $periodStart)
                        ->orWhereDate('application_end_date', '>=', $periodStart)
                        ->orWhereDate('created_at', '>=', $periodStart);
                } elseif ($periodEnd) {
                    $periodQuery->whereDate('application_start_date', '<=', $periodEnd)
                        ->orWhereDate('application_end_date', '<=', $periodEnd)
                        ->orWhereDate('created_at', '<=', $periodEnd);
                }
            });
        };

        $plansBase = $applyPlanPeriod(ThinkTankProcurementPlan::where('think_tank_member_id', $member->id))
            ->when($planStatusFilter !== '', fn ($query) => $query->where('status', $planStatusFilter))
            ->when($fiscalYearFilter !== '', fn ($query) => $query->where('fiscal_year', $fiscalYearFilter))
            ->when($keyword !== '', function ($query) use ($keyword) {
                $search = '%' . $keyword . '%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', $search)
                        ->orWhere('plan_code', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            });

        $opportunitiesBase = $applyOpportunityPeriod(Procurement::where('think_tank_member_id', $member->id))
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter))
            ->when($fiscalYearFilter !== '', fn ($query) => $query->where('fiscal_year', $fiscalYearFilter))
            ->when($keyword !== '', function ($query) use ($keyword) {
                $search = '%' . $keyword . '%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', $search)
                        ->orWhere('reference_no', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            });

        $plans = (clone $plansBase)
            ->withCount('procurements')
            ->latest()
            ->get();

        $planOptions = ThinkTankProcurementPlan::where('think_tank_member_id', $member->id)
            ->latest()
            ->get();

        $opportunityRecords = (clone $opportunitiesBase)
            ->with(['thinkTankProcurementPlan', 'awardedSubmission'])
            ->withCount('submissions')
            ->latest()
            ->get();

        $procurements = (clone $opportunitiesBase)
            ->with(['thinkTankProcurementPlan', 'awardedSubmission'])
            ->withCount('submissions')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $procurementIds = $opportunityRecords->pluck('id');
        $submissionRecords = $procurementIds->isNotEmpty()
            ? FormSubmission::with('thinkTankReview')
                ->whereIn('procurement_id', $procurementIds)
                ->get()
            : collect();

        $reviewRecords = $procurementIds->isNotEmpty()
            ? ThinkTankProcurementReview::whereIn('procurement_id', $procurementIds)->get()
            : collect();

        $statusCounts = $opportunityRecords->groupBy(fn ($procurement) => $procurement->status ?: 'draft')->map->count();
        $planStatusCounts = $plans->groupBy(fn ($plan) => $plan->status ?: 'submitted')->map->count();
        $submissionStatusCounts = $submissionRecords->groupBy(fn ($submission) => $submission->status ?: 'submitted')->map->count();
        $today = CarbonImmutable::now()->startOfDay();

        $procurementStats = [
            'plans' => $plans->count(),
            'plan_budget' => (float) $plans->sum(fn ($plan) => (float) $plan->estimated_budget),
            'opportunities' => $opportunityRecords->count(),
            'opportunity_budget' => (float) $opportunityRecords->sum(fn ($procurement) => (float) $procurement->estimated_budget),
            'published' => (int) ($statusCounts->get('published') ?? 0),
            'draft' => (int) ($statusCounts->get('draft') ?? 0),
            'closed' => (int) ($statusCounts->get('closed') ?? 0),
            'awarded' => (int) ($statusCounts->get('awarded') ?? 0),
            'applications' => $submissionRecords->count(),
            'reviewed' => $reviewRecords->count(),
            'selected' => $opportunityRecords->whereNotNull('awarded_submission_id')->count(),
            'open' => $opportunityRecords->filter(function ($procurement) use ($today) {
                if ($procurement->status !== 'published') {
                    return false;
                }

                if ($procurement->application_start_date && $today->lt($procurement->application_start_date)) {
                    return false;
                }

                return ! $procurement->application_end_date || $today->lte($procurement->application_end_date);
            })->count(),
            'closing_soon' => $opportunityRecords->filter(function ($procurement) use ($today) {
                return $procurement->status === 'published'
                    && $procurement->application_end_date
                    && $today->lte($procurement->application_end_date)
                    && $today->diffInDays($procurement->application_end_date, false) <= 14;
            })->count(),
        ];

        $procurementStats['average_applications'] = $procurementStats['opportunities'] > 0
            ? round($procurementStats['applications'] / $procurementStats['opportunities'], 1)
            : 0;

        $fiscalYears = $plans->pluck('fiscal_year')
            ->merge($opportunityRecords->pluck('fiscal_year'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $lastSixMonths = collect(range(5, 0))->map(fn ($monthsAgo) => $today->subMonths($monthsAgo));
        $monthlyPlans = $lastSixMonths->map(function ($date) use ($plans) {
            return $plans->filter(fn ($plan) => $plan->created_at && $plan->created_at->format('Y-m') === $date->format('Y-m'))->count();
        })->values();

        $monthlyOpportunities = $lastSixMonths->map(function ($date) use ($opportunityRecords) {
            return $opportunityRecords->filter(fn ($procurement) => $procurement->created_at && $procurement->created_at->format('Y-m') === $date->format('Y-m'))->count();
        })->values();

        $monthlyApplications = $lastSixMonths->map(function ($date) use ($submissionRecords) {
            return $submissionRecords->filter(fn ($submission) => $submission->submitted_at && $submission->submitted_at->format('Y-m') === $date->format('Y-m'))->count();
        })->values();

        $monthlyBudget = $lastSixMonths->map(function ($date) use ($opportunityRecords) {
            return round((float) $opportunityRecords->filter(fn ($procurement) => $procurement->created_at && $procurement->created_at->format('Y-m') === $date->format('Y-m'))
                ->sum(fn ($procurement) => (float) $procurement->estimated_budget), 2);
        })->values();

        $chartData = [
            'opportunityStatus' => [
                'labels' => $statusCounts->keys()->map(fn ($status) => ucfirst(str_replace('_', ' ', $status)))->values(),
                'values' => $statusCounts->values(),
            ],
            'planStatus' => [
                'labels' => $planStatusCounts->keys()->map(fn ($status) => ucfirst(str_replace('_', ' ', $status)))->values(),
                'values' => $planStatusCounts->values(),
            ],
            'pipeline' => [
                'labels' => $lastSixMonths->map(fn ($date) => $date->format('M'))->values(),
                'plans' => $monthlyPlans,
                'opportunities' => $monthlyOpportunities,
                'applications' => $monthlyApplications,
            ],
            'budget' => [
                'labels' => $lastSixMonths->map(fn ($date) => $date->format('M'))->values(),
                'values' => $monthlyBudget,
            ],
            'applications' => [
                'labels' => $submissionStatusCounts->keys()->map(fn ($status) => ucfirst(str_replace('_', ' ', $status)))->values(),
                'values' => $submissionStatusCounts->values(),
            ],
            'review' => [
                'labels' => ['Applications received', 'Reviewed', 'Selected opportunities'],
                'values' => [$procurementStats['applications'], $procurementStats['reviewed'], $procurementStats['selected']],
            ],
        ];

        foreach (['opportunityStatus', 'planStatus', 'applications'] as $chartKey) {
            if ($chartData[$chartKey]['labels']->isEmpty()) {
                $chartData[$chartKey]['labels'] = collect(['No data']);
                $chartData[$chartKey]['values'] = collect([0]);
            }
        }

        $membersForSearch = $request->user() && ($request->user()->isSuperAdmin() || $request->user()->isAdmin())
            ? ConsortiumThinkTank::with('consortium:id,name')
                ->orderBy('name')
                ->get(['id', 'consortium_id', 'name', 'country', 'role', 'status'])
            : collect([$member]);

        $portalRouteParams = $this->portalRouteParams($request, $member);
        $procurementQueryParams = collect([
            'think_tank_member_id' => $portalRouteParams['think_tank_member_id'] ?? null,
            'filter_month' => $dashboardFilter['month'] ?? null,
            'filter_year' => $dashboardFilter['year'] ?? null,
            'date_from' => $dashboardFilter['date_from'] ?? null,
            'date_to' => $dashboardFilter['date_to'] ?? null,
            'status' => $statusFilter ?: null,
            'plan_status' => $planStatusFilter ?: null,
            'fiscal_year' => $fiscalYearFilter ?: null,
            'q' => $keyword ?: null,
        ])->filter(fn ($value) => filled($value))->all();

        return compact(
            'member',
            'plans',
            'planOptions',
            'procurements',
            'opportunityRecords',
            'submissionRecords',
            'reviewRecords',
            'procurementStats',
            'statusCounts',
            'planStatusCounts',
            'submissionStatusCounts',
            'fiscalYears',
            'dashboardFilter',
            'statusFilter',
            'planStatusFilter',
            'fiscalYearFilter',
            'keyword',
            'chartData',
            'membersForSearch',
            'portalRouteParams',
            'procurementQueryParams'
        );
    }

    public function storeProcurementPlan(Request $request)
    {
        $member = $this->member($request);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'fiscal_year' => 'nullable|string|max:20',
            'estimated_budget' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'planned_publish_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $plan = ThinkTankProcurementPlan::create([
            ...$data,
            'consortium_id' => $member->consortium_id,
            'think_tank_member_id' => $member->id,
            'plan_code' => $this->nextCode('TT-PLAN'),
            'currency' => $data['currency'] ?? $member->consortium->currency,
            'status' => 'submitted',
            'created_by' => $request->user()?->id,
        ]);

        $this->auditAction('think_tank.procurement.plan_created', 'Think tank procurement plan created', [
            'think_tank_member_id' => $member->id,
            'plan_id' => $plan->id,
            'plan_code' => $plan->plan_code,
            'amount' => $plan->estimated_budget,
            'currency' => $plan->currency,
        ]);

        return back()->with('success', 'Procurement plan submitted.');
    }

    public function storeProcurement(Request $request)
    {
        $member = $this->member($request);

        $data = $request->validate([
            'think_tank_procurement_plan_id' => 'nullable|exists:attp_think_tank_procurement_plans,id',
            'title' => 'required|string|max:255',
            'reference_no' => 'nullable|string|max:100',
            'description' => 'required|string',
            'fiscal_year' => 'nullable|string|max:20',
            'estimated_budget' => 'required|numeric|min:0',
            'application_start_date' => 'nullable|date',
            'application_end_date' => 'required|date|after_or_equal:application_start_date',
            'status' => 'required|in:draft,published',
        ]);

        $procurement = DB::transaction(function () use ($data, $request, $member) {
            $procurement = Procurement::create([
                ...$data,
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'procurement_owner_type' => 'think_tank',
                'oversight_status' => 'visible',
                'visibility_type' => 'public',
                'created_by' => $request->user()?->id,
            ]);

            $form = DynamicForm::create([
                'name' => $procurement->title . ' Application Form',
                'applies_to' => 'procurement',
                'status' => 'approved',
                'is_active' => true,
                'procurement_id' => $procurement->id,
                'created_by' => $request->user()?->id,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);

            $form->ensureGlobalFields();

            foreach ($this->defaultProcurementFields() as $field) {
                DynamicFormField::updateOrCreate(
                    ['form_id' => $form->id, 'field_key' => $field['field_key']],
                    [...$field, 'created_by' => $request->user()?->id]
                );
            }

            return $procurement;
        });

        $this->auditAction('think_tank.procurement.opportunity_created', 'Think tank procurement opportunity created', [
            'think_tank_member_id' => $member->id,
            'procurement_id' => $procurement->id,
            'reference_no' => $procurement->reference_no,
            'status' => $procurement->status,
            'amount' => $procurement->estimated_budget,
        ]);

        return back()->with('success', 'Procurement opportunity created. Published items appear on the public procurement page.');
    }

    public function submissions(Request $request, Procurement $procurement)
    {
        $member = $this->member($request);
        abort_unless($procurement->think_tank_member_id === $member->id, 403);

        $procurement->load(['submissions.submitter', 'submissions.values', 'submissions.thinkTankReview', 'awardedSubmission']);

        return view('think-tank.submissions', compact('member', 'procurement'));
    }

    public function reviewSubmission(Request $request, Procurement $procurement, FormSubmission $submission)
    {
        $member = $this->member($request);
        abort_unless($procurement->think_tank_member_id === $member->id && $submission->procurement_id === $procurement->id, 403);

        $data = $request->validate([
            'technical_score' => 'required|numeric|min:0|max:100',
            'financial_score' => 'required|numeric|min:0|max:100',
            'recommendation' => 'required|in:pending,shortlisted,recommended,rejected',
            'comments' => 'nullable|string',
        ]);

        $total = round(((float) $data['technical_score'] * 0.7) + ((float) $data['financial_score'] * 0.3), 2);

        ThinkTankProcurementReview::updateOrCreate(
            ['procurement_id' => $procurement->id, 'form_submission_id' => $submission->id],
            [
                ...$data,
                'think_tank_member_id' => $member->id,
                'reviewed_by' => $request->user()?->id,
                'total_score' => $total,
                'reviewed_at' => now(),
            ]
        );

        $submission->update(['status' => $data['recommendation']]);

        return back()->with('success', 'Evaluation saved.');
    }

    public function selectSubmission(Request $request, Procurement $procurement, FormSubmission $submission)
    {
        $member = $this->member($request);
        abort_unless($procurement->think_tank_member_id === $member->id && $submission->procurement_id === $procurement->id, 403);

        $procurement->update([
            'awarded_submission_id' => $submission->id,
            'awarded_vendor_id' => $submission->submitted_by,
            'awarded_at' => now(),
            'status' => 'awarded',
        ]);

        $submission->update(['status' => 'selected']);

        return back()->with('success', 'Selected vendor saved. ATTP Secretariat and funders can see this decision immediately.');
    }

    private function dashboardFilter(Request $request): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $start = null;
        $end = null;
        $mode = 'all';
        $label = 'All time';

        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');
        $month = trim((string) $request->input('filter_month', ''));
        $year = trim((string) $request->input('filter_year', ''));

        if ($dateFrom || $dateTo) {
            $mode = 'custom';
            $start = $dateFrom ? CarbonImmutable::parse($dateFrom)->startOfDay() : null;
            $end = $dateTo ? CarbonImmutable::parse($dateTo)->endOfDay() : null;
            $label = ($start?->format('M d, Y') ?? 'Start') . ' to ' . ($end?->format('M d, Y') ?? 'Today');
        } elseif (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $mode = 'month';
            $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
            $end = $start->endOfMonth();
            $label = $start->format('F Y');
        } elseif (preg_match('/^\d{4}$/', $year)) {
            $mode = 'year';
            $start = CarbonImmutable::create((int) $year, 1, 1)->startOfYear();
            $end = $start->endOfYear();
            $label = $start->format('Y');
        }

        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [
            'mode' => $mode,
            'label' => $label,
            'start' => $start,
            'end' => $end,
            'is_all_time' => $mode === 'all',
            'month' => $month,
            'year' => $year,
            'date_from' => $dateFrom ? CarbonImmutable::parse($dateFrom)->toDateString() : null,
            'date_to' => $dateTo ? CarbonImmutable::parse($dateTo)->toDateString() : null,
            'year_options' => range((int) $today->format('Y') + 1, (int) $today->format('Y') - 6),
        ];
    }

    private function member(Request $request): ConsortiumThinkTank
    {
        $user = $request->user();

        if ($user && ($user->isSuperAdmin() || $user->isAdmin())) {
            $routeProcurement = $request->route('procurement');
            $memberId = $request->input('think_tank_member_id')
                ?: $request->input('member_id')
                ?: $routeProcurement?->think_tank_member_id;

            return ConsortiumThinkTank::with('consortium')
                ->when($memberId, fn ($query) => $query->whereKey($memberId))
                ->orderBy('name')
                ->firstOrFail();
        }

        return $user->thinkTankMembership()->with('consortium')->firstOrFail();
    }

    private function defaultProcurementFields(): array
    {
        return [
            ['label' => 'Organization Profile', 'field_key' => 'organization_profile', 'field_type' => 'file', 'is_required' => true, 'sort_order' => 10],
            ['label' => 'Technical Proposal', 'field_key' => 'technical_proposal', 'field_type' => 'file', 'is_required' => true, 'sort_order' => 20],
            ['label' => 'Financial Proposal', 'field_key' => 'financial_proposal', 'field_type' => 'file', 'is_required' => true, 'sort_order' => 30],
            ['label' => 'Quoted Amount', 'field_key' => 'quoted_amount', 'field_type' => 'number', 'is_required' => true, 'sort_order' => 40],
            ['label' => 'Relevant Experience', 'field_key' => 'relevant_experience', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 50],
        ];
    }

    private function nextCode(string $prefix): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    private function portalRouteParams(Request $request, ConsortiumThinkTank $member): array
    {
        $user = $request->user();

        return $user && ($user->isSuperAdmin() || $user->isAdmin())
            ? ['think_tank_member_id' => $member->id]
            : [];
    }

    private function qascConsortiumOptions(): array
    {
        return ['BRIDGE', 'CACEPS', 'RAISED'];
    }

    private function qascTrackOptions(): array
    {
        return ['Track 1', 'Track 2', 'Track 3', 'Track 4'];
    }

    private function qascLanguageOptions(): array
    {
        return ['English', 'French', 'Arabic', 'Portuguese', 'Spanish', 'Kiswahili'];
    }

    private function qascChecklistDefinitions(): array
    {
        return [
            'internal_review' => [
                'number' => 1,
                'title' => 'Internal institutional review completed',
                'description' => "Author confirms that the authoring institution's own internal review and approval procedures, including applicable internal peer review, editorial, ethics, factual accuracy and integrity checks, have been completed before this QASC is signed.",
                'applies_to' => 'Applies to all tracks',
            ],
            'data_confidentiality' => [
                'number' => 2,
                'title' => 'Data and confidentiality',
                'description' => 'Data verified against original sources; statistical claims fact-checked.',
                'applies_to' => 'Applies to all tracks',
            ],
            'publication_integrity' => [
                'number' => 3,
                'title' => 'Publication Integrity',
                'description' => 'Output is original; no fabrication, falsification, or plagiarism; prior work cited proportionately.',
                'applies_to' => 'Applies to all tracks',
            ],
            'au_partner_sensitivity' => [
                'number' => 4,
                'title' => 'AU and partner sensitivity',
                'description' => 'References to AU organs, RECs, Member States, and named institutions are factually correct and respectful; right of reply offered where required; no partisan endorsement; political and diplomatic risk assessed and elevated risks notified to the Secretariat.',
                'applies_to' => 'Applies to all tracks',
            ],
            'ai_usage' => [
                'number' => 5,
                'title' => 'AI usage',
                'description' => 'Generative AI used only for permitted support tasks per PREP Section 4.6, and not for content generation.',
                'applies_to' => 'Applies to all tracks',
            ],
        ];
    }

    private function buildQascData(array $data, ConsortiumThinkTank $member): array
    {
        $qasc = (array) ($data['qasc'] ?? []);
        $checklistInput = (array) ($qasc['checklist'] ?? []);
        $checklist = [];

        foreach ($this->qascChecklistDefinitions() as $key => $definition) {
            $entry = (array) ($checklistInput[$key] ?? []);

            $checklist[$key] = array_merge($definition, [
                'status' => (string) ($entry['status'] ?? 'confirmed'),
                'signed_by' => (string) ($entry['signed_by'] ?? ''),
                'signed_date' => (string) ($entry['signed_date'] ?? ''),
            ]);
        }

        return [
            'output_title' => (string) ($data['title'] ?? ''),
            'lead_authors' => (string) ($qasc['lead_authors'] ?? ''),
            'lead_think_tank' => (string) ($qasc['lead_think_tank'] ?? $member->name),
            'consortium' => (string) ($qasc['consortium'] ?? ''),
            'track_classification' => (string) ($qasc['track_classification'] ?? ''),
            'original_language' => (string) ($qasc['original_language'] ?? ''),
            'intended_publication_date' => (string) ($qasc['intended_publication_date'] ?? ''),
            'checklist' => $checklist,
            'lead_author' => [
                'name' => (string) ($qasc['lead_author_name'] ?? ''),
                'date' => (string) ($qasc['lead_author_date'] ?? ''),
            ],
            'lead_think_tank_representative' => [
                'name' => (string) ($qasc['lead_think_tank_representative_name'] ?? ''),
                'date' => (string) ($qasc['lead_think_tank_date'] ?? ''),
            ],
            'certified_at' => now()->toDateTimeString(),
        ];
    }

    private function researchQascPdfContent(ThinkTankResearchOutput $output): string
    {
        ini_set('memory_limit', '512M');

        $output->loadMissing(['member.consortium', 'consortium']);

        return Pdf::loadView('think-tank.research-qasc-pdf', [
            'output' => $output,
            'member' => $output->member,
            'qasc' => (array) ($output->qasc_data ?? []),
            'checklist' => (array) data_get($output->qasc_data, 'checklist', []),
            'authorSignatureDataUri' => $this->storedImageDataUri($output->qasc_author_signature_path),
            'thinkTankSignatureDataUri' => $this->storedImageDataUri($output->qasc_think_tank_signature_path),
        ])->setPaper('a4')->output();
    }

    private function researchQascFilename(ThinkTankResearchOutput $output): string
    {
        return 'annex-b-qasc-' . Str::slug($output->title ?: 'research-output') . '.pdf';
    }

    private function storedImageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::exists($path)) {
            return null;
        }

        $mime = Storage::mimeType($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::get($path));
    }

    private function sendResearchQascEmail(
        ThinkTankResearchOutput $output,
        ConsortiumThinkTank $member,
        string $pdfContent,
        Request $request
    ): bool {
        $member->loadMissing(['portalUser', 'vendorUser', 'consortium']);
        $recipients = collect([
            $member->email,
            $member->portalUser?->email,
            $member->vendorUser?->email,
        ])
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return false;
        }

        try {
            Mail::to($recipients->all())->send(new ThinkTankResearchQascSubmitted(
                $output,
                $member,
                $pdfContent,
                route('think-tank.research.qasc.preview', array_merge(
                    $this->portalRouteParams($request, $member),
                    ['output' => $output]
                )),
                $this->researchQascFilename($output)
            ));

            return true;
        } catch (Throwable $exception) {
            logger()->warning('Unable to email think tank QASC PDF.', [
                'research_output_id' => $output->id,
                'think_tank_member_id' => $member->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function auditAction(string $action, string $message, array $payload = []): void
    {
        try {
            $request = request();

            SystemAuditLog::create([
                'user_id' => optional($request->user())->id,
                'module' => 'think_tank_portal',
                'action' => $action,
                'action_message' => $message,
                'description' => $message,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'country' => IpGeo::countryForIp($request->ip()),
                'user_agent' => $request->userAgent() ? substr((string) $request->userAgent(), 0, 1000) : null,
                'status_code' => 200,
                'payload' => $payload,
            ]);
        } catch (Throwable) {
            // Audit logging should not block portal reporting workflows.
        }
    }

    private function assertMemberPurchaseOrder(ConsortiumThinkTank $member, ProcurementPurchaseOrder $purchaseOrder): void
    {
        abort_unless(
            $purchaseOrder->po_type === 'think_tank_transfer'
            && (string) $purchaseOrder->think_tank_member_id === (string) $member->id,
            403
        );
    }

    private function assertMemberResearchOutput(ConsortiumThinkTank $member, ThinkTankResearchOutput $output): void
    {
        abort_unless((string) $output->think_tank_member_id === (string) $member->id, 403);
    }
}
