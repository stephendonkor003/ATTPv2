<?php

namespace App\Http\Controllers;

use App\Mail\ThinkTankPortalWelcome;
use App\Models\BudgetCommitment;
use App\Models\Consortium;
use App\Models\ConsortiumActivityReport;
use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\PurchaseRequest;
use App\Models\SubActivity;
use App\Models\SystemAuditLog;
use App\Models\ThinkDataset;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankInvitationService;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use App\Services\ThinkTankLogoService;
use App\Support\IpGeo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminThinkTankController extends Controller
{
    public function __construct(
        private readonly ThinkTankUserManagementService $userManagement,
        private readonly ThinkTankInvitationService $invitations,
    ) {}

    public function dashboard(Request $request)
    {
        return $this->analysisDashboard($request, 'consortium');
    }

    public function consortiumAnalysis(Request $request)
    {
        return $this->analysisDashboard($request, 'consortium');
    }

    public function thinkTankAnalysis(Request $request)
    {
        return $this->analysisDashboard($request, 'think_tank');
    }

    public function consortiumReports(Request $request)
    {
        return $this->reportsDashboard($request, 'consortium');
    }

    public function thinkTankReports(Request $request)
    {
        return $this->reportsDashboard($request, 'think_tank');
    }

    private function analysisDashboard(Request $request, string $analysisMode)
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $isConsortiumAnalysis = $analysisMode === 'consortium';
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : null;
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : null;
        $dateRangeLabel = match (true) {
            $startDate && $endDate => $startDate->format('M d, Y').' to '.$endDate->format('M d, Y'),
            (bool) $startDate => 'From '.$startDate->format('M d, Y'),
            (bool) $endDate => 'Up to '.$endDate->format('M d, Y'),
            default => 'All dates',
        };

        $thinkTanks = ConsortiumThinkTank::query()
            ->with([
                'consortium.programFunding.program',
                'vendorUser',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.trim((string) $request->input('q')).'%';
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', $search)
                        ->orWhere('country', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhereHas('consortium', fn ($consortiumQuery) => $consortiumQuery
                            ->where('name', 'like', $search)
                            ->orWhere('code', 'like', $search))
                        ->orWhereHas('vendorUser', fn ($vendorQuery) => $vendorQuery
                            ->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search));
                });
            })
            ->when($request->filled('consortium_id'), fn ($query) => $query->where('consortium_id', $request->input('consortium_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('name')
            ->get();

        $this->hydrateDirectoryFinance($thinkTanks, $startDate, $endDate);

        $portfolioRows = $thinkTanks
            ->map(function (ConsortiumThinkTank $thinkTank) {
                $purchaseOrders = $thinkTank->directoryPurchaseOrders ?? collect();
                $purchaseRequests = $thinkTank->directoryPurchaseRequests ?? collect();
                $paidDisbursements = $thinkTank->directoryDisbursements ?? collect();
                $purchaseRequestAmount = (float) $purchaseRequests
                    ->sum(fn (PurchaseRequest $purchaseRequest) => (float) $purchaseRequest->total_amount);
                $poAmount = (float) ($thinkTank->directory_po_amount ?? 0);
                $paidAmount = (float) ($thinkTank->directory_paid_amount ?? 0);
                $confirmedAmount = (float) $paidDisbursements
                    ->where('recipient_confirmation_status', 'confirmed')
                    ->sum(fn (ProcurementDisbursement $disbursement) => (float) $disbursement->amount);
                $latestPurchaseRequest = $purchaseRequests
                    ->sortByDesc(fn (PurchaseRequest $purchaseRequest) => $purchaseRequest->created_at?->getTimestamp() ?? 0)
                    ->first();
                $latestPurchaseOrder = $purchaseOrders
                    ->sortByDesc(fn (ProcurementPurchaseOrder $purchaseOrder) => $purchaseOrder->issued_at?->getTimestamp() ?? $purchaseOrder->created_at?->getTimestamp() ?? 0)
                    ->first();
                $latestDisbursement = $paidDisbursements
                    ->sortByDesc(fn (ProcurementDisbursement $disbursement) => $disbursement->paid_at?->getTimestamp() ?? $disbursement->created_at?->getTimestamp() ?? 0)
                    ->first();
                $latestPurchaseRequestDate = $latestPurchaseRequest?->created_at;
                $latestPurchaseOrderDate = $latestPurchaseOrder?->issued_at ?: $latestPurchaseOrder?->created_at;
                $latestDisbursementDate = $latestDisbursement?->paid_at ?: $latestDisbursement?->created_at;
                $consortiumName = $thinkTank->consortium?->name ?: 'No consortium';
                $consortiumCode = $thinkTank->consortium?->code;

                return [
                    'id' => $thinkTank->id,
                    'name' => $thinkTank->name,
                    'primary_name' => $thinkTank->name,
                    'country' => $thinkTank->country ?: 'N/A',
                    'status' => $thinkTank->status ?: 'active',
                    'consortium_id' => $thinkTank->consortium_id,
                    'consortium' => $consortiumName,
                    'consortium_code' => $consortiumCode,
                    'primary_meta' => $consortiumName.($consortiumCode ? ' | '.$consortiumCode : ''),
                    'secondary_meta' => ($thinkTank->country ?: 'N/A').' | '.($thinkTank->vendorUser?->name ?: 'Vendor identity not linked'),
                    'currency' => $purchaseOrders->first()?->resolved_currency ?: $thinkTank->consortium?->currency ?: 'USD',
                    'vendor_name' => $thinkTank->vendorUser?->name,
                    'think_tanks' => 1,
                    'active' => ($thinkTank->status ?: 'active') === 'active' ? 1 : 0,
                    'purchase_requests' => (int) ($thinkTank->directory_pr_count ?? 0),
                    'purchase_orders' => (int) ($thinkTank->directory_po_count ?? 0),
                    'disbursements' => (int) ($thinkTank->directory_disbursement_count ?? 0),
                    'purchase_request_amount' => round($purchaseRequestAmount, 2),
                    'po_amount' => round($poAmount, 2),
                    'paid_amount' => round($paidAmount, 2),
                    'open_amount' => round(max($poAmount - $paidAmount, 0), 2),
                    'confirmed_amount' => round($confirmedAmount, 2),
                    'unconfirmed_amount' => round(max($paidAmount - $confirmedAmount, 0), 2),
                    'payment_rate' => $poAmount > 0 ? round(min(100, ($paidAmount / $poAmount) * 100), 1) : 0,
                    'receipt_rate' => $paidAmount > 0 ? round(min(100, ($confirmedAmount / $paidAmount) * 100), 1) : 0,
                    'latest_purchase_request' => $latestPurchaseRequest?->reference_no,
                    'latest_purchase_request_at' => $latestPurchaseRequestDate?->toDateTimeString(),
                    'latest_purchase_request_date' => $latestPurchaseRequestDate?->format('M d, Y'),
                    'latest_purchase_order' => $latestPurchaseOrder?->reference_no,
                    'latest_purchase_order_at' => $latestPurchaseOrderDate?->toDateTimeString(),
                    'latest_purchase_order_date' => $latestPurchaseOrderDate?->format('M d, Y'),
                    'latest_disbursement' => $latestDisbursement?->reference_no,
                    'latest_disbursement_at' => $latestDisbursementDate?->toDateTimeString(),
                    'latest_disbursement_date' => $latestDisbursementDate?->format('M d, Y'),
                    'last_payment_at' => $paidDisbursements->max('paid_at'),
                ];
            })
            ->values();

        $summary = [
            'think_tanks' => $portfolioRows->count(),
            'active' => $portfolioRows->where('status', 'active')->count(),
            'purchase_requests' => (int) $portfolioRows->sum('purchase_requests'),
            'purchase_orders' => (int) $portfolioRows->sum('purchase_orders'),
            'disbursements' => (int) $portfolioRows->sum('disbursements'),
            'purchase_request_amount' => round((float) $portfolioRows->sum('purchase_request_amount'), 2),
            'po_amount' => round((float) $portfolioRows->sum('po_amount'), 2),
            'paid_amount' => round((float) $portfolioRows->sum('paid_amount'), 2),
            'open_amount' => round((float) $portfolioRows->sum('open_amount'), 2),
            'confirmed_amount' => round((float) $portfolioRows->sum('confirmed_amount'), 2),
            'unconfirmed_amount' => round((float) $portfolioRows->sum('unconfirmed_amount'), 2),
        ];

        $consortiumRows = $this->consortiumAnalysisRows($portfolioRows);
        $analysisRows = $isConsortiumAnalysis ? $consortiumRows : $portfolioRows;
        $summary['consortia'] = $consortiumRows->count();
        $summary['display_entities'] = $analysisRows->count();
        $summary['display_active_entities'] = $analysisRows->where('status', 'active')->count();
        $summary['payment_rate'] = $summary['po_amount'] > 0
            ? round(min(100, ($summary['paid_amount'] / $summary['po_amount']) * 100), 1)
            : 0;
        $summary['receipt_rate'] = $summary['paid_amount'] > 0
            ? round(min(100, ($summary['confirmed_amount'] / $summary['paid_amount']) * 100), 1)
            : 0;

        $topEntities = $analysisRows
            ->sortByDesc('po_amount')
            ->take(10)
            ->values();

        $analysisCopy = $isConsortiumAnalysis
            ? [
                'pageTitle' => 'Consortium Analysis',
                'heroTitle' => 'Consortium Analysis',
                'heroText' => 'Aggregated PR, PO, disbursement, receipt, and open-balance performance by consortium.',
                'filterRoute' => 'think-tanks-admin.consortium-analysis',
                'entityPlural' => 'Consortia',
                'entityColumn' => 'Consortium',
                'topTitle' => 'Top Consortia',
                'topSubtitle' => 'Largest consortium portfolios by PR, PO, paid disbursement, and open balance.',
                'tableTitle' => 'Consortium Financial Analysis',
                'tableDescription' => 'Purchase request, purchase order, disbursement, and receipt status grouped by consortium.',
                'emptyText' => 'No consortia matched this view.',
                'searchPlaceholder' => 'Consortium, code, country',
                'openBalanceMeta' => 'Remaining amount across consortium POs',
            ]
            : [
                'pageTitle' => 'Think Tank Analysis',
                'heroTitle' => 'Think Tank Analysis',
                'heroText' => 'Individual think-tank PR, PO, disbursement, receipt, and open-balance performance.',
                'filterRoute' => 'think-tanks-admin.think-tank-analysis',
                'entityPlural' => 'Think Tanks',
                'entityColumn' => 'Think Tank',
                'topTitle' => 'Top Think Tanks',
                'topSubtitle' => 'Largest think-tank portfolios by PR, PO, paid disbursement, and open balance.',
                'tableTitle' => 'Think Tank Financial Analysis',
                'tableDescription' => 'Purchase request, purchase order, disbursement, and receipt status by think tank.',
                'emptyText' => 'No think tanks matched this view.',
                'searchPlaceholder' => 'Think tank, country, vendor',
                'openBalanceMeta' => 'Remaining amount across think tank POs',
            ];

        $chartData = [
            'finance' => [
                'labels' => ['PR Amount', 'PO Amount', 'Paid Disbursements', 'Open PO Balance', 'Confirmed Receipts'],
                'values' => [
                    $summary['purchase_request_amount'],
                    $summary['po_amount'],
                    $summary['paid_amount'],
                    $summary['open_amount'],
                    $summary['confirmed_amount'],
                ],
            ],
            'pipeline' => [
                'labels' => ['Purchase Requests', 'Purchase Orders', 'Paid Disbursements'],
                'values' => [
                    $summary['purchase_requests'],
                    $summary['purchase_orders'],
                    $summary['disbursements'],
                ],
            ],
            'topEntities' => [
                'labels' => $topEntities->pluck('primary_name')->values(),
                'pr' => $topEntities->pluck('purchase_request_amount')->values(),
                'po' => $topEntities->pluck('po_amount')->values(),
                'paid' => $topEntities->pluck('paid_amount')->values(),
                'open' => $topEntities->pluck('open_amount')->values(),
            ],
            'receipts' => [
                'labels' => ['Confirmed Receipts', 'Awaiting Confirmation'],
                'values' => [
                    $summary['confirmed_amount'],
                    $summary['unconfirmed_amount'],
                ],
            ],
        ];

        $consortia = Consortium::with('programFunding.program')->orderBy('name')->get();
        $statuses = ['active', 'inactive', 'suspended', 'closed'];

        return view('think-tanks-admin.dashboard', compact(
            'thinkTanks',
            'portfolioRows',
            'consortiumRows',
            'analysisRows',
            'analysisMode',
            'analysisCopy',
            'summary',
            'chartData',
            'consortia',
            'statuses',
            'dateRangeLabel'
        ));
    }

    private function reportsDashboard(Request $request, string $reportMode)
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'consortium_id' => ['nullable', 'exists:attp_consortia,id'],
            'status' => ['nullable', 'string', 'max:40'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $isConsortiumReports = $reportMode === 'consortium';
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : null;
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : null;
        $dateRangeLabel = match (true) {
            $startDate && $endDate => $startDate->format('M d, Y').' to '.$endDate->format('M d, Y'),
            (bool) $startDate => 'From '.$startDate->format('M d, Y'),
            (bool) $endDate => 'Up to '.$endDate->format('M d, Y'),
            default => 'All dates',
        };

        $reports = ConsortiumActivityReport::query()
            ->with(['consortium.programFunding.program', 'member.consortium'])
            ->withCount('evidence')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.trim((string) $request->input('q')).'%';
                $query->where(function ($builder) use ($search) {
                    $builder->where('title', 'like', $search)
                        ->orWhere('summary', 'like', $search)
                        ->orWhereHas('consortium', fn ($consortiumQuery) => $consortiumQuery
                            ->where('name', 'like', $search)
                            ->orWhere('code', 'like', $search))
                        ->orWhereHas('member', fn ($memberQuery) => $memberQuery
                            ->where('name', 'like', $search)
                            ->orWhere('country', 'like', $search));
                });
            })
            ->when($request->filled('consortium_id'), fn ($query) => $query->where('consortium_id', $request->input('consortium_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($startDate || $endDate, function ($query) use ($startDate, $endDate) {
                $query->where(function ($dateQuery) use ($startDate, $endDate) {
                    $dateQuery
                        ->when($startDate, fn ($builder) => $builder->where('submitted_at', '>=', $startDate))
                        ->when($endDate, fn ($builder) => $builder->where('submitted_at', '<=', $endDate))
                        ->orWhere(function ($fallbackQuery) use ($startDate, $endDate) {
                            $fallbackQuery->whereNull('submitted_at')
                                ->when($startDate, fn ($builder) => $builder->where('created_at', '>=', $startDate))
                                ->when($endDate, fn ($builder) => $builder->where('created_at', '<=', $endDate));
                        });
                });
            })
            ->latest('submitted_at')
            ->latest()
            ->get();

        $reportRows = $this->reportOverviewRows($reports, $reportMode);
        $summary = [
            'entities' => $reportRows->count(),
            'reports' => $reports->count(),
            'submitted' => $reports->where('status', 'submitted')->count(),
            'approved' => $reports->where('status', 'approved')->count(),
            'attention' => $reports->whereIn('status', ['rejected', 'revisions_requested'])->count(),
            'evidence' => (int) $reports->sum(fn (ConsortiumActivityReport $report) => (int) ($report->evidence_count ?? 0)),
            'funds_spent' => round((float) $reports->sum(fn (ConsortiumActivityReport $report) => (float) ($report->funds_spent ?? 0)), 2),
            'avg_progress' => round((float) $reports->avg(fn (ConsortiumActivityReport $report) => (float) ($report->progress_percent ?? 0)), 1),
        ];

        $reportCopy = $isConsortiumReports
            ? [
                'pageTitle' => 'Consortium Reports',
                'heroTitle' => 'Consortium Reports',
                'heroText' => 'Submission, approval, evidence, progress, and spending visibility grouped by consortium.',
                'filterRoute' => 'think-tanks-admin.consortium-reports',
                'entityPlural' => 'Consortia',
                'entityColumn' => 'Consortium',
                'tableTitle' => 'Consortium Report Register',
                'tableDescription' => 'Activity report status, evidence, progress, and spend by consortium.',
                'emptyText' => 'No consortium reports matched this view.',
                'searchPlaceholder' => 'Consortium, code, report title',
            ]
            : [
                'pageTitle' => 'Think Tank Reports',
                'heroTitle' => 'Think Tank Reports',
                'heroText' => 'Submission, approval, evidence, progress, and spending visibility by think tank.',
                'filterRoute' => 'think-tanks-admin.think-tank-reports',
                'entityPlural' => 'Think Tanks',
                'entityColumn' => 'Think Tank',
                'tableTitle' => 'Think Tank Report Register',
                'tableDescription' => 'Activity report status, evidence, progress, and spend by individual think tank.',
                'emptyText' => 'No think tank reports matched this view.',
                'searchPlaceholder' => 'Think tank, country, report title',
            ];

        $chartData = [
            'status' => [
                'labels' => ['Submitted', 'Approved', 'Needs Attention'],
                'values' => [$summary['submitted'], $summary['approved'], $summary['attention']],
            ],
            'topEntities' => [
                'labels' => $reportRows->sortByDesc('reports')->take(10)->pluck('primary_name')->values(),
                'reports' => $reportRows->sortByDesc('reports')->take(10)->pluck('reports')->values(),
                'evidence' => $reportRows->sortByDesc('reports')->take(10)->pluck('evidence')->values(),
            ],
        ];

        $consortia = Consortium::with('programFunding.program')->orderBy('name')->get();
        $statuses = ['submitted', 'approved', 'rejected', 'revisions_requested', 'draft'];
        $recentReports = $reports->take(12)->values();

        return view('think-tanks-admin.reports', compact(
            'reports',
            'reportRows',
            'recentReports',
            'reportMode',
            'reportCopy',
            'summary',
            'chartData',
            'consortia',
            'statuses',
            'dateRangeLabel'
        ));
    }

    private function consortiumAnalysisRows($portfolioRows)
    {
        return $portfolioRows
            ->groupBy(fn (array $row) => $row['consortium_id'] ?: 'unassigned')
            ->map(function ($rows, $consortiumId) {
                $first = $rows->first();
                $poAmount = (float) $rows->sum('po_amount');
                $paidAmount = (float) $rows->sum('paid_amount');
                $confirmedAmount = (float) $rows->sum('confirmed_amount');
                $latestPurchaseRequest = $rows
                    ->sortByDesc(fn (array $row) => strtotime((string) ($row['latest_purchase_request_at'] ?? '')) ?: 0)
                    ->first();
                $latestPurchaseOrder = $rows
                    ->sortByDesc(fn (array $row) => strtotime((string) ($row['latest_purchase_order_at'] ?? '')) ?: 0)
                    ->first();
                $latestDisbursement = $rows
                    ->sortByDesc(fn (array $row) => strtotime((string) ($row['latest_disbursement_at'] ?? '')) ?: 0)
                    ->first();
                $countrySummary = $rows
                    ->pluck('country')
                    ->filter(fn ($country) => filled($country) && $country !== 'N/A')
                    ->unique()
                    ->take(3)
                    ->join(', ');

                return [
                    'id' => $consortiumId,
                    'name' => $first['consortium'],
                    'primary_name' => $first['consortium'],
                    'country' => $countrySummary ?: 'Multiple countries',
                    'status' => $rows->where('status', 'active')->isNotEmpty() ? 'active' : ($first['status'] ?? 'inactive'),
                    'consortium_id' => $first['consortium_id'],
                    'consortium' => $first['consortium'],
                    'consortium_code' => $first['consortium_code'],
                    'primary_meta' => $first['consortium_code'] ?: 'No consortium code',
                    'secondary_meta' => ($countrySummary ?: 'Multiple countries').' | '.ucfirst((string) ($first['status'] ?? 'active')),
                    'currency' => $first['currency'],
                    'vendor_name' => null,
                    'think_tanks' => $rows->count(),
                    'active' => $rows->where('status', 'active')->count(),
                    'purchase_requests' => (int) $rows->sum('purchase_requests'),
                    'purchase_orders' => (int) $rows->sum('purchase_orders'),
                    'disbursements' => (int) $rows->sum('disbursements'),
                    'purchase_request_amount' => round((float) $rows->sum('purchase_request_amount'), 2),
                    'po_amount' => round($poAmount, 2),
                    'paid_amount' => round($paidAmount, 2),
                    'open_amount' => round(max($poAmount - $paidAmount, 0), 2),
                    'confirmed_amount' => round($confirmedAmount, 2),
                    'unconfirmed_amount' => round(max($paidAmount - $confirmedAmount, 0), 2),
                    'payment_rate' => $poAmount > 0 ? round(min(100, ($paidAmount / $poAmount) * 100), 1) : 0,
                    'receipt_rate' => $paidAmount > 0 ? round(min(100, ($confirmedAmount / $paidAmount) * 100), 1) : 0,
                    'latest_purchase_request' => $latestPurchaseRequest['latest_purchase_request'] ?? null,
                    'latest_purchase_request_at' => $latestPurchaseRequest['latest_purchase_request_at'] ?? null,
                    'latest_purchase_request_date' => $latestPurchaseRequest['latest_purchase_request_date'] ?? null,
                    'latest_purchase_order' => $latestPurchaseOrder['latest_purchase_order'] ?? null,
                    'latest_purchase_order_at' => $latestPurchaseOrder['latest_purchase_order_at'] ?? null,
                    'latest_purchase_order_date' => $latestPurchaseOrder['latest_purchase_order_date'] ?? null,
                    'latest_disbursement' => $latestDisbursement['latest_disbursement'] ?? null,
                    'latest_disbursement_at' => $latestDisbursement['latest_disbursement_at'] ?? null,
                    'latest_disbursement_date' => $latestDisbursement['latest_disbursement_date'] ?? null,
                    'last_payment_at' => $latestDisbursement['last_payment_at'] ?? null,
                ];
            })
            ->sortBy('primary_name')
            ->values();
    }

    private function reportOverviewRows($reports, string $reportMode)
    {
        $grouped = $reportMode === 'consortium'
            ? $reports->groupBy(fn (ConsortiumActivityReport $report) => (string) ($report->consortium_id ?: 'unassigned'))
            : $reports->groupBy(fn (ConsortiumActivityReport $report) => (string) ($report->think_tank_member_id ?: 'unassigned'));

        return $grouped
            ->map(function ($rows, $entityId) use ($reportMode) {
                $first = $rows->first();
                $latest = $rows
                    ->sortByDesc(fn (ConsortiumActivityReport $report) => $report->submitted_at?->getTimestamp() ?? $report->created_at?->getTimestamp() ?? 0)
                    ->first();
                $approved = $rows->where('status', 'approved')->count();
                $submitted = $rows->where('status', 'submitted')->count();
                $attention = $rows->whereIn('status', ['rejected', 'revisions_requested'])->count();
                $total = $rows->count();
                $isConsortium = $reportMode === 'consortium';
                $entityName = $isConsortium
                    ? ($first->consortium?->name ?: 'Unassigned Consortium')
                    : ($first->member?->name ?: 'Unassigned Think Tank');
                $primaryMeta = $isConsortium
                    ? ($first->consortium?->code ?: 'No consortium code')
                    : ($first->member?->country ?: 'N/A');
                $secondaryMeta = $isConsortium
                    ? ($first->consortium?->programFunding?->program?->name ?: 'No program linked')
                    : ucfirst((string) ($first->member?->status ?: 'active'));

                return [
                    'id' => $entityId,
                    'primary_name' => $entityName,
                    'primary_meta' => $primaryMeta,
                    'secondary_meta' => $secondaryMeta,
                    'reports' => $total,
                    'submitted' => $submitted,
                    'approved' => $approved,
                    'attention' => $attention,
                    'evidence' => (int) $rows->sum(fn (ConsortiumActivityReport $report) => (int) ($report->evidence_count ?? 0)),
                    'funds_spent' => round((float) $rows->sum(fn (ConsortiumActivityReport $report) => (float) ($report->funds_spent ?? 0)), 2),
                    'avg_progress' => round((float) $rows->avg(fn (ConsortiumActivityReport $report) => (float) ($report->progress_percent ?? 0)), 1),
                    'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
                    'latest_title' => $latest?->title,
                    'latest_status' => $latest?->status,
                    'latest_date' => ($latest?->submitted_at ?: $latest?->created_at)?->format('M d, Y'),
                ];
            })
            ->sortBy('primary_name')
            ->values();
    }

    public function directory(Request $request)
    {
        $allDirectoryMembers = ConsortiumThinkTank::query()
            ->get(['id', 'portal_user_id', 'vendor_user_id']);
        $directoryFinance = $this->directoryFinanceTotals($allDirectoryMembers);

        $summary = [
            'total' => ConsortiumThinkTank::count(),
            'active' => ConsortiumThinkTank::where('status', 'active')->count(),
            'system_dataset' => ThinkDataset::count(),
            'dataset_linked' => ConsortiumThinkTank::whereNotNull('think_dataset_id')->count(),
            'portal_linked' => ConsortiumThinkTank::whereNotNull('portal_user_id')->count(),
            'vendor_linked' => ConsortiumThinkTank::whereNotNull('vendor_user_id')->count(),
            'approved_ops' => (float) ConsortiumThinkTank::sum('budget_allocated') + (float) ConsortiumFundAllocation::sum('amount_allocated'),
            'linked_po_amount' => $directoryFinance['po_amount'],
            'linked_po_count' => $directoryFinance['po_count'],
            'transferred' => $directoryFinance['paid_amount'],
            'paid_disbursement_count' => $directoryFinance['paid_disbursement_count'],
        ];

        $thinkTanks = ConsortiumThinkTank::query()
            ->with([
                'consortium.programFunding.program',
                'thinkDataset',
                'portalUser',
                'vendorUser',
            ])
            ->withSum('fundAllocations', 'amount_allocated')
            ->withCount([
                'reports',
                'researchOutputs',
                'procurementPlans',
                'procurements',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.trim((string) $request->input('q')).'%';
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', $search)
                        ->orWhere('country', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhereHas('thinkDataset', function ($datasetQuery) use ($search) {
                            $datasetQuery->where('tt_name_en', 'like', $search)
                                ->orWhere('ottd_id', 'like', $search)
                                ->orWhere('g_email', 'like', $search)
                                ->orWhere('website', 'like', $search);
                        });
                });
            })
            ->when($request->filled('consortium_id'), fn ($query) => $query->where('consortium_id', $request->input('consortium_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->input('portal') === 'linked', fn ($query) => $query->whereNotNull('portal_user_id'))
            ->when($request->input('portal') === 'unlinked', fn ($query) => $query->whereNull('portal_user_id'))
            ->when($request->input('dataset') === 'linked', fn ($query) => $query->whereNotNull('think_dataset_id'))
            ->when($request->input('dataset') === 'unlinked', fn ($query) => $query->whereNull('think_dataset_id'))
            ->orderBy('name')
            ->get();

        $this->hydrateDirectoryFinance($thinkTanks);

        $consortia = Consortium::with('programFunding.program')->orderBy('name')->get();
        $consortiumRollups = $this->directoryConsortiumRollups($thinkTanks, $consortia);

        $thinkDatasets = ThinkDataset::query()
            ->orderBy('tt_name_en')
            ->get(['id', 'ottd_id', 'tt_name_en', 'country', 'g_email', 'website', 'is_validated']);

        $datasetLookup = $thinkDatasets
            ->mapWithKeys(fn (ThinkDataset $dataset) => [
                $dataset->id => [
                    'name' => $dataset->tt_name_en,
                    'country' => $dataset->country,
                    'email' => $dataset->g_email,
                    'website' => $dataset->website,
                ],
            ])
            ->all();

        return view('think-tanks-admin.directory', [
            'thinkTanks' => $thinkTanks,
            'consortia' => $consortia,
            'consortiumRollups' => $consortiumRollups,
            'thinkDatasets' => $thinkDatasets,
            'datasetLookup' => $datasetLookup,
            'roles' => ['lead', 'member', 'implementing_partner'],
            'statuses' => ['active', 'inactive', 'suspended', 'closed'],
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $this->hydrateDatasetFields($request);
        $data = $request->validate($this->memberRules());
        $data['joined_at'] = $data['joined_at'] ?? now()->toDateString();

        [$portalUser, $portalUserCreated] = $this->resolvePortalUser($data);
        $data['portal_user_id'] = $portalUser?->id;

        $assignment = DB::transaction(function () use ($data, $portalUser): array {
            $member = ConsortiumThinkTank::query()->create($data);
            $assignedUser = $portalUser
                ? $this->userManagement->assignAdministrator($portalUser, $member)
                : null;

            return ['member' => $member, 'user' => $assignedUser];
        });
        $member = $assignment['member'];
        $portalUser = $assignment['user'];

        $deliverySucceeded = $portalUser?->email
            ? ($portalUserCreated
                ? $this->invitations->send($portalUser, true)
                : $this->sendWelcomeSafely($portalUser, $member))
            : null;

        $this->auditAction('think_tank.created', 'Think tank profile created', [
            'think_tank_member_id' => $member->id,
            'think_tank_name' => $member->name,
            'consortium_id' => $member->consortium_id,
        ]);

        return redirect()
            ->route('think-tanks-admin.show', $member)
            ->with('success', match (true) {
                $portalUserCreated && $deliverySucceeded => 'Think tank profile created. A secure, single-use password setup link was sent.',
                $portalUserCreated => 'Think tank profile created, but the secure invitation could not be delivered. Ask the user to use Forgot password.',
                $deliverySucceeded === true => 'Think tank profile created. The existing portal account was notified; its password was not changed.',
                $deliverySucceeded === false => 'Think tank profile created. The existing account password was not changed, but the access notification could not be delivered.',
                default => 'Think tank profile created.',
            });
    }

    public function show(ConsortiumThinkTank $thinkTank)
    {
        $thinkTank->load([
            'consortium.programFunding.program',
            'thinkDataset',
            'portalUser',
            'vendorUser',
            'fundAllocations.disbursementRequests',
            'reports',
            'researchOutputs',
            'procurementPlans',
            'procurements',
        ]);

        $consortiumMembers = $thinkTank->consortium_id
            ? ConsortiumThinkTank::query()
                ->with([
                    'consortium.programFunding.program',
                    'portalUser',
                    'vendorUser',
                    'thinkDataset',
                    'fundAllocations',
                    'reports',
                    'researchOutputs',
                    'procurementPlans',
                    'procurements',
                ])
                ->withCount(['reports', 'researchOutputs', 'procurements'])
                ->where('consortium_id', $thinkTank->consortium_id)
                ->orderBy('name')
                ->get()
            : collect([$thinkTank]);

        $this->hydrateDirectoryFinance($consortiumMembers);

        $thinkTank = $consortiumMembers->firstWhere('id', $thinkTank->id) ?: $thinkTank;
        $consortiumRollup = $this->directoryConsortiumRollupRow($thinkTank->consortium, $consortiumMembers);

        return view('think-tanks-admin.show', compact('thinkTank', 'consortiumMembers', 'consortiumRollup'));
    }

    public function update(Request $request, ConsortiumThinkTank $thinkTank)
    {
        $this->hydrateDatasetFields($request);
        $data = $request->validate($this->memberRules($thinkTank));
        [$portalUser] = $this->resolvePortalUser($data, false, $thinkTank);
        $data['portal_user_id'] = $portalUser?->id ?? $data['portal_user_id'] ?? null;

        DB::transaction(function () use ($data, $portalUser, $thinkTank): void {
            $thinkTank->update($data);

            if ($portalUser) {
                $this->userManagement->assignAdministrator($portalUser, $thinkTank);
            }
        });

        $this->auditAction('think_tank.updated', 'Think tank profile updated', [
            'think_tank_member_id' => $thinkTank->id,
            'think_tank_name' => $thinkTank->name,
            'changes' => $thinkTank->getChanges(),
        ]);

        return redirect()
            ->route('think-tanks-admin.show', $thinkTank)
            ->with('success', 'Think tank profile updated.');
    }

    public function updateLogo(
        Request $request,
        ConsortiumThinkTank $thinkTank,
        ThinkTankLogoService $logos
    ) {
        abort_unless(
            $request->user()?->isAdmin() || $request->user()?->isSuperAdmin(),
            403,
            'Only a system administrator can update think tank branding.'
        );

        $request->validate([
            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=5000,max_height=5000',
            ],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $remove = $request->boolean('remove_logo');

        if (! $request->hasFile('logo') && ! $remove) {
            throw ValidationException::withMessages([
                'logo' => 'Choose a PNG, JPEG, or WebP logo to upload.',
            ]);
        }

        $result = $logos->replace($thinkTank, $request->file('logo'), $remove);
        $action = $result['removed'] ? 'think_tank.logo.removed' : 'think_tank.logo.updated';
        $message = $result['removed'] ? 'Think tank logo removed' : 'Think tank logo updated';

        $this->auditAction($action, $message, [
            'think_tank_member_id' => $thinkTank->id,
            'think_tank_name' => $thinkTank->name,
            'had_previous_logo' => filled($result['previous_path']),
        ]);

        return back()->with('success', $message.'.');
    }

    public function funding(Request $request)
    {
        $source = $this->fundingSource();
        $summary = $this->budgetSummary($source);

        $thinkTanks = ConsortiumThinkTank::query()
            ->with([
                'consortium',
                'transferPurchaseOrders' => fn ($query) => $query
                    ->with([
                        'purchaseRequest',
                        'budgetCommitment.purchaseRequest',
                        'disbursements' => fn ($disbursementQuery) => $this->paidDisbursements($disbursementQuery)
                            ->latest('paid_at')
                            ->latest(),
                    ])
                    ->latest('issued_at')
                    ->latest(),
                'transferDisbursements' => fn ($query) => $query
                    ->with(['purchaseOrder.purchaseRequest', 'purchaseOrder.budgetCommitment.purchaseRequest', 'fundAllocation', 'consortiumDisbursementRequest', 'recipientConfirmer'])
                    ->whereNotNull('paid_at')
                    ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
                    ->whereHas('purchaseOrder', fn ($purchaseOrderQuery) => $purchaseOrderQuery->where('po_type', 'think_tank_transfer'))
                    ->latest('paid_at')
                    ->latest(),
            ])
            ->withSum('fundAllocations', 'amount_allocated')
            ->withSum('transferPurchaseOrders', 'amount')
            ->withSum([
                'transferDisbursements as paid_transfer_disbursements_sum_amount' => fn ($query) => $this->fundingTransferDisbursements($query),
            ], 'amount')
            ->withCount([
                'transferPurchaseOrders',
                'transferDisbursements as paid_transfer_disbursements_count' => fn ($query) => $this->fundingTransferDisbursements($query),
                'transferDisbursements as confirmed_transfers_count' => fn ($query) => $this->fundingTransferDisbursements($query)
                    ->where('recipient_confirmation_status', 'confirmed'),
            ])
            ->orderBy('name')
            ->get();

        return view('think-tanks-admin.funding', [
            'source' => $source,
            'summary' => $summary,
            'thinkTanks' => $thinkTanks,
        ]);
    }

    public function createFunding()
    {
        $source = $this->fundingSource();
        $summary = $this->budgetSummary($source);

        return view('think-tanks-admin.funding-create', [
            'source' => $source,
            'summary' => $summary,
            'thinkTanks' => ConsortiumThinkTank::with('consortium')->orderBy('name')->get(),
        ]);
    }

    public function fundingHistory()
    {
        $source = $this->fundingSource();
        $summary = $this->budgetSummary($source);

        $transfers = ProcurementDisbursement::query()
            ->with([
                'thinkTankMember.consortium',
                'thinkTankMember.portalUser',
                'purchaseOrder.budgetCommitment.purchaseRequest',
                'purchaseOrder.budgetCommitment.approver',
                'purchaseOrder.budgetCommitment.creator',
                'fundAllocation',
                'consortiumDisbursementRequest',
                'recipientConfirmer',
            ])
            ->whereNotNull('think_tank_member_id')
            ->whereHas('purchaseOrder', fn ($query) => $query->where('po_type', 'think_tank_transfer'))
            ->latest('paid_at')
            ->latest()
            ->paginate(15);

        return view('think-tanks-admin.funding-history', [
            'source' => $source,
            'summary' => $summary,
            'transfers' => $transfers,
        ]);
    }

    public function storeFunding(Request $request)
    {
        $source = $this->fundingSource();
        if (! $source['programFunding'] || ! $source['subActivity']) {
            return back()->with('error', 'The African Think Tank Project / Funding to Think Tanks budget source could not be found.');
        }

        $data = $request->validate([
            'think_tank_member_id' => 'required|exists:attp_consortium_think_tanks,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'payment_method' => 'required|string|max:80',
            'transfer_reference' => 'nullable|string|max:120',
            'paid_at' => 'required|date',
            'notes' => 'nullable|string|max:3000',
        ]);

        $summary = $this->budgetSummary($source);
        if ((float) $data['amount'] > (float) $summary['remaining']) {
            return back()
                ->withErrors(['amount' => 'Transfer exceeds remaining Funding to Think Tanks budget. Available: '.number_format($summary['remaining'], 2)])
                ->withInput();
        }

        $member = ConsortiumThinkTank::with('consortium')->findOrFail($data['think_tank_member_id']);
        $currency = 'USD';
        $paidAt = Carbon::parse($data['paid_at']);

        DB::transaction(function () use ($data, $source, $member, $currency, $paidAt, $request) {
            $purchaseRequest = PurchaseRequest::create([
                'reference_no' => $this->nextReference('PR-TT'),
                'program_funding_id' => $source['programFunding']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'allocation_level' => 'sub_activity',
                'allocation_id' => $source['subActivity']->id,
                'start_year' => (int) $paidAt->format('Y'),
                'commitment_date' => now()->toDateString(),
                'delivery_date' => $paidAt->toDateString(),
                'currency' => $currency,
                'total_amount' => $data['amount'],
                'description' => 'Funding transfer to think tank: '.$member->name,
                'status' => 'approved',
                'created_by' => $request->user()?->id,
            ]);

            $commitment = BudgetCommitment::create([
                'purchase_request_id' => $purchaseRequest->id,
                'program_funding_id' => $source['programFunding']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'allocation_level' => 'sub_activity',
                'allocation_id' => $source['subActivity']->id,
                'commitment_amount' => $data['amount'],
                'commitment_year' => (int) $paidAt->format('Y'),
                'status' => BudgetCommitment::STATUS_APPROVED,
                'description' => 'Funding to Think Tanks transfer for '.$member->name,
                'created_by' => $request->user()?->id,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);

            $allocation = ConsortiumFundAllocation::create([
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'program_funding_id' => $source['programFunding']->id,
                'budget_line' => 'Funding to Think Tanks',
                'currency' => $currency,
                'amount_allocated' => $data['amount'],
                'amount_committed' => $data['amount'],
                'amount_disbursed' => $data['amount'],
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice = ProcurementInvoice::create([
                'vendor_id' => $member->vendor_user_id ?: $member->portal_user_id,
                'sub_activity_id' => $source['subActivity']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'invoice_month' => $paidAt->copy()->startOfMonth()->toDateString(),
                'reference_no' => ProcurementInvoice::generateReference(),
                'amount' => $data['amount'],
                'currency' => $currency,
                'status' => 'paid',
                'created_by' => $request->user()?->id,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'notes' => 'Paid Funding to Think Tanks transfer for '.$member->name.(! empty($data['notes']) ? ': '.$data['notes'] : ''),
            ]);

            $purchaseOrder = ProcurementPurchaseOrder::create([
                'invoice_id' => $invoice->id,
                'budget_commitment_id' => $commitment->id,
                'sub_activity_id' => $source['subActivity']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'vendor_id' => $member->vendor_user_id ?: $member->portal_user_id,
                'reference_no' => ProcurementPurchaseOrder::generateThinkTankTransferReference($member),
                'po_type' => 'think_tank_transfer',
                'amount' => $data['amount'],
                'currency' => $currency,
                'status' => 'pending',
                'created_by' => $request->user()?->id,
                'issued_at' => $paidAt,
            ]);

            $disbursementRequest = $allocation->disbursementRequests()->create([
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'request_code' => $this->nextReference('ATTP-DISB'),
                'amount_requested' => $data['amount'],
                'amount_approved' => $data['amount'],
                'currency' => $currency,
                'status' => 'paid',
                'purpose' => $data['notes'] ?? 'Funding to Think Tanks transfer',
                'requested_by' => $request->user()?->id,
                'requested_at' => now(),
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'paid_at' => $paidAt,
            ]);

            $disbursement = ProcurementDisbursement::create([
                'purchase_order_id' => $purchaseOrder->id,
                'vendor_id' => $member->vendor_user_id ?: $member->portal_user_id,
                'sub_activity_id' => $source['subActivity']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'fund_allocation_id' => $allocation->id,
                'consortium_disbursement_request_id' => $disbursementRequest->id,
                'reference_no' => ProcurementDisbursement::generateReference(),
                'amount' => $data['amount'],
                'currency' => $currency,
                'payment_method' => $data['payment_method'],
                'transfer_reference' => $data['transfer_reference'] ?? null,
                'status' => 'paid',
                'recipient_confirmation_status' => 'pending',
                'paid_at' => $paidAt,
                'created_by' => $request->user()?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditAction('think_tank.transfer.created', 'Funding transfer recorded for think tank', [
                'disbursement_id' => $disbursement->id,
                'reference_no' => $disbursement->reference_no,
                'think_tank_member_id' => $member->id,
                'think_tank_name' => $member->name,
                'amount' => (float) $data['amount'],
                'currency' => $currency,
            ]);
        });

        return redirect()
            ->route('think-tanks-admin.funding.history')
            ->with('success', 'Funding transfer recorded. The think tank can now confirm receipt from its portal.');
    }

    public function updateFundingTransfer(Request $request, ProcurementDisbursement $transfer)
    {
        abort_unless((bool) $transfer->think_tank_member_id, 404);

        $source = $this->fundingSource();
        if (! $source['programFunding'] || ! $source['subActivity']) {
            return back()->with('error', 'The Funding to Think Tanks budget source could not be found.');
        }

        $transfer->load([
            'thinkTankMember',
            'purchaseOrder.invoice',
            'purchaseOrder.budgetCommitment.purchaseRequest',
            'fundAllocation',
            'consortiumDisbursementRequest',
        ]);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:80',
            'transfer_reference' => 'nullable|string|max:120',
            'paid_at' => 'required|date',
            'notes' => 'nullable|string|max:3000',
        ]);

        $oldAmount = (float) $transfer->amount;
        $newAmount = round((float) $data['amount'], 2);
        $summary = $this->budgetSummary($source);
        $availableIncludingThisTransfer = (float) $summary['remaining'] + $oldAmount;

        if ($newAmount > $availableIncludingThisTransfer) {
            return back()
                ->withErrors(['amount' => 'Updated transfer exceeds remaining Funding to Think Tanks budget. Available including this transfer: '.number_format($availableIncludingThisTransfer, 2)])
                ->withInput();
        }

        $paidAt = Carbon::parse($data['paid_at']);
        $currency = 'USD';

        DB::transaction(function () use ($transfer, $data, $oldAmount, $newAmount, $paidAt, $currency, $request) {
            $transfer->update([
                'amount' => $newAmount,
                'currency' => $currency,
                'payment_method' => $data['payment_method'],
                'transfer_reference' => $data['transfer_reference'] ?? null,
                'paid_at' => $paidAt,
                'notes' => $data['notes'] ?? null,
            ]);

            $purchaseOrder = $transfer->purchaseOrder;
            if ($purchaseOrder) {
                $invoice = $purchaseOrder->invoice;
                if ($invoice) {
                    $invoice->update([
                        'vendor_id' => $purchaseOrder->vendor_id,
                        'sub_activity_id' => $purchaseOrder->sub_activity_id,
                        'governance_node_id' => $purchaseOrder->governance_node_id,
                        'invoice_month' => $paidAt->copy()->startOfMonth()->toDateString(),
                        'amount' => $newAmount,
                        'currency' => $currency,
                        'status' => 'paid',
                        'approved_by' => $request->user()?->id,
                        'approved_at' => $invoice->approved_at ?: now(),
                        'notes' => 'Paid Funding to Think Tanks transfer for '.($transfer->thinkTankMember?->name ?? 'think tank').(! empty($data['notes']) ? ': '.$data['notes'] : ''),
                    ]);
                } else {
                    $invoice = ProcurementInvoice::create([
                        'vendor_id' => $purchaseOrder->vendor_id,
                        'sub_activity_id' => $purchaseOrder->sub_activity_id,
                        'governance_node_id' => $purchaseOrder->governance_node_id,
                        'invoice_month' => $paidAt->copy()->startOfMonth()->toDateString(),
                        'reference_no' => ProcurementInvoice::generateReference(),
                        'amount' => $newAmount,
                        'currency' => $currency,
                        'status' => 'paid',
                        'created_by' => $request->user()?->id,
                        'approved_by' => $request->user()?->id,
                        'approved_at' => now(),
                        'notes' => 'Paid Funding to Think Tanks transfer for '.($transfer->thinkTankMember?->name ?? 'think tank').(! empty($data['notes']) ? ': '.$data['notes'] : ''),
                    ]);
                }

                $purchaseOrder->update([
                    'invoice_id' => $invoice->id,
                    'amount' => $newAmount,
                    'currency' => $currency,
                    'issued_at' => $paidAt,
                    'status' => $transfer->recipient_confirmation_status === 'confirmed' ? 'fully_paid' : 'pending',
                ]);
            }

            $commitment = $purchaseOrder?->budgetCommitment;
            if ($commitment) {
                $commitment->update([
                    'commitment_amount' => $newAmount,
                    'commitment_year' => (int) $paidAt->format('Y'),
                    'description' => 'Funding to Think Tanks transfer for '.($transfer->thinkTankMember?->name ?? 'think tank'),
                    'status' => BudgetCommitment::STATUS_APPROVED,
                    'approved_by' => $request->user()?->id,
                    'approved_at' => now(),
                ]);
            }

            $purchaseRequest = $commitment?->purchaseRequest;
            if ($purchaseRequest) {
                $purchaseRequest->update([
                    'start_year' => (int) $paidAt->format('Y'),
                    'delivery_date' => $paidAt->toDateString(),
                    'currency' => $currency,
                    'total_amount' => $newAmount,
                    'description' => 'Funding transfer to think tank: '.($transfer->thinkTankMember?->name ?? 'think tank'),
                    'status' => 'approved',
                ]);
            }

            if ($transfer->fundAllocation) {
                $transfer->fundAllocation->update([
                    'currency' => $currency,
                    'amount_allocated' => $newAmount,
                    'amount_committed' => $newAmount,
                    'amount_disbursed' => $newAmount,
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            if ($transfer->consortiumDisbursementRequest) {
                $transfer->consortiumDisbursementRequest->update([
                    'amount_requested' => $newAmount,
                    'amount_approved' => $newAmount,
                    'currency' => $currency,
                    'purpose' => $data['notes'] ?? 'Funding to Think Tanks transfer',
                    'reviewed_by' => $request->user()?->id,
                    'reviewed_at' => now(),
                    'paid_at' => $paidAt,
                ]);
            }

            $this->auditAction('think_tank.transfer.updated', 'Funding transfer updated for think tank', [
                'disbursement_id' => $transfer->id,
                'reference_no' => $transfer->reference_no,
                'think_tank_member_id' => $transfer->think_tank_member_id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'currency' => $currency,
            ]);
        });

        return back()->with('success', 'Funding transfer updated and the finance trail was synchronized.');
    }

    private function memberRules(?ConsortiumThinkTank $member = null): array
    {
        return [
            'consortium_id' => 'required|exists:attp_consortia,id',
            'think_dataset_id' => [
                'nullable',
                'exists:think_datasets,id',
                Rule::unique('attp_consortium_think_tanks', 'think_dataset_id')
                    ->where(fn ($query) => $query->where('consortium_id', request('consortium_id', $member?->consortium_id)))
                    ->ignore($member?->id),
            ],
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('attp_consortium_think_tanks', 'email')->ignore($member?->id),
            ],
            'role' => 'required|in:lead,member,implementing_partner',
            'status' => 'nullable|in:active,inactive,suspended,closed',
            'budget_allocated' => 'nullable|numeric|min:0',
            'joined_at' => 'nullable|date',
            'portal_user_id' => 'nullable|exists:users,id',
            'vendor_user_id' => 'nullable|exists:users,id',
            'au_sap_vendor_number' => 'nullable|string|max:120',
        ];
    }

    private function hydrateDatasetFields(Request $request): void
    {
        if (! $request->filled('think_dataset_id')) {
            return;
        }

        $dataset = ThinkDataset::find($request->input('think_dataset_id'));
        if (! $dataset) {
            return;
        }

        $request->merge([
            'name' => $request->input('name') ?: $dataset->tt_name_en,
            'country' => $request->input('country') ?: $dataset->country,
            'email' => $request->input('email') ?: $dataset->g_email,
        ]);
    }

    private function resolvePortalUser(
        array $data,
        bool $createWhenMissing = true,
        ?ConsortiumThinkTank $expectedMembership = null,
    ): array {
        if (! empty($data['portal_user_id'])) {
            $user = User::query()->findOrFail($data['portal_user_id']);
            $this->userManagement->assertCanBeAssignedToMembership($user, $expectedMembership);

            return [$user, false];
        }

        if (! $createWhenMissing || empty($data['email'])) {
            return [null, false];
        }

        $resolved = $this->userManagement->resolveOrCreateUnassignedAdministrator(
            $data['name'],
            $data['email'],
        );

        return [$resolved['user'], $resolved['created']];
    }

    private function sendWelcomeSafely(User $user, ConsortiumThinkTank $member): bool
    {
        if (! $member->consortium) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new ThinkTankPortalWelcome($member, $member->consortium, $user));

            return true;
        } catch (Throwable) {
            // Mail failure should not block profile creation.
            return false;
        }
    }

    private function fundingSource(): array
    {
        $program = Program::query()
            ->where('name', 'like', '%African Think%')
            ->orWhere('program_id', 'like', 'PROG00001%')
            ->with(['projects.activities.subActivities'])
            ->first();

        $programFunding = ProgramFunding::query()
            ->where('status', 'approved')
            ->when($program, fn ($query) => $query->where('program_id', $program->id))
            ->orderByDesc('approved_at')
            ->orderByDesc('approved_amount')
            ->first()
            ?: ProgramFunding::where('status', 'approved')
                ->where('program_name', 'like', '%African Think%')
                ->orderByDesc('approved_at')
                ->first();

        $subActivity = SubActivity::query()
            ->where('name', 'like', '%Funding to Think Tanks%')
            ->with('activity.project.program')
            ->first();

        return [
            'program' => $program ?: $subActivity?->activity?->project?->program,
            'programFunding' => $programFunding,
            'subActivity' => $subActivity,
        ];
    }

    private function budgetSummary(array $source): array
    {
        $subActivity = $source['subActivity'];
        $allocated = $subActivity ? (float) $subActivity->allocations()->sum('amount') : 0.0;
        $budget = $allocated;
        $fundingPurchaseOrderIds = ProcurementPurchaseOrder::query()
            ->whereNotNull('think_tank_member_id')
            ->where('po_type', 'think_tank_transfer')
            ->select('procurement_purchase_orders.id');

        $poAllocated = (float) ProcurementPurchaseOrder::query()
            ->whereNotNull('think_tank_member_id')
            ->where('po_type', 'think_tank_transfer')
            ->sum('amount');

        $transferred = (float) ProcurementDisbursement::query()
            ->whereIn('purchase_order_id', $fundingPurchaseOrderIds)
            ->whereNotNull('paid_at')
            ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
            ->sum('amount');

        $confirmed = (float) ProcurementDisbursement::query()
            ->whereIn('purchase_order_id', ProcurementPurchaseOrder::query()
                ->whereNotNull('think_tank_member_id')
                ->where('po_type', 'think_tank_transfer')
                ->select('procurement_purchase_orders.id'))
            ->whereNotNull('paid_at')
            ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
            ->where('recipient_confirmation_status', 'confirmed')
            ->sum('amount');

        $pending = max($transferred - $confirmed, 0);
        $pendingPayment = max($poAllocated - $transferred, 0);
        $remaining = max($budget - $poAllocated, 0);

        return [
            'allocated' => $allocated,
            'budget' => $budget,
            'po_allocated' => $poAllocated,
            'transferred' => $transferred,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'pending_payment' => $pendingPayment,
            'remaining' => $remaining,
            'po_allocation_rate' => $budget > 0 ? round(($poAllocated / $budget) * 100, 1) : 0,
            'transfer_rate' => $budget > 0 ? round(($transferred / $budget) * 100, 1) : 0,
            'payment_rate' => $poAllocated > 0 ? round(($transferred / $poAllocated) * 100, 1) : 0,
            'remaining_rate' => $budget > 0 ? round(($remaining / $budget) * 100, 1) : 0,
            'confirmation_rate' => $transferred > 0 ? round(($confirmed / $transferred) * 100, 1) : 0,
        ];
    }

    private function directoryConsortiumRollups($thinkTanks, $consortia)
    {
        $thinkTanksByConsortium = $thinkTanks->groupBy(fn (ConsortiumThinkTank $thinkTank) => (string) ($thinkTank->consortium_id ?: 'unassigned'));

        $rollups = $consortia
            ->map(function (Consortium $consortium) use ($thinkTanksByConsortium) {
                $members = $thinkTanksByConsortium->get((string) $consortium->id, collect());

                return $this->directoryConsortiumRollupRow($consortium, $members);
            })
            ->filter(fn (array $row) => $row['think_tanks'] > 0)
            ->values();

        $unassignedMembers = $thinkTanksByConsortium->get('unassigned', collect());
        if ($unassignedMembers->isNotEmpty()) {
            $rollups->push($this->directoryConsortiumRollupRow(null, $unassignedMembers));
        }

        return $rollups
            ->sortByDesc('think_tanks')
            ->values();
    }

    private function directoryConsortiumRollupRow(?Consortium $consortium, $members): array
    {
        $thinkTankCount = $members->count();
        $activeCount = $members->where('status', 'active')->count();
        $portalLinked = $members->filter(fn (ConsortiumThinkTank $member) => filled($member->portal_user_id))->count();
        $vendorLinked = $members->filter(fn (ConsortiumThinkTank $member) => filled($member->vendor_user_id))->count();
        $datasetLinked = $members->filter(fn (ConsortiumThinkTank $member) => filled($member->think_dataset_id))->count();
        $withReports = $members->filter(fn (ConsortiumThinkTank $member) => (int) ($member->reports_count ?? 0) > 0)->count();
        $poAmount = (float) $members->sum(fn (ConsortiumThinkTank $member) => (float) ($member->directory_po_amount ?? 0));
        $paidAmount = (float) $members->sum(fn (ConsortiumThinkTank $member) => (float) ($member->directory_paid_amount ?? 0));

        $profileRate = $thinkTankCount > 0
            ? round((($portalLinked + $vendorLinked + $datasetLinked) / ($thinkTankCount * 3)) * 100, 1)
            : 0;
        $activityRate = $thinkTankCount > 0 ? round(($withReports / $thinkTankCount) * 100, 1) : 0;
        $paymentRate = $poAmount > 0 ? round(($paidAmount / $poAmount) * 100, 1) : 0;

        return [
            'id' => $consortium?->id,
            'name' => $consortium?->name ?? 'Unassigned Think Tanks',
            'code' => $consortium?->code,
            'program' => $consortium?->programFunding?->program?->name,
            'currency' => $consortium?->currency ?? 'USD',
            'think_tanks' => $thinkTankCount,
            'active' => $activeCount,
            'portal_linked' => $portalLinked,
            'vendor_linked' => $vendorLinked,
            'dataset_linked' => $datasetLinked,
            'reports' => (int) $members->sum(fn (ConsortiumThinkTank $member) => (int) ($member->reports_count ?? 0)),
            'research' => (int) $members->sum(fn (ConsortiumThinkTank $member) => (int) ($member->research_outputs_count ?? 0)),
            'procurements' => (int) $members->sum(fn (ConsortiumThinkTank $member) => (int) ($member->procurements_count ?? 0)),
            'po_amount' => $poAmount,
            'paid_amount' => $paidAmount,
            'unpaid_amount' => max($poAmount - $paidAmount, 0),
            'profile_rate' => $profileRate,
            'activity_rate' => $activityRate,
            'payment_rate' => $paymentRate,
            'progress_rate' => round(($profileRate + $activityRate + $paymentRate) / 3, 1),
        ];
    }

    private function directoryFinanceTotals($members): array
    {
        if ($members->isEmpty()) {
            return [
                'po_amount' => 0.0,
                'po_count' => 0,
                'paid_amount' => 0.0,
                'paid_disbursement_count' => 0,
            ];
        }

        $memberIds = $members->pluck('id')->filter()->values();
        $vendorIds = $this->directoryVendorIds($members);

        $purchaseOrderIds = ProcurementPurchaseOrder::query()
            ->where(function ($query) use ($memberIds, $vendorIds) {
                $query->whereIn('think_tank_member_id', $memberIds);

                if ($vendorIds->isNotEmpty()) {
                    $query->orWhereIn('vendor_id', $vendorIds);
                }
            })
            ->pluck('id');

        $poAmount = (float) ProcurementPurchaseOrder::query()
            ->whereKey($purchaseOrderIds)
            ->sum('amount');

        $paidDisbursementQuery = ProcurementDisbursement::query()
            ->where(function ($query) use ($memberIds, $vendorIds, $purchaseOrderIds) {
                $query->whereIn('think_tank_member_id', $memberIds);

                if ($vendorIds->isNotEmpty()) {
                    $query->orWhereIn('vendor_id', $vendorIds);
                }

                if ($purchaseOrderIds->isNotEmpty()) {
                    $query->orWhereIn('purchase_order_id', $purchaseOrderIds);
                }
            });

        $this->paidDisbursements($paidDisbursementQuery);

        return [
            'po_amount' => $poAmount,
            'po_count' => $purchaseOrderIds->count(),
            'paid_amount' => (float) (clone $paidDisbursementQuery)->sum('amount'),
            'paid_disbursement_count' => (int) (clone $paidDisbursementQuery)->count(),
        ];
    }

    private function hydrateDirectoryFinance($thinkTanks, ?Carbon $startDate = null, ?Carbon $endDate = null): void
    {
        if ($thinkTanks->isEmpty()) {
            return;
        }

        $memberIds = $thinkTanks->pluck('id')->filter()->values();
        $vendorIds = $this->directoryVendorIds($thinkTanks);

        $purchaseOrders = ProcurementPurchaseOrder::query()
            ->with([
                'vendor',
                'purchaseRequest.attachments',
                'budgetCommitment.purchaseRequest.attachments',
                'lineItemEvidence',
                'disbursements' => fn ($query) => $this->paidDisbursements($query)
                    ->latest('paid_at')
                    ->latest(),
            ])
            ->where(function ($query) use ($memberIds, $vendorIds) {
                $query->whereIn('think_tank_member_id', $memberIds);

                if ($vendorIds->isNotEmpty()) {
                    $query->orWhereIn('vendor_id', $vendorIds);
                }
            })
            ->when($startDate || $endDate, function ($query) use ($startDate, $endDate) {
                $query->where(function ($dateQuery) use ($startDate, $endDate) {
                    $dateQuery
                        ->when($startDate, fn ($builder) => $builder->where('issued_at', '>=', $startDate))
                        ->when($endDate, fn ($builder) => $builder->where('issued_at', '<=', $endDate))
                        ->orWhere(function ($fallbackQuery) use ($startDate, $endDate) {
                            $fallbackQuery->whereNull('issued_at')
                                ->when($startDate, fn ($builder) => $builder->where('created_at', '>=', $startDate))
                                ->when($endDate, fn ($builder) => $builder->where('created_at', '<=', $endDate));
                        });
                });
            })
            ->latest('issued_at')
            ->latest()
            ->get();

        $purchaseOrderIds = $purchaseOrders->pluck('id')->filter()->values();

        $disbursements = ProcurementDisbursement::query()
            ->with([
                'purchaseOrder.purchaseRequest.attachments',
                'purchaseOrder.budgetCommitment.purchaseRequest.attachments',
            ])
            ->where(function ($query) use ($memberIds, $vendorIds, $purchaseOrderIds) {
                $query->whereIn('think_tank_member_id', $memberIds);

                if ($vendorIds->isNotEmpty()) {
                    $query->orWhereIn('vendor_id', $vendorIds);
                }

                if ($purchaseOrderIds->isNotEmpty()) {
                    $query->orWhereIn('purchase_order_id', $purchaseOrderIds);
                }
            });

        $this->paidDisbursements($disbursements);
        $disbursements
            ->when($startDate, fn ($query) => $query->where('paid_at', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->where('paid_at', '<=', $endDate));

        $disbursements = $disbursements
            ->latest('paid_at')
            ->latest()
            ->get();

        $thinkTanks->each(function (ConsortiumThinkTank $thinkTank) use ($purchaseOrders, $disbursements) {
            $financeVendorIds = collect([$thinkTank->vendor_user_id, $thinkTank->portal_user_id])
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();

            $relatedPurchaseOrders = $purchaseOrders
                ->filter(function (ProcurementPurchaseOrder $purchaseOrder) use ($thinkTank, $financeVendorIds) {
                    $directMemberMatch = (string) $purchaseOrder->think_tank_member_id === (string) $thinkTank->id;
                    $vendorMatch = $purchaseOrder->vendor_id
                        && in_array((string) $purchaseOrder->vendor_id, $financeVendorIds, true);

                    return $directMemberMatch || $vendorMatch;
                })
                ->unique('id')
                ->values();

            $relatedPurchaseOrderIds = $relatedPurchaseOrders
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $relatedDisbursements = $disbursements
                ->filter(function (ProcurementDisbursement $disbursement) use ($thinkTank, $financeVendorIds, $relatedPurchaseOrderIds) {
                    $directMemberMatch = (string) $disbursement->think_tank_member_id === (string) $thinkTank->id;
                    $vendorMatch = $disbursement->vendor_id
                        && in_array((string) $disbursement->vendor_id, $financeVendorIds, true);
                    $purchaseOrderMatch = $disbursement->purchase_order_id
                        && in_array((string) $disbursement->purchase_order_id, $relatedPurchaseOrderIds, true);

                    return $directMemberMatch || $vendorMatch || $purchaseOrderMatch;
                })
                ->unique('id')
                ->values();

            $purchaseRequests = $relatedPurchaseOrders
                ->map(fn (ProcurementPurchaseOrder $purchaseOrder) => $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest)
                ->merge($relatedDisbursements->map(function (ProcurementDisbursement $disbursement) {
                    $purchaseOrder = $disbursement->purchaseOrder;

                    return $purchaseOrder?->purchaseRequest ?: $purchaseOrder?->budgetCommitment?->purchaseRequest;
                }))
                ->filter()
                ->unique('id')
                ->values();

            $poAmount = (float) $relatedPurchaseOrders->sum(fn (ProcurementPurchaseOrder $purchaseOrder) => (float) $purchaseOrder->amount);
            $paidAmount = (float) $relatedDisbursements->sum(fn (ProcurementDisbursement $disbursement) => (float) $disbursement->amount);

            $thinkTank->setRelation('directoryPurchaseOrders', $relatedPurchaseOrders);
            $thinkTank->setRelation('directoryPurchaseRequests', $purchaseRequests);
            $thinkTank->setRelation('directoryDisbursements', $relatedDisbursements);
            $thinkTank->setAttribute('directory_po_amount', $poAmount);
            $thinkTank->setAttribute('directory_paid_amount', $paidAmount);
            $thinkTank->setAttribute('directory_unpaid_amount', max($poAmount - $paidAmount, 0));
            $thinkTank->setAttribute('directory_po_count', $relatedPurchaseOrders->count());
            $thinkTank->setAttribute('directory_pr_count', $purchaseRequests->count());
            $thinkTank->setAttribute('directory_disbursement_count', $relatedDisbursements->count());
        });
    }

    private function directoryVendorIds($members)
    {
        return $members
            ->flatMap(fn (ConsortiumThinkTank $member) => [$member->vendor_user_id, $member->portal_user_id])
            ->filter()
            ->unique()
            ->values();
    }

    private function paidDisbursements($query)
    {
        return $query
            ->whereNotNull('paid_at')
            ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES);
    }

    private function fundingTransferDisbursements($query)
    {
        return $this->paidDisbursements($query)
            ->whereHas('purchaseOrder', fn ($purchaseOrderQuery) => $purchaseOrderQuery->where('po_type', 'think_tank_transfer'));
    }

    private function nextReference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (
            PurchaseRequest::where('reference_no', $reference)->exists()
            || ProcurementDisbursement::where('reference_no', $reference)->exists()
        );

        return $reference;
    }

    private function auditAction(string $action, string $message, array $payload = []): void
    {
        try {
            $request = request();

            SystemAuditLog::create([
                'user_id' => $request->user()?->id,
                'module' => 'think_tank_management',
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
            // Audit logging must never block the operational workflow.
        }
    }
}
